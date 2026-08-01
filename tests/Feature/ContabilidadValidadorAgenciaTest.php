<?php

namespace Tests\Feature;

use App\Http\Middleware\ExpireInactiveSession;
use App\Http\Middleware\ForcePasswordChange;
use App\Models\CentroDeCosto;
use App\Models\ValidadorAgenciaCarga;
use App\Models\ValidadorAgenciaDetalle;
use App\Services\Contabilidad\ValidadorAgenciaCsvService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContabilidadValidadorAgenciaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            Authenticate::class,
            ForcePasswordChange::class,
            ExpireInactiveSession::class,
        ]);
        $this->crearEsquema();
    }

    public function test_clasifica_terminales_por_id_viejo_normalizado_y_empresa(): void
    {
        CentroDeCosto::query()->create([
            'id_centro_costo' => 10,
            'company_id' => '168',
            'descripcion' => 'AGENCIA CORRECTA',
            'id_viejo' => '05502503',
            'id_grupo' => 'RUTA 01',
            'id_sociedad' => 'GRUPO JOSELITO',
        ]);
        CentroDeCosto::query()->create([
            'id_centro_costo' => 11,
            'company_id' => '169',
            'descripcion' => 'OTRA EMPRESA',
            'id_viejo' => '05502503',
            'id_grupo' => 'RUTA 01',
            'id_sociedad' => 'GRUPO JOSELITO',
        ]);
        CentroDeCosto::query()->create([
            'id_centro_costo' => 12,
            'company_id' => '168',
            'descripcion' => 'NOMBRE ANTERIOR',
            'id_viejo' => '05502504',
            'id_grupo' => 'RUTA 02',
            'id_sociedad' => 'GRUPO JOSELITO',
        ]);

        $archivo = UploadedFile::fake()->createWithContent('terminales.csv', implode("\n", [
            'Textbox40,Banca,Grupo,Ruta,ColumnaIgnorada',
            '5502503,AGENCIA CORRECTA,GRUPO JOSELITO,RUTA 01,NO USAR',
            '5502504,NOMBRE NUEVO,GRUPO JOSELITO,RUTA 02,NO USAR',
            '5502505,AGENCIA NUEVA,NEGOSUR,RUTA 03,NO USAR',
        ]));

        $resultado = app(ValidadorAgenciaCsvService::class)->procesar($archivo);
        $filas = collect($resultado['filas'])->keyBy('terminal');

        $this->assertSame(3, $resultado['control']['filas_validas']);
        $this->assertSame('correcto', $filas['5502503']['estado']);
        $this->assertSame('1', (string) $filas['5502503']['centro_costo_id']);
        $this->assertSame('nombre_diferente', $filas['5502504']['estado']);
        $this->assertSame('nuevo', $filas['5502505']['estado']);
        $this->assertSame('169', $filas['5502505']['company_id']);
        $this->assertArrayNotHasKey('columna_ignorada', $filas['5502503']);
    }

    public function test_rechaza_archivo_sin_las_cuatro_columnas_requeridas(): void
    {
        $archivo = UploadedFile::fake()->createWithContent(
            'incompleto.csv',
            "Textbox40,Banca,Grupo\n5502503,AGENCIA,GRUPO JOSELITO"
        );

        $this->expectException(ValidationException::class);

        app(ValidadorAgenciaCsvService::class)->procesar($archivo);
    }

    public function test_carga_el_reporte_sin_modificar_centros_de_costo(): void
    {
        $archivo = UploadedFile::fake()->createWithContent('terminales.csv', implode("\n", [
            'Textbox40,Banca,Grupo,Ruta',
            '5502505,AGENCIA NUEVA,GRUPO JOSELITO,RUTA 03',
        ]));

        $response = $this->post(route('contabilidad.validador-agencia.procesar'), [
            'archivo_csv' => $archivo,
        ]);

        $carga = ValidadorAgenciaCarga::query()->firstOrFail();
        $response->assertRedirect(route('contabilidad.validador-agencia', ['carga' => $carga->id]));
        $this->assertDatabaseHas('validador_agencia_detalles', [
            'terminal' => '5502505',
            'nombre_agencia' => 'AGENCIA NUEVA',
            'ruta' => 'RUTA 03',
            'sociedad' => 'GRUPO JOSELITO',
            'estado' => 'nuevo',
        ]);
        $this->assertDatabaseCount('centros_de_costo', 0);
    }

    public function test_actualiza_nombre_y_conserva_historial_del_cambio(): void
    {
        $centro = CentroDeCosto::query()->create([
            'id_centro_costo' => 20,
            'company_id' => '168',
            'descripcion' => 'NOMBRE ANTERIOR',
            'id_viejo' => '05502504',
        ]);
        $carga = ValidadorAgenciaCarga::factory()->create(['nombre_archivo' => 'terminales.csv']);
        $detalle = ValidadorAgenciaDetalle::factory()->create([
            'carga_id' => $carga->id,
            'centro_costo_id' => $centro->id,
            'terminal' => '5502504',
            'terminal_normalizada' => '5502504',
            'nombre_agencia' => 'NOMBRE NUEVO',
            'nombre_centro_costo' => 'NOMBRE ANTERIOR',
            'estado' => 'nombre_diferente',
        ]);

        $response = $this->postJson(
            route('contabilidad.validador-agencia.aplicar', $detalle),
            ['observacion' => 'Validado por Contabilidad']
        );

        $response->assertOk()->assertJsonPath('data.accion', 'actualizacion');
        $this->assertSame('NOMBRE NUEVO', $centro->fresh()->descripcion);
        $this->assertDatabaseHas('centro_costo_historial_cambios', [
            'terminal_normalizada' => '5502504',
            'accion' => 'actualizacion',
            'valor_anterior' => 'NOMBRE ANTERIOR',
            'valor_nuevo' => 'NOMBRE NUEVO',
            'archivo_origen' => 'terminales.csv',
            'observacion' => 'Validado por Contabilidad',
        ]);
        $this->assertSame('correcto', $detalle->fresh()->estado);
    }

    public function test_crea_terminal_y_registra_historial(): void
    {
        $carga = ValidadorAgenciaCarga::factory()->create();
        $detalle = ValidadorAgenciaDetalle::factory()->create([
            'carga_id' => $carga->id,
            'terminal' => '5502999',
            'terminal_normalizada' => '5502999',
            'nombre_agencia' => 'AGENCIA CREADA',
            'ruta' => 'RUTA 10',
            'sociedad' => 'NEGOSUR',
            'company_id' => '169',
            'estado' => 'nuevo',
        ]);

        $this->postJson(route('contabilidad.validador-agencia.aplicar', $detalle))
            ->assertOk()
            ->assertJsonPath('data.accion', 'creacion');

        $this->assertDatabaseHas('centros_de_costo', [
            'company_id' => '169',
            'id_viejo' => '5502999',
            'descripcion' => 'AGENCIA CREADA',
            'id_grupo' => 'RUTA 10',
        ]);
        $this->assertDatabaseHas('centro_costo_historial_cambios', [
            'terminal_normalizada' => '5502999',
            'accion' => 'creacion',
            'valor_anterior' => null,
            'valor_nuevo' => 'AGENCIA CREADA',
        ]);
    }

    public function test_detecta_y_actualiza_una_ruta_diferente_usando_el_archivo_como_referencia(): void
    {
        $centro = CentroDeCosto::query()->create([
            'id_centro_costo' => 25,
            'company_id' => '168',
            'descripcion' => 'AGENCIA SIN CAMBIO',
            'id_viejo' => '05502510',
            'id_grupo' => 'RUTA ANTERIOR',
        ]);
        $carga = ValidadorAgenciaCarga::factory()->create();
        $detalle = ValidadorAgenciaDetalle::factory()->create([
            'carga_id' => $carga->id,
            'centro_costo_id' => $centro->id,
            'terminal' => '5502510',
            'terminal_normalizada' => '5502510',
            'nombre_agencia' => 'AGENCIA SIN CAMBIO',
            'nombre_centro_costo' => 'AGENCIA SIN CAMBIO',
            'ruta' => 'JOSELITO RUTA 01',
            'ruta_centro_costo' => 'RUTA ANTERIOR',
            'estado' => 'ruta_diferente',
        ]);

        $this->postJson(route('contabilidad.validador-agencia.aplicar', $detalle))
            ->assertOk()
            ->assertJsonPath('data.accion', 'actualizacion');

        $this->assertSame('JOSELITO RUTA 01', $centro->fresh()->id_grupo);
        $this->assertDatabaseHas('centro_costo_historial_cambios', [
            'terminal_normalizada' => '5502510',
            'campo' => 'id_grupo',
            'valor_anterior' => 'RUTA ANTERIOR',
            'valor_nuevo' => 'JOSELITO RUTA 01',
        ]);
    }

    public function test_actualiza_la_sociedad_y_conserva_el_cambio_en_el_historial(): void
    {
        $centro = CentroDeCosto::query()->create([
            'id_centro_costo' => 26,
            'company_id' => '168',
            'descripcion' => 'AGENCIA SIN CAMBIO',
            'id_viejo' => '05502511',
            'id_grupo' => 'RUTA 01',
            'id_sociedad' => 'SOCIEDAD ANTERIOR',
        ]);
        $carga = ValidadorAgenciaCarga::factory()->create();
        $detalle = ValidadorAgenciaDetalle::factory()->create([
            'carga_id' => $carga->id,
            'centro_costo_id' => $centro->id,
            'terminal' => '5502511',
            'terminal_normalizada' => '5502511',
            'nombre_agencia' => 'AGENCIA SIN CAMBIO',
            'nombre_centro_costo' => 'AGENCIA SIN CAMBIO',
            'ruta' => 'RUTA 01',
            'ruta_centro_costo' => 'RUTA 01',
            'sociedad' => 'AGENCIAS JOSELITO',
            'sociedad_centro_costo' => 'SOCIEDAD ANTERIOR',
            'estado' => 'sociedad_diferente',
        ]);

        $this->postJson(route('contabilidad.validador-agencia.aplicar', $detalle))
            ->assertOk();

        $this->assertSame('AGENCIAS JOSELITO', $centro->fresh()->id_sociedad);
        $this->assertDatabaseHas('centro_costo_historial_cambios', [
            'terminal_normalizada' => '5502511',
            'campo' => 'id_sociedad',
            'valor_anterior' => 'SOCIEDAD ANTERIOR',
            'valor_nuevo' => 'AGENCIAS JOSELITO',
        ]);
    }

    public function test_no_duplica_una_terminal_creada_despues_de_la_carga(): void
    {
        $carga = ValidadorAgenciaCarga::factory()->create();
        $detalle = ValidadorAgenciaDetalle::factory()->create([
            'carga_id' => $carga->id,
            'terminal' => '5502998',
            'terminal_normalizada' => '5502998',
            'company_id' => '168',
            'estado' => 'nuevo',
        ]);
        CentroDeCosto::query()->create([
            'id_centro_costo' => 30,
            'company_id' => '168',
            'descripcion' => 'CREADA POR OTRO USUARIO',
            'id_viejo' => '05502998',
        ]);

        $this->postJson(route('contabilidad.validador-agencia.aplicar', $detalle))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('detalle');

        $this->assertDatabaseCount('centros_de_costo', 1);
        $this->assertDatabaseCount('centro_costo_historial_cambios', 0);
    }

    public function test_el_reporte_esta_en_contabilidad_y_ofrece_excel_e_historial(): void
    {
        $item = collect(config('module_hubs.contabilidad.items'))
            ->firstWhere('nombre', 'Validador de Agencia');
        $vista = file_get_contents(resource_path('views/contabilidad/validador-agencia.blade.php'));

        $this->assertNotNull($item);
        $this->assertSame('/contabilidad/validador-agencia', $item['url']);
        $this->assertIsString($vista);
        $this->assertStringContainsString('Descargar Excel', $vista);
        $this->assertStringContainsString('Ver historial completo', $vista);
        $this->assertStringContainsString('Nombre de la agencia', $vista);
        $this->assertStringContainsString('<th>Ruta actual</th>', $vista);
        $this->assertStringContainsString('<th>Sociedad actual</th>', $vista);
        $this->assertStringContainsString('Sociedades diferentes', $vista);
        $this->assertStringNotContainsString('table table-bordered table-hover', $vista);
        $this->assertStringContainsString('Conflicto en Nombre', $vista);
        $this->assertStringContainsString('Conflicto en Ruta', $vista);
        $this->assertStringContainsString('Conflicto en Sociedad', $vista);
        $this->assertStringContainsString('modal-conflicto-agencia', $vista);
        $this->assertStringContainsString('Datos por actualizar', $vista);
        $this->assertStringContainsString('modal-incidencias-agencia', $vista);
        $this->assertStringContainsString("str_contains(\$detalle->estado, 'sociedad_')", $vista);
        $this->assertStringNotContainsString('small text-muted mt-1', $vista);
    }

    private function crearEsquema(): void
    {
        Schema::create('centros_de_costo', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_centro_costo');
            $table->string('company_id')->nullable();
            $table->string('descripcion');
            $table->string('id_grupo')->nullable();
            $table->string('id_sociedad')->nullable();
            $table->string('id_viejo')->nullable();
            $table->boolean('inactivo')->default(false);
            $table->string('creado_por')->nullable();
            $table->dateTime('fecha_grabado')->nullable();
            $table->string('modificado_por')->nullable();
            $table->dateTime('fecha_modificado')->nullable();
            $table->json('atributos')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'id_centro_costo']);
        });

        Schema::create('validador_agencia_cargas', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_archivo');
            $table->string('hash_archivo')->nullable();
            $table->unsignedInteger('filas_leidas')->default(0);
            $table->unsignedInteger('filas_validas')->default(0);
            $table->unsignedInteger('correctas')->default(0);
            $table->unsignedInteger('nuevas')->default(0);
            $table->unsignedInteger('nombres_diferentes')->default(0);
            $table->unsignedInteger('rutas_diferentes')->default(0);
            $table->unsignedInteger('sociedades_diferentes')->default(0);
            $table->unsignedInteger('conflictos')->default(0);
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();
        });

        Schema::create('validador_agencia_detalles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('carga_id');
            $table->unsignedBigInteger('centro_costo_id')->nullable();
            $table->string('terminal');
            $table->string('terminal_normalizada');
            $table->string('nombre_agencia');
            $table->string('ruta');
            $table->string('sociedad');
            $table->string('company_id');
            $table->string('nombre_centro_costo')->nullable();
            $table->string('ruta_centro_costo')->nullable();
            $table->string('sociedad_centro_costo')->nullable();
            $table->string('estado');
            $table->text('observacion')->nullable();
            $table->timestamp('aplicado_en')->nullable();
            $table->unsignedBigInteger('aplicado_por')->nullable();
            $table->timestamps();
        });

        Schema::create('centro_costo_historial_cambios', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('centro_costo_id')->nullable();
            $table->unsignedBigInteger('carga_id')->nullable();
            $table->unsignedBigInteger('detalle_id')->nullable();
            $table->string('terminal');
            $table->string('terminal_normalizada');
            $table->string('company_id');
            $table->string('accion');
            $table->string('campo');
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->string('archivo_origen')->nullable();
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();
        });
    }
}
