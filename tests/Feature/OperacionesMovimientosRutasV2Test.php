<?php

namespace Tests\Feature;

use App\Http\Controllers\OperacionesMovimientosRutasV2Controller;
use App\Http\Requests\Operaciones\FiltrarMovimientosRutasV2Request;
use App\Http\Requests\Operaciones\GuardarMovimientoRutaV2DepositoRequest;
use App\Http\Requests\Operaciones\GuardarMovimientoRutaV2GastoRequest;
use App\Http\Requests\Operaciones\ReporteMovimientoRutaV2PdfRequest;
use App\Models\BancoOperacion;
use App\Models\MovimientoRutaV2Deposito;
use App\Models\MovimientoRutaV2Importacion;
use App\Models\MovimientoRutaV2Transaccion;
use App\Services\Operaciones\MovimientosRutasV2ImportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OperacionesMovimientosRutasV2Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('bancos_operaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });
        Schema::create('movimientos_rutas_v2_importaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_archivo');
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->unsignedInteger('fechas_reemplazadas');
            $table->unsignedInteger('filas_aceptadas');
            $table->unsignedInteger('filas_descartadas');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
        Schema::create('movimientos_rutas_v2_transacciones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('importacion_id');
            $table->date('fecha');
            $table->string('ruta_key');
            $table->string('ruta');
            $table->string('id_trans');
            $table->string('terminal')->nullable();
            $table->string('nombre_agencia')->nullable();
            $table->string('tipo');
            $table->string('tipo_etiqueta');
            $table->decimal('monto', 15, 2);
            $table->decimal('monto_original', 15, 2);
            $table->timestamps();
            $table->unique(['fecha', 'id_trans']);
        });
        Schema::create('movimientos_rutas_v2_depositos', function (Blueprint $table): void {
            $table->id();
            $table->date('fecha');
            $table->string('ruta_key');
            $table->string('ruta');
            $table->decimal('monto', 15, 2);
            $table->string('banco');
            $table->string('referencia')->nullable();
            $table->string('comprobante_path')->nullable();
            $table->text('observacion')->nullable();
            $table->string('estado')->default('aplicado');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
        Schema::create('movimientos_rutas_v2_gastos', function (Blueprint $table): void {
            $table->id();
            $table->date('fecha');
            $table->string('ruta_key');
            $table->string('ruta');
            $table->decimal('monto', 15, 2);
            $table->string('concepto');
            $table->string('comprobante_path')->nullable();
            $table->text('observacion')->nullable();
            $table->string('estado')->default('aplicado');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('movimientos_rutas_v2_gastos');
        Schema::dropIfExists('movimientos_rutas_v2_depositos');
        Schema::dropIfExists('movimientos_rutas_v2_transacciones');
        Schema::dropIfExists('movimientos_rutas_v2_importaciones');
        Schema::dropIfExists('bancos_operaciones');

        parent::tearDown();
    }

    public function test_reemplaza_solo_las_fechas_del_archivo_y_conserva_los_depositos_manuales(): void
    {
        $servicio = app(MovimientosRutasV2ImportService::class);
        $servicio->importar($this->archivoCsv([
            $this->retiro('T-1', '02/08/2026', '05 - HAINA', -5000000),
        ]), null, '2026-08-02');
        $servicio->importar($this->archivoCsv([
            $this->retiro('T-2', '03/08/2026', '01 - NORTE', -1000000),
        ]), null, '2026-08-03');

        DB::table('movimientos_rutas_v2_depositos')->insert([
            'fecha' => '2026-08-02',
            'ruta_key' => '05 - HAINA',
            'ruta' => '05 - HAINA',
            'monto' => 3000000,
            'banco' => 'Banreservas',
            'estado' => 'aplicado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('movimientos_rutas_v2_gastos')->insert([
            'fecha' => '2026-08-02',
            'ruta_key' => '05 - HAINA',
            'ruta' => '05 - HAINA',
            'monto' => 1000000,
            'concepto' => 'Combustible y peajes',
            'estado' => 'aplicado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $servicio->importar($this->archivoCsv([
            $this->retiro('T-3', '02/08/2026', '05 - HAINA', -5500000),
        ]), null, '2026-08-02');

        $this->assertDatabaseMissing('movimientos_rutas_v2_transacciones', ['id_trans' => 'T-1']);
        $this->assertDatabaseHas('movimientos_rutas_v2_transacciones', ['id_trans' => 'T-3', 'fecha' => '2026-08-02']);
        $this->assertDatabaseHas('movimientos_rutas_v2_transacciones', ['id_trans' => 'T-2', 'fecha' => '2026-08-03']);
        $deposito = MovimientoRutaV2Deposito::query()->firstOrFail();
        $this->assertSame('2026-08-02', $deposito->fecha->toDateString());
        $this->assertSame(3000000.0, (float) $deposito->monto);
        $this->assertDatabaseHas('movimientos_rutas_v2_gastos', [
            'concepto' => 'Combustible y peajes',
            'monto' => 1000000,
        ]);
        $this->assertSame(2, MovimientoRutaV2Transaccion::query()->count());

        $metodoResumen = new \ReflectionMethod(OperacionesMovimientosRutasV2Controller::class, 'resumenPorRutas');
        $resumenRuta = $metodoResumen->invoke(app(OperacionesMovimientosRutasV2Controller::class), '2026-08-02')->first();
        $this->assertSame(5500000.0, $resumenRuta['neto_esperado']);
        $this->assertSame(3000000.0, $resumenRuta['depositado_banco']);
        $this->assertSame(1000000.0, $resumenRuta['gastos_ruta']);
        $this->assertSame(1500000.0, $resumenRuta['pendiente']);
        $this->assertSame('parcial', $resumenRuta['estado']);
    }

    public function test_registra_la_v2_en_el_hub_y_muestra_las_columnas_de_conciliacion(): void
    {
        $item = collect(config('module_hubs.operaciones.items'))->firstWhere('nombre', 'Movimientos por Ruta V2');
        $vista = file_get_contents(resource_path('views/operaciones/movimientos-rutas-v2.blade.php'));
        $vistaCompilada = Blade::compileString($vista);

        $this->assertNotNull($item);
        $this->assertSame('/operaciones/movimientos-rutas-v2', $item['url']);
        $this->assertIsString($vista);
        $this->assertStringContainsString('Depositado banco', $vista);
        $this->assertStringContainsString('Depósito Pérdida', $vista);
        $this->assertStringNotContainsString('Depósitos CSV', $vista);
        $this->assertStringContainsString('Pendiente', $vista);
        $this->assertStringContainsString('Aplicar depósito bancario', $vista);
        $this->assertStringContainsString('Gasto de ruta', $vista);
        $this->assertStringContainsString('Voucher o comprobante', $vista);
        $this->assertStringContainsString('Mini informe PDF', $vista);
        $this->assertStringContainsString('operaciones.movimientos-rutas-v2.pdf', $vista);
        $this->assertStringContainsString('name="empresa"', $vista);
        $this->assertStringContainsString('Todas las empresas', $vista);
        $this->assertStringContainsString('name="fecha_reporte"', $vista);
        $this->assertStringContainsString('Rendimiento de ruta', $vista);
        $this->assertStringContainsString("title: 'Las fechas no corresponden'", $vista);
        $this->assertStringContainsString('cumplimiento_depositos', $vista);
        $this->assertStringContainsString('Últimas importaciones del día', $vista);
        $this->assertStringContainsString('Balance pendiente', $vista);
        $this->assertStringContainsString("['balance_pendiente']", $vista);
        $this->assertStringContainsString('id="rendimiento-neto-esperado"', $vista);
        $this->assertStringContainsString('id="rendimiento-depositado-banco"', $vista);
        $this->assertStringContainsString('id="rendimiento-porcentaje"', $vista);
        $this->assertStringContainsString('height: 28px', $vista);
        $this->assertStringContainsString('id="zona-pegar-deposito"', $vista);
        $this->assertStringContainsString('id="zona-pegar-gasto"', $vista);
        $this->assertStringContainsString("modalElement.addEventListener('paste'", $vista);
        $this->assertStringContainsString('btn-eliminar-aplicacion', $vista);
        $this->assertStringContainsString("method: 'DELETE'", $vista);
        $this->assertStringContainsString('id="monto-deposito-visible"', $vista);
        $this->assertStringContainsString('id="monto-deposito"', $vista);
        $this->assertStringContainsString("titulo: 'Confirmar monto depositado'", $vista);
        $this->assertStringContainsString("decimales.padEnd(2, '0')", $vista);
        $this->assertContains('decimal:2', (new GuardarMovimientoRutaV2DepositoRequest)->rules()['monto']);
        $this->assertStringContainsString('id="tarjeta-depositado-banco"', $vista);
        $this->assertStringContainsString('id="modal-depositos-banco"', $vista);
        $this->assertStringContainsString('Depósitos por banco', $vista);
        $this->assertStringContainsString('Ver montos por banco', $vista);
        $this->assertStringContainsString('id="form-aplicar-gasto"', $vista);
        $this->assertStringContainsString('id="banco-deposito"', $vista);
        $this->assertStringContainsString('Selecciona un banco', $vista);
        $this->assertStringNotContainsString('lista-bancos-v2', $vista);
        $this->assertStringContainsString('id="monto-gasto-visible"', $vista);
        $this->assertStringContainsString('id="monto-gasto"', $vista);
        $this->assertStringContainsString("titulo: 'Confirmar monto del gasto'", $vista);
        $this->assertStringContainsString('function configurarMontoMonetario', $vista);
        $this->assertContains('decimal:2', (new GuardarMovimientoRutaV2GastoRequest)->rules()['monto']);
        $this->assertStringContainsString('btn-eliminar-importacion', $vista);
        $this->assertStringContainsString('Eliminar carga completa', $vista);
        $this->assertStringContainsString('operaciones.movimientos-rutas-v2.importaciones.eliminar', $vista);
        $this->assertStringNotContainsString('@php(', $vista);
        $this->assertStringContainsString('foreach($__currentLoopData as $ruta)', $vistaCompilada);
        $this->assertContains('in:GJ,NG', (new FiltrarMovimientosRutasV2Request)->rules()['empresa']);
        $this->assertContains('in:GJ,NG', (new ReporteMovimientoRutaV2PdfRequest)->rules()['empresa']);
    }

    public function test_el_catalogo_de_bancos_refleja_los_registros_disponibles(): void
    {
        BancoOperacion::query()->insert([
            ['nombre' => 'Banco Reservas'],
            ['nombre' => 'Banco Caribe'],
            ['nombre' => 'Banco Santa Cruz'],
            ['nombre' => 'Banco Popular'],
        ]);

        $bancos = BancoOperacion::nombresDisponibles();

        $this->assertSame([
            'Banco Caribe',
            'Banco Popular',
            'Banco Reservas',
            'Banco Santa Cruz',
        ], $bancos->all());
        $this->assertSame(1, $bancos->filter(
            fn (string $banco): bool => $banco === 'Banco Reservas'
        )->count());
    }

    public function test_filtra_por_empresa_sin_perder_el_detalle_individual_de_las_rutas(): void
    {
        app(MovimientosRutasV2ImportService::class)->importar($this->archivoCsv([
            $this->retiro('T-GJ-1', '07/08/2026', '05 - GJ RUTA ROMANA', -100),
            $this->retiro('T-GJ-2', '07/08/2026', '05 - GJ RUTA CONSUELO', -200),
            $this->retiro('T-NG-1', '07/08/2026', '05 - NG RUTA HAINA', -300),
            $this->retiro('T-SIN-1', '07/08/2026', '05 - RUTA SIN EMPRESA', -400),
        ]), null, '2026-08-07');

        $metodo = new \ReflectionMethod(OperacionesMovimientosRutasV2Controller::class, 'resumenPorRutas');
        $controlador = app(OperacionesMovimientosRutasV2Controller::class);
        $todas = $metodo->invoke($controlador, '2026-08-07');
        $grupoJoselito = $metodo->invoke($controlador, '2026-08-07', 'GJ');
        $negosur = $metodo->invoke($controlador, '2026-08-07', 'NG');

        $this->assertCount(4, $todas);
        $this->assertSame(
            ['05 - GJ RUTA CONSUELO', '05 - GJ RUTA ROMANA'],
            $grupoJoselito->pluck('ruta')->all(),
        );
        $this->assertSame(['05 - NG RUTA HAINA'], $negosur->pluck('ruta')->all());
        $this->assertSame(300.0, (float) $grupoJoselito->sum('neto_esperado'));
    }

    public function test_filtra_el_resumen_de_depositos_por_banco_segun_la_empresa(): void
    {
        foreach ([
            ['Banreservas', 100, '05 - GJ RUTA ROMANA'],
            ['Banreservas', 150, '05 - NG RUTA HAINA'],
            ['Popular', 300, '05 - GJ RUTA CONSUELO'],
            ['BHD', 400, '05 - RUTA SIN EMPRESA'],
        ] as [$banco, $monto, $ruta]) {
            DB::table('movimientos_rutas_v2_depositos')->insert([
                'fecha' => '2026-08-07',
                'ruta_key' => $ruta,
                'ruta' => $ruta,
                'monto' => $monto,
                'banco' => $banco,
                'estado' => 'aplicado',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $metodo = new \ReflectionMethod(OperacionesMovimientosRutasV2Controller::class, 'depositosPorBanco');
        $depositos = $metodo->invoke(app(OperacionesMovimientosRutasV2Controller::class), '2026-08-07', 'GJ');

        $this->assertCount(2, $depositos);
        $this->assertSame(400.0, (float) $depositos->sum('monto_total'));
        $this->assertNull($depositos->firstWhere('banco', 'BHD'));
    }

    public function test_agrupa_los_depositos_del_dia_por_banco(): void
    {
        foreach ([
            ['Banreservas', 100, 'aplicado', '2026-08-03'],
            ['Banreservas', 150, 'aplicado', '2026-08-03'],
            ['Popular', 300, 'aplicado', '2026-08-03'],
            ['BHD', 900, 'aplicado', '2026-08-02'],
            ['Scotiabank', 800, 'anulado', '2026-08-03'],
        ] as [$banco, $monto, $estado, $fecha]) {
            DB::table('movimientos_rutas_v2_depositos')->insert([
                'fecha' => $fecha,
                'ruta_key' => '05 - HAINA',
                'ruta' => '05 - HAINA',
                'monto' => $monto,
                'banco' => $banco,
                'estado' => $estado,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $metodo = new \ReflectionMethod(OperacionesMovimientosRutasV2Controller::class, 'depositosPorBanco');
        $depositos = $metodo->invoke(app(OperacionesMovimientosRutasV2Controller::class), '2026-08-03');
        $banreservas = $depositos->firstWhere('banco', 'Banreservas');

        $this->assertCount(2, $depositos);
        $this->assertSame('Popular', $depositos->first()->banco);
        $this->assertSame(250.0, (float) $banreservas->monto_total);
        $this->assertSame(2, (int) $banreservas->cantidad_depositos);
    }

    public function test_aplica_deposito_y_gasto_por_ajax_sin_recargar_la_tabla(): void
    {
        app(MovimientosRutasV2ImportService::class)->importar($this->archivoCsv([
            $this->retiro('T-AJAX', '03/08/2026', '05 - HAINA', -1000),
        ]), null, '2026-08-03');

        $deposito = $this->withoutMiddleware()->postJson(route('operaciones.movimientos-rutas-v2.depositos.guardar'), [
            'fecha' => '2026-08-03',
            'ruta_key' => '05 - HAINA',
            'ruta' => '05 - HAINA',
            'monto' => '200.00',
            'banco' => 'Banreservas',
            'referencia' => 'AJAX-001',
        ])->assertOk();

        $this->assertSame(200.0, (float) $deposito->json('ruta.depositado_banco'));
        $this->assertSame(800.0, (float) $deposito->json('ruta.pendiente'));
        $this->assertSame(200.0, (float) $deposito->json('resumen.depositado_banco'));
        $this->assertSame('Banreservas', $deposito->json('depositos_por_banco.0.banco'));

        $gasto = $this->withoutMiddleware()->postJson(route('operaciones.movimientos-rutas-v2.gastos.guardar'), [
            'fecha' => '2026-08-03',
            'ruta_key' => '05 - HAINA',
            'ruta' => '05 - HAINA',
            'monto' => '100.00',
            'concepto' => 'Peaje',
        ])->assertOk();

        $this->assertSame(200.0, (float) $gasto->json('ruta.depositado_banco'));
        $this->assertSame(100.0, (float) $gasto->json('ruta.gastos_ruta'));
        $this->assertSame(700.0, (float) $gasto->json('ruta.pendiente'));
        $this->assertSame(700.0, (float) $gasto->json('resumen.pendiente'));
        $this->assertDatabaseHas('movimientos_rutas_v2_depositos', ['referencia' => 'AJAX-001', 'monto' => 200]);
        $this->assertDatabaseHas('movimientos_rutas_v2_gastos', ['concepto' => 'Peaje', 'monto' => 100]);

        $vista = file_get_contents(resource_path('views/operaciones/movimientos-rutas-v2.blade.php'));
        $this->assertStringContainsString('fetch(formulario.action', $vista);
        $this->assertStringContainsString('tablaMovimientos.row(fila).data(datos).draw(false)', $vista);
        $this->assertStringNotContainsString('if (confirmado) formulario.submit();', $vista);
    }

    public function test_permite_eliminar_depositos_y_gastos_con_sus_comprobantes(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('comprobantes/deposito.png', 'deposito');
        Storage::disk('local')->put('comprobantes/gasto.png', 'gasto');

        $deposito = MovimientoRutaV2Deposito::query()->create([
            'fecha' => '2026-08-03',
            'ruta_key' => '05 - HAINA',
            'ruta' => '05 - HAINA',
            'monto' => 100,
            'banco' => 'Banreservas',
            'comprobante_path' => 'comprobantes/deposito.png',
            'estado' => 'aplicado',
        ]);
        $gasto = DB::table('movimientos_rutas_v2_gastos')->insertGetId([
            'fecha' => '2026-08-03',
            'ruta_key' => '05 - HAINA',
            'ruta' => '05 - HAINA',
            'monto' => 50,
            'concepto' => 'Peaje',
            'comprobante_path' => 'comprobantes/gasto.png',
            'estado' => 'aplicado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withoutMiddleware([
            \Illuminate\Auth\Middleware\Authenticate::class,
            \App\Http\Middleware\ForcePasswordChange::class,
        ])
            ->deleteJson(route('operaciones.movimientos-rutas-v2.depositos.eliminar', $deposito))
            ->assertOk()
            ->assertJsonPath('message', 'Depósito eliminado correctamente. Ya puedes cargar el registro nuevamente.');
        $this->assertDatabaseMissing('movimientos_rutas_v2_depositos', ['id' => $deposito->id]);
        Storage::disk('local')->assertMissing('comprobantes/deposito.png');

        $this->withoutMiddleware([
            \Illuminate\Auth\Middleware\Authenticate::class,
            \App\Http\Middleware\ForcePasswordChange::class,
        ])
            ->deleteJson(route('operaciones.movimientos-rutas-v2.gastos.eliminar', $gasto))
            ->assertOk()
            ->assertJsonPath('message', 'Gasto eliminado correctamente. Ya puedes cargar el registro nuevamente.');

        $this->assertDatabaseMissing('movimientos_rutas_v2_gastos', ['id' => $gasto]);
        Storage::disk('local')->assertMissing('comprobantes/gasto.png');
    }

    public function test_elimina_la_carga_del_dia_sus_transacciones_y_depositos_pero_conserva_los_gastos(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('comprobantes/deposito-carga.png', 'deposito');
        $resultadoImportacion = app(MovimientosRutasV2ImportService::class)->importar($this->archivoCsv([
            $this->retiro('T-CARGA-ELIMINAR', '02/08/2026', '05 - HAINA', -5000000),
        ]), null, '2026-08-02');
        MovimientoRutaV2Importacion::query()->create([
            'nombre_archivo' => 'carga-anterior.csv',
            'fecha_desde' => '2026-08-02',
            'fecha_hasta' => '2026-08-02',
            'fechas_reemplazadas' => 1,
            'filas_aceptadas' => 1,
            'filas_descartadas' => 0,
        ]);
        MovimientoRutaV2Deposito::query()->create([
            'fecha' => '2026-08-02',
            'ruta_key' => 'RUTA ANTIGUA',
            'ruta' => 'RUTA ANTIGUA',
            'monto' => 100,
            'banco' => 'Banreservas',
            'comprobante_path' => 'comprobantes/deposito-carga.png',
            'estado' => 'aplicado',
        ]);
        $depositoOtraFecha = MovimientoRutaV2Deposito::query()->create([
            'fecha' => '2026-08-03',
            'ruta_key' => 'OTRA RUTA',
            'ruta' => 'OTRA RUTA',
            'monto' => 200,
            'banco' => 'Popular',
            'estado' => 'aplicado',
        ]);
        DB::table('movimientos_rutas_v2_gastos')->insert([
            'fecha' => '2026-08-02',
            'ruta_key' => 'RUTA ANTIGUA',
            'ruta' => 'RUTA ANTIGUA',
            'monto' => 50,
            'concepto' => 'Peaje',
            'estado' => 'aplicado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withoutMiddleware([
            \Illuminate\Auth\Middleware\Authenticate::class,
            \App\Http\Middleware\ForcePasswordChange::class,
        ])->deleteJson(route(
            'operaciones.movimientos-rutas-v2.importaciones.eliminar',
            $resultadoImportacion['importacion'],
        ))->assertOk()
            ->assertJsonPath('data.importacionesEliminadas', 2)
            ->assertJsonPath('data.transaccionesEliminadas', 1)
            ->assertJsonPath('data.depositosEliminados', 1);

        $this->assertDatabaseMissing('movimientos_rutas_v2_importaciones', ['fecha_desde' => '2026-08-02']);
        $this->assertDatabaseMissing('movimientos_rutas_v2_transacciones', ['fecha' => '2026-08-02']);
        $this->assertDatabaseMissing('movimientos_rutas_v2_depositos', ['ruta_key' => 'RUTA ANTIGUA']);
        $this->assertDatabaseHas('movimientos_rutas_v2_depositos', ['id' => $depositoOtraFecha->id, 'monto' => 200]);
        $this->assertDatabaseHas('movimientos_rutas_v2_gastos', ['ruta_key' => 'RUTA ANTIGUA', 'monto' => 50]);
        Storage::disk('local')->assertMissing('comprobantes/deposito-carga.png');
    }

    public function test_muestra_comprobantes_del_disco_actual_y_de_la_ruta_heredada_del_vps(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('operaciones/actual.png', 'comprobante actual');

        $rutaHeredadaRelativa = 'operaciones/movimientos-rutas-v2/comprobantes/prueba-vps-heredada.png';
        $rutaHeredadaAbsoluta = storage_path('app/'.$rutaHeredadaRelativa);
        File::ensureDirectoryExists(dirname($rutaHeredadaAbsoluta));
        File::put($rutaHeredadaAbsoluta, 'comprobante heredado');

        try {
            $deposito = MovimientoRutaV2Deposito::query()->create([
                'fecha' => '2026-08-03',
                'ruta_key' => '05 - HAINA',
                'ruta' => '05 - HAINA',
                'monto' => 100,
                'banco' => 'Banreservas',
                'comprobante_path' => 'operaciones/actual.png',
                'estado' => 'aplicado',
            ]);
            $gasto = DB::table('movimientos_rutas_v2_gastos')->insertGetId([
                'fecha' => '2026-08-03',
                'ruta_key' => '05 - HAINA',
                'ruta' => '05 - HAINA',
                'monto' => 50,
                'concepto' => 'Peaje',
                'comprobante_path' => $rutaHeredadaRelativa,
                'estado' => 'aplicado',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->withoutMiddleware([
                \Illuminate\Auth\Middleware\Authenticate::class,
                \App\Http\Middleware\ForcePasswordChange::class,
            ])->get(route('operaciones.movimientos-rutas-v2.depositos.comprobante', $deposito))
                ->assertOk();
            $this->withoutMiddleware([
                \Illuminate\Auth\Middleware\Authenticate::class,
                \App\Http\Middleware\ForcePasswordChange::class,
            ])->get(route('operaciones.movimientos-rutas-v2.gastos.comprobante', $gasto))
                ->assertOk();
        } finally {
            File::delete($rutaHeredadaAbsoluta);
        }
    }

    public function test_rechaza_el_archivo_cuando_su_fecha_no_coincide_con_la_fecha_del_reporte(): void
    {
        try {
            app(MovimientosRutasV2ImportService::class)->importar($this->archivoCsv([
                $this->retiro('T-FECHA-INCORRECTA', '03/08/2026', '05 - HAINA', -5000000),
            ]), null, '2026-08-02');

            $this->fail('La importación debió rechazar una fecha diferente.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'La fecha del reporte (02/08/2026) no corresponde con la fecha del archivo (03/08/2026)',
                $exception->errors()['fecha_reporte'][0],
            );
        }

        $this->assertDatabaseCount('movimientos_rutas_v2_importaciones', 0);
        $this->assertDatabaseCount('movimientos_rutas_v2_transacciones', 0);
    }

    public function test_balance_pendiente_acumula_los_dias_anteriores_de_la_ruta(): void
    {
        $servicio = app(MovimientosRutasV2ImportService::class);

        foreach ([
            ['2026-08-01', '01/08/2026', 'T-BALANCE-1', -100],
            ['2026-08-02', '02/08/2026', 'T-BALANCE-2', -150],
            ['2026-08-03', '03/08/2026', 'T-BALANCE-3', -100],
        ] as [$fechaIso, $fechaArchivo, $transaccion, $monto]) {
            $servicio->importar($this->archivoCsv([
                $this->retiro($transaccion, $fechaArchivo, '05 - HAINA', $monto),
            ]), null, $fechaIso);
        }

        $metodoResumen = new \ReflectionMethod(OperacionesMovimientosRutasV2Controller::class, 'resumenPorRutas');
        $resumenRuta = $metodoResumen->invoke(app(OperacionesMovimientosRutasV2Controller::class), '2026-08-03')->first();

        $this->assertSame(100.0, $resumenRuta['pendiente']);
        $this->assertSame(350.0, $resumenRuta['balance_pendiente']);
    }

    public function test_ultimas_importaciones_solo_incluye_el_dia_consultado(): void
    {
        $servicio = app(MovimientosRutasV2ImportService::class);
        $servicio->importar($this->archivoCsv([
            $this->retiro('T-DIA-2', '02/08/2026', '05 - HAINA', -5000000),
        ]), null, '2026-08-02');
        $servicio->importar($this->archivoCsv([
            $this->retiro('T-DIA-3', '03/08/2026', '01 - NORTE', -1000000),
        ]), null, '2026-08-03');

        $metodo = new \ReflectionMethod(OperacionesMovimientosRutasV2Controller::class, 'importacionesPorFecha');
        $importaciones = $metodo->invoke(app(OperacionesMovimientosRutasV2Controller::class), '2026-08-02');

        $this->assertCount(1, $importaciones);
        $this->assertInstanceOf(MovimientoRutaV2Importacion::class, $importaciones->first());
        $this->assertSame('2026-08-02', $importaciones->first()->fecha_desde->toDateString());
        $this->assertSame('2026-08-02', $importaciones->first()->fecha_hasta->toDateString());
    }

    public function test_genera_el_mini_informe_pdf_de_las_tarjetas_del_dia(): void
    {
        app(MovimientosRutasV2ImportService::class)->importar($this->archivoCsv([
            $this->retiro('T-PDF', '02/08/2026', '05 - HAINA', -5000000),
        ]), null, '2026-08-02');

        $response = $this->withoutMiddleware()->get(route('operaciones.movimientos-rutas-v2.pdf', [
            'fecha' => '2026-08-02',
        ]));
        $vistaPdf = file_get_contents(resource_path('views/operaciones/movimientos-rutas-v2-pdf.blade.php'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertDownload('resumen-movimientos-rutas-2026-08-02.pdf');
        $this->assertIsString($vistaPdf);
        $this->assertStringContainsString('Mini informe diario de conciliación', $vistaPdf);
        $this->assertStringContainsString('Cumplimiento del neto esperado', $vistaPdf);
        $this->assertStringNotContainsString('Transacciones del CSV', $vistaPdf);
        $this->assertStringNotContainsString('Depósitos bancarios aplicados', $vistaPdf);
    }

    /** @param  array<int, string>  $filas */
    private function archivoCsv(array $filas): UploadedFile
    {
        $encabezado = 'TipoTransaccion,NumeroExterno,Ruta,IdTrans,FecTransaccion,Referencia,DMonto2';

        return UploadedFile::fake()->createWithContent('movimientos.csv', implode("\n", [$encabezado, ...$filas]));
    }

    private function retiro(string $id, string $fecha, string $ruta, float $monto): string
    {
        return implode(',', [
            'RETIRO DE EFECTIVO DE LA AGENCIA E INGRESO A LA CAJA',
            '', $ruta, $id, $fecha, '', number_format($monto, 2, '.', ''),
        ]);
    }
}
