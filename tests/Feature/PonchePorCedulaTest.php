<?php

namespace Tests\Feature;

use App\Services\RecursosHumanos\NominaDomingoVentasImportService;
use App\Services\RecursosHumanos\PonchePorCedulaCargaTemporal;
use App\ViewPermissionCatalog;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PonchePorCedulaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();

        Schema::create('asistencias_bet', function (Blueprint $table): void {
            $table->id('asistencia_id');
            $table->date('fecha');
            $table->string('cedula');
            $table->string('agencia_id')->nullable();
            $table->string('usuario')->nullable();
            $table->dateTime('primer_login')->nullable();
            $table->dateTime('ultimo_login')->nullable();
        });
        Schema::create('asistencias_net', function (Blueprint $table): void {
            $table->id('asistencia_id');
            $table->string('identificacion');
            $table->string('terminal')->nullable();
            $table->string('usuario')->nullable();
            $table->string('username')->nullable();
            $table->dateTime('entrada')->nullable();
            $table->dateTime('salida')->nullable();
        });
        Schema::create('empleados', function (Blueprint $table): void {
            $table->id();
            $table->string('cedula');
            $table->string('empleadoid')->nullable();
            $table->string('nombres')->nullable();
            $table->string('apellidos')->nullable();
            $table->date('fechasalida')->nullable();
        });
        Schema::create('nomina_domingo_ventas', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal')->nullable();
            $table->string('usuario_venta')->nullable();
            $table->dateTime('fecha_transaccion')->nullable();
            $table->string('tipo')->nullable();
            $table->decimal('total_apostado', 12, 2)->nullable();
            $table->string('estatus')->nullable();
        });
        (require database_path('migrations/2026_10_14_125659_create_ponche_por_cedula_ventas_table.php'))->up();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('ponche_por_cedula_ventas');
        Schema::dropIfExists('nomina_domingo_ventas');
        Schema::dropIfExists('empleados');
        Schema::dropIfExists('asistencias_net');
        Schema::dropIfExists('asistencias_bet');
        parent::tearDown();
    }

    public function test_report_groups_first_and_last_login_by_day_and_system(): void
    {
        DB::table('empleados')->insert(['cedula' => '402-4046149-7', 'nombres' => 'Ana', 'apellidos' => 'Pérez']);
        DB::table('asistencias_bet')->insert([
            ['fecha' => '2026-10-10', 'cedula' => '402-4046149-7', 'agencia_id' => '0012', 'primer_login' => '2026-10-10 08:00:00', 'ultimo_login' => '2026-10-10 12:00:00'],
            ['fecha' => '2026-10-10', 'cedula' => '40240461497', 'agencia_id' => '0012', 'primer_login' => '2026-10-10 13:00:00', 'ultimo_login' => '2026-10-10 17:30:00'],
            ['fecha' => '2026-10-11', 'cedula' => '40240461497', 'agencia_id' => '0012', 'primer_login' => '2026-10-11 08:00:00', 'ultimo_login' => null],
            ['fecha' => '2026-10-10', 'cedula' => '00100000001', 'agencia_id' => '0013', 'primer_login' => '2026-10-10 08:00:00', 'ultimo_login' => '2026-10-10 18:00:00'],
        ]);
        DB::table('asistencias_net')->insert([
            ['identificacion' => '40240461497', 'entrada' => '2026-10-10 09:00:00', 'salida' => '2026-10-10 18:00:00'],
            ['identificacion' => '00100000001', 'entrada' => '2026-10-10 08:00:00', 'salida' => '2026-10-10 18:00:00'],
        ]);

        $this->assertStringContainsString('Primer login', file_get_contents(resource_path('views/recursos_humanos/ponche_por_cedula.blade.php')));
        $this->assertStringContainsString('id="totalHoras"', file_get_contents(resource_path('views/recursos_humanos/ponche_por_cedula.blade.php')));

        $this->getJson(route('recursos-humanos.ponche-por-cedula.list', [
            'cedula' => '402-4046149-7', 'sistema' => 'todos', 'fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-11',
        ]))->assertOk()
            ->assertJsonCount(3)
            ->assertJsonPath('0.sistema', 'Lotobet')
            ->assertJsonPath('0.nombre', 'Ana Pérez')
            ->assertJsonPath('0.primer_login', '2026-10-10 08:00:00')
            ->assertJsonPath('0.ultimo_login', '2026-10-10 17:30:00')
            ->assertJsonPath('0.total_horas', 9.5)
            ->assertJsonPath('1.sistema', 'Lotonet')
            ->assertJsonPath('1.nombre', 'Ana Pérez')
            ->assertJsonPath('1.total_horas', 9)
            ->assertJsonPath('2.total_horas', 0);
    }

    public function test_report_filters_system_and_rejects_invalid_range(): void
    {
        DB::table('asistencias_net')->insert(['identificacion' => '40240461497', 'entrada' => '2026-10-10 09:00:00', 'salida' => '2026-10-10 18:00:00']);

        $this->getJson(route('recursos-humanos.ponche-por-cedula.list', [
            'cedula' => '40240461497', 'sistema' => 'lotobet', 'fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-11',
        ]))->assertOk()->assertExactJson([]);

        $this->getJson(route('recursos-humanos.ponche-por-cedula.list', [
            'cedula' => '40240461497', 'sistema' => 'lotonet', 'fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-11',
        ]))->assertOk()->assertJsonPath('0.nombre', 'No identificado');

        $this->getJson(route('recursos-humanos.ponche-por-cedula.list', [
            'cedula' => '40240461497', 'sistema' => 'todos', 'fecha_inicio' => '2026-10-11', 'fecha_fin' => '2026-10-10',
        ]))->assertUnprocessable()->assertJsonValidationErrors('fecha_fin');
    }

    public function test_sunday_uses_payroll_exit_adjusted_to_last_sale_plus_five_minutes(): void
    {
        DB::table('asistencias_bet')->insert([
            'fecha' => '2026-10-11', 'agencia_id' => '0012', 'cedula' => '40240461497', 'usuario' => '40240461497',
            'primer_login' => '2026-10-11 08:00:00', 'ultimo_login' => '2026-10-11 14:00:00',
        ]);
        DB::table('ponche_por_cedula_ventas')->insert([
            ['terminal' => '12', 'usuario_venta' => '402-4046149-7', 'fecha_transaccion' => '2026-10-11 16:30:00', 'tipo' => 'Tradicional', 'total_apostado' => 100, 'estatus' => 'Aplicado'],
            ['terminal' => '99', 'usuario_venta' => '402-4046149-7', 'fecha_transaccion' => '2026-10-11 20:00:00', 'tipo' => 'Tradicional', 'total_apostado' => 100, 'estatus' => 'Aplicado'],
        ]);

        $this->getJson(route('recursos-humanos.ponche-por-cedula.list', [
            'cedula' => '40240461497', 'sistema' => 'lotobet', 'fecha_inicio' => '2026-10-11', 'fecha_fin' => '2026-10-11',
        ]))->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.primer_login', '2026-10-11 08:00:00')
            ->assertJsonPath('0.ultimo_login', '2026-10-11 14:00:00')
            ->assertJsonPath('0.salida_calculada', '2026-10-11 16:35:00')
            ->assertJsonMissingPath('0.estado')
            ->assertJsonPath('0.total_horas', 8.58);
    }

    public function test_sunday_keeps_last_login_when_sales_are_earlier_or_pending(): void
    {
        DB::table('asistencias_bet')->insert([
            ['fecha' => '2026-10-11', 'agencia_id' => '0013', 'cedula' => '40240461497', 'usuario' => '40240461497', 'primer_login' => '2026-10-11 08:00:00', 'ultimo_login' => '2026-10-11 18:00:00'],
            ['fecha' => '2026-10-12', 'agencia_id' => '0013', 'cedula' => '40240461497', 'usuario' => '40240461497', 'primer_login' => '2026-10-12 08:00:00', 'ultimo_login' => '2026-10-12 18:00:00'],
        ]);
        DB::table('ponche_por_cedula_ventas')->insert([
            ['terminal' => '13', 'usuario_venta' => '40240461497', 'fecha_transaccion' => '2026-10-11 17:00:00', 'tipo' => 'Tradicional', 'estatus' => 'Aplicado'],
            ['terminal' => '13', 'usuario_venta' => '40240461497', 'fecha_transaccion' => '2026-10-11 19:00:00', 'tipo' => 'Recargas', 'estatus' => 'Pendiente'],
            ['terminal' => '13', 'usuario_venta' => '00100000001', 'fecha_transaccion' => '2026-10-11 20:00:00', 'tipo' => 'Tradicional', 'estatus' => 'Aplicado'],
            ['terminal' => '13', 'usuario_venta' => '40240461497', 'fecha_transaccion' => '2026-10-12 20:00:00', 'tipo' => 'Tradicional', 'estatus' => 'Aplicado'],
        ]);

        $this->getJson(route('recursos-humanos.ponche-por-cedula.list', [
            'cedula' => '40240461497', 'sistema' => 'lotobet', 'fecha_inicio' => '2026-10-11', 'fecha_fin' => '2026-10-12',
        ]))->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.salida_calculada', '2026-10-11 18:00:00')
            ->assertJsonPath('0.total_horas', 10)
            ->assertJsonPath('1.salida_calculada', '2026-10-12 20:05:00')
            ->assertJsonPath('1.total_horas', 12.08);
    }

    public function test_exit_allows_five_minutes_after_the_last_sale_in_each_category(): void
    {
        DB::table('asistencias_bet')->insert([
            ['fecha' => '2026-10-10', 'agencia_id' => '12', 'cedula' => '40240461497', 'primer_login' => '2026-10-10 08:00:00', 'ultimo_login' => '2026-10-10 17:00:00'],
            ['fecha' => '2026-10-11', 'agencia_id' => '12', 'cedula' => '40240461497', 'primer_login' => '2026-10-11 08:00:00', 'ultimo_login' => '2026-10-11 17:00:00'],
            ['fecha' => '2026-10-12', 'agencia_id' => '12', 'cedula' => '40240461497', 'primer_login' => '2026-10-12 08:00:00', 'ultimo_login' => '2026-10-12 17:00:00'],
        ]);
        DB::table('ponche_por_cedula_ventas')->insert([
            ['terminal' => '12', 'usuario_venta' => '40240461497', 'fecha_transaccion' => '2026-10-10 16:58:00', 'tipo' => 'Tradicional', 'estatus' => 'Validos'],
            ['terminal' => '12', 'usuario_venta' => '40240461497', 'fecha_transaccion' => '2026-10-11 16:58:00', 'tipo' => 'No Tradicional', 'estatus' => 'Validos'],
            ['terminal' => '12', 'usuario_venta' => '40240461497', 'fecha_transaccion' => '2026-10-12 16:58:00', 'tipo' => 'Recargas', 'estatus' => 'Validos'],
        ]);

        $this->getJson(route('recursos-humanos.ponche-por-cedula.list', [
            'cedula' => '40240461497', 'sistema' => 'lotobet', 'fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-12',
        ]))->assertOk()
            ->assertJsonPath('0.salida_calculada', '2026-10-10 17:03:00')
            ->assertJsonPath('1.salida_calculada', '2026-10-11 17:03:00')
            ->assertJsonPath('2.salida_calculada', '2026-10-12 17:03:00');
    }

    public function test_report_has_its_own_permission_on_both_routes(): void
    {
        $permission = 'recursos_humanos.ponche_por_cedula.view';
        $item = collect(config('recursos_humanos'))->firstWhere('url', '/recursos-humanos/ponche-por-cedula');

        $this->assertSame($permission, app(ViewPermissionCatalog::class)->permission('recursos_humanos', $item));
        $this->assertContains('permission:'.$permission, Route::getRoutes()->getByName('recursos-humanos.ponche-por-cedula.index')->gatherMiddleware());
        $this->assertContains('permission:'.$permission, Route::getRoutes()->getByName('recursos-humanos.ponche-por-cedula.list')->gatherMiddleware());
        $this->assertContains('permission:mantenimiento.view', Route::getRoutes()->getByName('mantenimiento.ponche-por-cedula-ventas.index')->gatherMiddleware());
        $this->assertContains('permission:mantenimiento.view', Route::getRoutes()->getByName('mantenimiento.ponche-por-cedula-ventas.store')->gatherMiddleware());
        $this->assertContains('permission:mantenimiento.view', Route::getRoutes()->getByName('mantenimiento.ponche-por-cedula-ventas.lote')->gatherMiddleware());
        $this->assertContains('permission:mantenimiento.view', Route::getRoutes()->getByName('mantenimiento.ponche-por-cedula-ventas.pool')->gatherMiddleware());
        $this->assertContains('permission:mantenimiento.view', Route::getRoutes()->getByName('mantenimiento.ponche-por-cedula-ventas.progreso')->gatherMiddleware());
        $this->assertContains('permission:mantenimiento.view', Route::getRoutes()->getByName('mantenimiento.ponche-por-cedula-ventas.fragmento')->gatherMiddleware());
        $this->assertContains('permission:mantenimiento.view', Route::getRoutes()->getByName('mantenimiento.ponche-por-cedula-ventas.finalizar')->gatherMiddleware());
    }

    public function test_sales_upload_is_available_as_an_independent_maintenance_card(): void
    {
        $card = collect(config('module_hubs.mantenimiento.items'))->firstWhere('url', '/mantenimiento/ponche-por-cedula-ventas');

        $this->assertSame('mantenimiento.view', $card['permission']);
        $this->assertSame('Carga de ponche por cédula', $card['nombre']);
        $this->assertSame('mantenimiento.ponche-por-cedula-ventas.index', Route::getRoutes()->match(
            \Illuminate\Http\Request::create('/mantenimiento/ponche-por-cedula-ventas')
        )->getName());
        $this->assertStringContainsString('formCargarPoncheVentas', file_get_contents(resource_path('views/mantenimiento/ponche_por_cedula_ventas.blade.php')));
        $this->assertStringContainsString('formCargarPonchePool', file_get_contents(resource_path('views/mantenimiento/ponche_por_cedula_ventas.blade.php')));
        $this->assertStringContainsString('Ver archivos por día', file_get_contents(resource_path('views/mantenimiento/ponche_por_cedula_ventas.blade.php')));
        $this->assertStringContainsString("route('mantenimiento.ponche-por-cedula-ventas.fragmento', [], false)", file_get_contents(resource_path('views/mantenimiento/ponche_por_cedula_ventas.blade.php')));
        $this->assertStringContainsString("route('mantenimiento.ponche-por-cedula-ventas.finalizar', [], false)", file_get_contents(resource_path('views/mantenimiento/ponche_por_cedula_ventas.blade.php')));
        $this->assertStringContainsString("route('mantenimiento.ponche-por-cedula-ventas.progreso', ['token' => '__TOKEN__'], false)", file_get_contents(resource_path('views/mantenimiento/ponche_por_cedula_ventas.blade.php')));
        $this->assertStringNotContainsString('formCargarPoncheVentas', file_get_contents(resource_path('views/recursos_humanos/ponche_por_cedula.blade.php')));
    }

    public function test_pool_upload_groups_documents_by_filename_regardless_of_selection_order(): void
    {
        $archivos = [
            ...$this->archivosPool('2026-10-12', '16:30:00'),
            ...$this->archivosPool('2026-10-13', '17:30:00'),
        ];
        $archivos = [$archivos[5], $archivos[1], $archivos[3], $archivos[0], $archivos[4], $archivos[2]];
        $token = '11111111-1111-4111-8111-111111111111';

        $this->post(route('mantenimiento.ponche-por-cedula-ventas.pool'), ['archivos' => $archivos, 'progress_token' => $token])
            ->assertRedirect(route('mantenimiento.ponche-por-cedula-ventas.index'))
            ->assertSessionHas('success');

        $this->getJson(route('mantenimiento.ponche-por-cedula-ventas.progreso', ['token' => $token]))
            ->assertOk()
            ->assertJsonPath('estado', 'completo')
            ->assertJsonPath('completadas', 2)
            ->assertJsonPath('total', 2)
            ->assertJsonPath('pasos_completados', 6);

        $this->assertSame(3, DB::table('ponche_por_cedula_ventas')->whereDate('fecha_transaccion', '2026-10-12')->count());
        $this->assertSame(3, DB::table('ponche_por_cedula_ventas')->whereDate('fecha_transaccion', '2026-10-13')->count());
    }

    public function test_fragmented_upload_imports_each_day_before_receiving_the_next_day(): void
    {
        Storage::fake('local');
        foreach (['2026-10-12', '2026-10-13'] as $dia => $fecha) {
            $token = $dia === 0 ? '33333333-3333-4333-8333-333333333333' : '66666666-6666-4666-8666-666666666666';
            $archivos = $this->archivosPool($fecha, '16:30:00');
            $documentos = [];

            foreach ($archivos as $indice => $archivo) {
                $contenido = file_get_contents($archivo->getRealPath());
                $fragmentos = str_split($contenido, 1024 * 1024);
                $documentos[] = ['indice' => $indice, 'nombre' => $archivo->getClientOriginalName(), 'partes' => count($fragmentos), 'tamano' => strlen($contenido)];
                foreach ($fragmentos as $parte => $fragmento) {
                    $this->post(route('mantenimiento.ponche-por-cedula-ventas.fragmento'), [
                        'token' => $token,
                        'indice' => $indice,
                        'parte' => $parte,
                        'partes' => count($fragmentos),
                        'nombre' => $archivo->getClientOriginalName(),
                        'tamano' => strlen($contenido),
                        'fragmento' => UploadedFile::fake()->createWithContent('fragmento.bin', $fragmento),
                    ])->assertOk()->assertJsonPath('guardado', true);
                }
            }

            $this->postJson(route('mantenimiento.ponche-por-cedula-ventas.finalizar'), compact('token', 'documentos'))
                ->assertOk()->assertJsonPath('message', '1 fecha cargada correctamente para Ponche por Cédula.');
            $this->assertSame(3, DB::table('ponche_por_cedula_ventas')->whereDate('fecha_transaccion', $fecha)->count());
            $this->assertSame(($dia + 1) * 3, DB::table('ponche_por_cedula_ventas')->count());
        }
        $this->assertSame([], Storage::disk('local')->allFiles('ponche_carga_temporal'));
    }

    public function test_fragmented_upload_rejects_a_missing_fragment_without_importing(): void
    {
        Storage::fake('local');
        $token = '44444444-4444-4444-8444-444444444444';
        $archivos = $this->archivosPool('2026-10-12', '16:30:00');
        $documentos = [];

        foreach ($archivos as $indice => $archivo) {
            $contenido = file_get_contents($archivo->getRealPath());
            $partes = $indice === 2 ? 2 : 1;
            $documentos[] = ['indice' => $indice, 'nombre' => $archivo->getClientOriginalName(), 'partes' => $partes, 'tamano' => strlen($contenido)];
            $this->post(route('mantenimiento.ponche-por-cedula-ventas.fragmento'), [
                'token' => $token,
                'indice' => $indice,
                'parte' => 0,
                'partes' => $partes,
                'nombre' => $archivo->getClientOriginalName(),
                'tamano' => strlen($contenido),
                'fragmento' => UploadedFile::fake()->createWithContent('fragmento.bin', $indice === 2 ? substr($contenido, 0, 10) : $contenido),
            ])->assertOk();
        }

        $this->postJson(route('mantenimiento.ponche-por-cedula-ventas.finalizar'), compact('token', 'documentos'))
            ->assertUnprocessable()->assertJsonValidationErrors('documentos.2');
        $this->assertSame(0, DB::table('ponche_por_cedula_ventas')->count());
        $this->assertSame([], Storage::disk('local')->allFiles('ponche_carga_temporal'));
    }

    public function test_fragmented_upload_rejects_multiple_days_in_one_finalization(): void
    {
        $documentos = [];
        foreach (['2026-10-12', '2026-10-13'] as $fecha) {
            foreach (['tradicional', 'no_tradicional', 'recargas'] as $categoria) {
                $documentos[] = [
                    'indice' => count($documentos),
                    'nombre' => $fecha.'_'.$categoria.'.'.($categoria === 'recargas' ? 'xlsx' : 'csv'),
                    'partes' => 1,
                    'tamano' => 100,
                ];
            }
        }

        $this->postJson(route('mantenimiento.ponche-por-cedula-ventas.finalizar'), [
            'token' => '77777777-7777-4777-8777-777777777777',
            'documentos' => $documentos,
        ])->assertUnprocessable()->assertJsonValidationErrors('documentos');

        $this->assertSame(0, DB::table('ponche_por_cedula_ventas')->count());
    }

    public function test_fragments_reassemble_a_file_larger_than_the_php_file_limit(): void
    {
        Storage::fake('local');
        $token = '55555555-5555-4555-8555-555555555555';
        $contenido = str_repeat('x', 2 * 1024 * 1024 + 5);
        $fragmentos = str_split($contenido, 1024 * 1024);
        $documento = ['indice' => 0, 'nombre' => '2026-10-12_tradicional.csv', 'partes' => count($fragmentos), 'tamano' => strlen($contenido)];

        foreach ($fragmentos as $parte => $fragmento) {
            $this->post(route('mantenimiento.ponche-por-cedula-ventas.fragmento'), [
                'token' => $token,
                ...$documento,
                'parte' => $parte,
                'fragmento' => UploadedFile::fake()->createWithContent('fragmento.bin', $fragmento),
            ])->assertOk();
        }

        $archivos = app(PonchePorCedulaCargaTemporal::class)->archivos($token, [$documento]);
        $this->assertSame(strlen($contenido), filesize($archivos[0]->getRealPath()));
        $this->assertSame($contenido, file_get_contents($archivos[0]->getRealPath()));
        app(PonchePorCedulaCargaTemporal::class)->limpiar($token);
    }

    public function test_pool_upload_rejects_missing_category_and_duplicate_destination(): void
    {
        $archivos = $this->archivosPool('2026-10-12', '16:30:00');
        unset($archivos[2]);
        $this->post(route('mantenimiento.ponche-por-cedula-ventas.pool'), ['archivos' => array_values($archivos)])
            ->assertSessionHasErrors('archivos');

        $archivos = $this->archivosPool('2026-10-12', '16:30:00');
        $archivos[] = UploadedFile::fake()->createWithContent('2026-10-12_tradicional.csv', "Fecha,Agencia,Total Apostado,Estatus,Terminal,Usr. Venta\n2026-10-12 16:30:00,Agencia 12,1000,Validos,12,40240461497\n");
        $this->post(route('mantenimiento.ponche-por-cedula-ventas.pool'), ['archivos' => $archivos])
            ->assertSessionHasErrors('archivos.3');

        $this->assertSame(0, DB::table('ponche_por_cedula_ventas')->count());
    }

    public function test_pool_upload_rejects_invalid_names_and_rolls_back_wrong_document_dates(): void
    {
        $archivos = $this->archivosPool('2026-10-12', '16:30:00');
        $archivos[0] = UploadedFile::fake()->createWithContent('tradicional-12-10.csv', file_get_contents($archivos[0]->getRealPath()));
        $this->post(route('mantenimiento.ponche-por-cedula-ventas.pool'), ['archivos' => $archivos])
            ->assertSessionHasErrors('archivos.0');

        DB::table('ponche_por_cedula_ventas')->insert([
            'terminal' => '12', 'usuario_venta' => '40240461497', 'fecha_transaccion' => '2026-10-12 12:00:00',
            'tipo' => 'Tradicional', 'estatus' => 'Validos',
        ]);
        $archivos = [
            ...$this->archivosPool('2026-10-12', '16:30:00'),
            ...$this->archivosPool('2026-10-13', '17:30:00'),
        ];
        $archivos[4] = UploadedFile::fake()->createWithContent('2026-10-13_no_tradicional.csv', "Agencia,Estatus,Fecha,Id Terminal,Usr. Venta,Total Apostado\nAgencia 12,Validos,2026-10-14 13:00:00,12,40240461497,400\n");
        $token = '22222222-2222-4222-8222-222222222222';
        $this->post(route('mantenimiento.ponche-por-cedula-ventas.pool'), ['archivos' => $archivos, 'progress_token' => $token])
            ->assertSessionHasErrors('lotes.1.no_tradicional');

        $this->getJson(route('mantenimiento.ponche-por-cedula-ventas.progreso', ['token' => $token]))
            ->assertOk()
            ->assertJsonPath('estado', 'error')
            ->assertJsonPath('completadas', 0);

        $this->assertSame(1, DB::table('ponche_por_cedula_ventas')->count());
    }

    public function test_import_reports_each_category_in_the_order_it_is_processed(): void
    {
        $archivos = $this->archivosVentas('2026-10-12', '16:30:00');
        $categorias = [];

        app(NominaDomingoVentasImportService::class)->importarPonchePorCedula(
            $archivos['tradicional'],
            $archivos['no_tradicional'],
            $archivos['recargas'],
            Carbon::parse('2026-10-12'),
            function (string $categoria) use (&$categorias): void {
                $categorias[] = $categoria;
            }
        );

        $this->assertSame(['tradicional', 'no_tradicional', 'recargas'], $categorias);
        $this->assertSame(3, DB::table('ponche_por_cedula_ventas')->count());
    }

    public function test_batch_upload_assigns_three_documents_to_each_date(): void
    {
        $primero = $this->archivosVentas('2026-10-12', '16:30:00');
        $segundo = $this->archivosVentas('2026-10-13', '17:30:00');
        unset($primero['fecha'], $segundo['fecha']);

        $this->post(route('mantenimiento.ponche-por-cedula-ventas.lote'), [
            'lotes' => [
                ['fecha' => '2026-10-12', ...$primero],
                ['fecha' => '2026-10-13', ...$segundo],
            ],
        ])->assertRedirect(route('mantenimiento.ponche-por-cedula-ventas.index'))
            ->assertSessionHas('success');

        $this->assertSame(3, DB::table('ponche_por_cedula_ventas')->whereDate('fecha_transaccion', '2026-10-12')->count());
        $this->assertSame(3, DB::table('ponche_por_cedula_ventas')->whereDate('fecha_transaccion', '2026-10-13')->count());
    }

    public function test_batch_upload_rolls_back_all_dates_if_one_document_has_the_wrong_date(): void
    {
        DB::table('ponche_por_cedula_ventas')->insert([
            'terminal' => '12', 'usuario_venta' => '40240461497', 'fecha_transaccion' => '2026-10-12 12:00:00',
            'tipo' => 'Tradicional', 'estatus' => 'Validos',
        ]);
        $primero = $this->archivosVentas('2026-10-12', '16:30:00');
        $segundo = $this->archivosVentas('2026-10-13', '17:30:00');
        unset($primero['fecha'], $segundo['fecha']);
        $segundo['no_tradicional'] = UploadedFile::fake()->createWithContent('no_tradicional.csv', "Agencia,Estatus,Fecha,Id Terminal,Usr. Venta,Total Apostado\nAgencia 12,Validos,2026-10-14 13:00:00,12,40240461497,400\n");

        $this->post(route('mantenimiento.ponche-por-cedula-ventas.lote'), [
            'lotes' => [
                ['fecha' => '2026-10-12', ...$primero],
                ['fecha' => '2026-10-13', ...$segundo],
            ],
        ])->assertSessionHasErrors('lotes.1.no_tradicional');

        $this->assertSame(1, DB::table('ponche_por_cedula_ventas')->count());
        $this->assertTrue(DB::table('ponche_por_cedula_ventas')->where('fecha_transaccion', '2026-10-12 12:00:00')->exists());
    }

    public function test_batch_upload_rejects_repeated_dates_before_importing(): void
    {
        $primero = $this->archivosVentas('2026-10-12', '16:30:00');
        $segundo = $this->archivosVentas('2026-10-12', '17:30:00');
        unset($primero['fecha'], $segundo['fecha']);

        $this->post(route('mantenimiento.ponche-por-cedula-ventas.lote'), [
            'lotes' => [
                ['fecha' => '2026-10-12', ...$primero],
                ['fecha' => '2026-10-12', ...$segundo],
            ],
        ])->assertSessionHasErrors('lotes.0.fecha');

        $this->assertSame(0, DB::table('ponche_por_cedula_ventas')->count());
    }

    public function test_weekday_upload_is_exclusive_and_replaces_only_its_date(): void
    {
        DB::table('asistencias_bet')->insert([
            'fecha' => '2026-10-12', 'agencia_id' => '0012', 'cedula' => '40240461497',
            'primer_login' => '2026-10-12 08:00:00', 'ultimo_login' => '2026-10-12 14:00:00',
        ]);
        DB::table('nomina_domingo_ventas')->insert([
            'terminal' => '12', 'usuario_venta' => '40240461497', 'fecha_transaccion' => '2026-10-12 22:00:00',
            'tipo' => 'Tradicional', 'estatus' => 'Validos',
        ]);

        $this->post(route('mantenimiento.ponche-por-cedula-ventas.store'), $this->archivosVentas('2026-10-12', '16:30:00'))
            ->assertRedirect(route('mantenimiento.ponche-por-cedula-ventas.index'))
            ->assertSessionHas('success');

        $this->assertSame(1, DB::table('nomina_domingo_ventas')->count());
        $this->assertSame(3, DB::table('ponche_por_cedula_ventas')->count());
        $this->getJson(route('recursos-humanos.ponche-por-cedula.list', [
            'cedula' => '40240461497', 'sistema' => 'lotobet', 'fecha_inicio' => '2026-10-12', 'fecha_fin' => '2026-10-12',
        ]))->assertOk()->assertJsonPath('0.salida_calculada', '2026-10-12 16:35:00');

        $this->post(route('mantenimiento.ponche-por-cedula-ventas.store'), $this->archivosVentas('2026-10-12', '17:30:00'))
            ->assertRedirect(route('mantenimiento.ponche-por-cedula-ventas.index'));
        $this->assertSame(3, DB::table('ponche_por_cedula_ventas')->count());

        $archivos = $this->archivosVentas('2026-10-12', '18:30:00');
        $archivos['no_tradicional'] = UploadedFile::fake()->createWithContent('no_tradicional.csv', "Agencia,Estatus,Fecha,Id Terminal,Usr. Venta,Total Apostado\nAgencia 12,Validos,2026-10-13 13:00:00,12,40240461497,400\n");
        $this->post(route('mantenimiento.ponche-por-cedula-ventas.store'), $archivos)
            ->assertSessionHasErrors('no_tradicional');
        $this->assertSame(3, DB::table('ponche_por_cedula_ventas')->count());
        $this->assertTrue(DB::table('ponche_por_cedula_ventas')->where('fecha_transaccion', '2026-10-12 17:30:00')->exists());
    }

    public function test_upload_inserts_rows_after_a_full_sqlite_batch(): void
    {
        $archivos = $this->archivosVentas('2026-10-12', '16:30:00');
        $fila = "2026-10-12 16:30:00,Agencia 12,1000,Validos,12,40240461497\n";
        $archivos['tradicional'] = UploadedFile::fake()->createWithContent(
            'tradicional.csv',
            "Fecha,Agencia,Total Apostado,Estatus,Terminal,Usr. Venta\n".str_repeat($fila, 2501)
        );

        $this->post(route('mantenimiento.ponche-por-cedula-ventas.store'), $archivos)
            ->assertRedirect(route('mantenimiento.ponche-por-cedula-ventas.index'));

        $this->assertSame(2501, DB::table('ponche_por_cedula_ventas')->where('tipo', 'Tradicional')->count());
        $this->assertSame(2503, DB::table('ponche_por_cedula_ventas')->count());
    }

    /** @return array<string, mixed> */
    private function archivosVentas(string $fecha, string $hora): array
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A10', 'Estatus: Confirmada');
        foreach (['A' => 'Agencia', 'K' => 'Fecha', 'R' => 'Monto', 'S' => 'Usr. Venta'] as $columna => $encabezado) {
            $sheet->setCellValue($columna.'13', $encabezado);
        }
        $sheet->setCellValue('A17', '20267');
        $sheet->setCellValue('K17', $fecha.' 03:00:00 PM');
        $sheet->setCellValue('R17', 150);
        $sheet->setCellValue('S17', '40240461497');
        $path = tempnam(sys_get_temp_dir(), 'recargas_');
        (new Xlsx($spreadsheet))->save($path);
        $recargas = UploadedFile::fake()->createWithContent('recargas.xlsx', file_get_contents($path));
        unlink($path);
        $spreadsheet->disconnectWorksheets();

        return [
            'fecha' => $fecha,
            'tradicional' => UploadedFile::fake()->createWithContent('tradicional.csv', "Fecha,Agencia,Total Apostado,Estatus,Terminal,Usr. Venta\n$fecha $hora,Agencia 12,1000,Validos,12,40240461497\n"),
            'no_tradicional' => UploadedFile::fake()->createWithContent('no_tradicional.csv', "Agencia,Estatus,Fecha,Id Terminal,Usr. Venta,Total Apostado\nAgencia 12,Validos,$fecha 13:00:00,12,40240461497,400\n"),
            'recargas' => $recargas,
        ];
    }

    /** @return array<int, UploadedFile> */
    private function archivosPool(string $fecha, string $hora): array
    {
        $archivos = $this->archivosVentas($fecha, $hora);

        return collect(['tradicional', 'no_tradicional', 'recargas'])
            ->map(fn (string $categoria): UploadedFile => UploadedFile::fake()->createWithContent(
                $fecha.'_'.$categoria.'.'.($categoria === 'recargas' ? 'xlsx' : 'csv'),
                file_get_contents($archivos[$categoria]->getRealPath())
            ))->all();
    }

    public function test_migration_creates_permission_and_grants_it_to_human_resources(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('rh', 'web');
        Role::findOrCreate('admin', 'web');
        $migration = require database_path('migrations/2026_10_14_102322_create_ponche_por_cedula_permission.php');
        $migration->up();
        $migration->up();

        $this->assertSame(1, Permission::query()->where('name', 'recursos_humanos.ponche_por_cedula.view')->count());
        $this->assertTrue(Role::findByName('rh', 'web')->hasPermissionTo('recursos_humanos.ponche_por_cedula.view'));
        $this->assertTrue(Role::findByName('admin', 'web')->hasPermissionTo('recursos_humanos.ponche_por_cedula.view'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
}
