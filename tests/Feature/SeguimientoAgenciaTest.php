<?php

namespace Tests\Feature;

use App\Http\Controllers\Gerencia\SeguimientoAgenciaController;
use App\Http\Requests\Gerencia\ConsultarSeguimientoAgenciaRequest;
use App\Services\Gerencia\SeguimientoAgenciaService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class SeguimientoAgenciaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('vt_usuarios_bet');
        Schema::dropIfExists('agencias');

        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('agencia')->nullable();
            $table->string('terminal');
            $table->string('nombre_agencia')->nullable();
            $table->string('sistema')->nullable();
            $table->string('empresa')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('ruta')->nullable();
            $table->string('coordinador')->nullable();
            $table->boolean('estatus')->default(true);
            $table->timestamps();
        });

        Schema::create('vt_usuarios_bet', function (Blueprint $table): void {
            $table->id('vt_usuario_id');
            $table->string('agencia_id');
            $table->string('tipo');
            $table->decimal('monto', 14, 2);
            $table->date('fecha');
        });
    }

    public function test_report_is_published_under_management_module(): void
    {
        $item = collect(config('module_hubs.gerencia.items'))->firstWhere('nombre', 'Seguimiento de Agencia');

        $this->assertNotNull($item);
        $this->assertSame('/gerencia/seguimiento-agencia', $item['url']);
        $this->assertTrue(Route::has('gerencia.seguimiento-agencia'));
        $this->assertTrue(Route::has('gerencia.seguimiento-agencia.detalle'));
        $this->assertTrue(Route::has('gerencia.seguimiento-agencia.export.excel'));
        $this->assertTrue(Route::has('gerencia.seguimiento-agencia.export.pdf'));
        $this->assertTrue(view()->exists('gerencia.seguimiento-agencia'));
    }

    public function test_report_calculates_sales_targets_hierarchy_and_daily_compliance(): void
    {
        DB::table('agencias')->insert([
            'terminal' => '00123',
            'agencia' => 'A-123',
            'nombre_agencia' => 'Agencia Centro',
            'sistema' => 'Lotobet',
            'empresa' => 'Empresa Uno',
            'ciudad' => 'Santo Domingo',
            'coordinador' => 'Ana Pérez',
            'ruta' => 'Ruta 1',
            'estatus' => 1,
        ]);

        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '123', 'tipo' => 'Tradicional', 'monto' => 8000, 'fecha' => '2026-07-01'],
            ['agencia_id' => '123', 'tipo' => 'Tradicional', 'monto' => 6000, 'fecha' => '2026-07-02'],
            ['agencia_id' => '123', 'tipo' => 'No Tradicional', 'monto' => 3000, 'fecha' => '2026-07-01'],
            ['agencia_id' => '123', 'tipo' => 'Recarga', 'monto' => 1400, 'fecha' => '2026-07-01'],
        ]);

        $reporte = app(SeguimientoAgenciaService::class)->generar(
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-02'),
            ['sistema' => 'lotobet'],
            ['tradicional' => 7000, 'no_tradicional' => 1500, 'recargas' => 700]
        );

        $tradicional = $reporte['filas']->firstWhere('producto_key', 'tradicional');

        $this->assertCount(3, $reporte['filas']);
        $this->assertSame('Santo Domingo', $tradicional['ciudad']);
        $this->assertSame('Ana Pérez', $tradicional['coordinador']);
        $this->assertSame(14000.0, $tradicional['meta_acumulada']);
        $this->assertSame(14000.0, $tradicional['venta']);
        $this->assertSame(1, $tradicional['dias_cumplidos']);
        $this->assertSame(1, $tradicional['dias_no_cumplidos']);
        $this->assertSame('Cumple', $tradicional['estado']);
        $this->assertSame(18400.0, $reporte['resumen']['meta_acumulada']);
        $this->assertSame(18400.0, $reporte['resumen']['venta']);
        $this->assertSame(100.0, $reporte['resumen']['cumplimiento']);
    }

    public function test_view_explains_that_today_is_excluded(): void
    {
        $source = file_get_contents(resource_path('views/gerencia/seguimiento-agencia.blade.php'));

        $this->assertStringContainsString('No se incluye el día de hoy', $source);
        $this->assertStringContainsString('Empresa → Ciudad → Coordinador → Ruta → Agencia → Producto', $source);
        $this->assertStringContainsString('<th>Ver</th>', $source);
        $this->assertStringContainsString('modalDetalleSeguimiento', $source);
        $this->assertStringContainsString('<div class="row g-4 mb-5">', $source);
        $this->assertStringContainsString('name="buscar"', $source);
        $this->assertStringContainsString('Nombre de agencia o terminal', $source);
    }

    public function test_daily_modal_detail_marks_compliant_and_non_compliant_days_by_product(): void
    {
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '123', 'tipo' => 'Tradicional', 'monto' => 8000, 'fecha' => '2026-07-01'],
            ['agencia_id' => '123', 'tipo' => 'Tradicional', 'monto' => 6000, 'fecha' => '2026-07-02'],
            ['agencia_id' => '123', 'tipo' => 'Recarga', 'monto' => 900, 'fecha' => '2026-07-01'],
        ]);

        $detalle = app(SeguimientoAgenciaService::class)->detalleDiario(
            '00123',
            'lotobet',
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-02'),
            ['tradicional' => 7000, 'no_tradicional' => 1500, 'recargas' => 700]
        );
        $tradicional = collect($detalle['productos'])->firstWhere('key', 'tradicional');
        $noTradicional = collect($detalle['productos'])->firstWhere('key', 'no_tradicional');

        $this->assertSame(['01/07', '02/07'], $detalle['labels']);
        $this->assertSame([8000.0, 6000.0], $tradicional['ventas']);
        $this->assertSame(1, $tradicional['dias_cumplidos']);
        $this->assertSame(1, $tradicional['dias_no_cumplidos']);
        $this->assertSame(0, $noTradicional['dias_cumplidos']);
        $this->assertSame(2, $noTradicional['dias_no_cumplidos']);
        $this->assertFalse($tradicional['dias'][1]['cumple']);
    }

    public function test_opening_report_does_not_load_sales_automatically(): void
    {
        $service = Mockery::mock(SeguimientoAgenciaService::class);
        $service->shouldNotReceive('generar');
        $service->shouldReceive('opcionesFiltros')->once()->andReturn([
            'empresas' => collect(), 'ciudades' => collect(), 'coordinadores' => collect(),
            'rutas' => collect(), 'agencias' => collect(),
        ]);

        $request = ConsultarSeguimientoAgenciaRequest::create('/gerencia/seguimiento-agencia', 'GET');
        $view = (new SeguimientoAgenciaController($service))->index($request);

        $this->assertFalse($view->getData()['debeConsultar']);
        $this->assertArrayNotHasKey('filas', $view->getData());
    }

    public function test_selected_previous_month_is_loaded_completely_after_user_requests_it(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');

        $service = Mockery::mock(SeguimientoAgenciaService::class);
        $service->shouldReceive('generar')
            ->once()
            ->withArgs(fn (Carbon $inicio, Carbon $fin): bool => $inicio->toDateString() === '2026-06-01' && $fin->toDateString() === '2026-06-30')
            ->andReturn(['filas' => collect()]);
        $service->shouldReceive('opcionesFiltros')->once()->andReturn([
            'empresas' => collect(), 'ciudades' => collect(), 'coordinadores' => collect(),
            'rutas' => collect(), 'agencias' => collect(),
        ]);

        $request = ConsultarSeguimientoAgenciaRequest::create('/gerencia/seguimiento-agencia', 'GET', [
            'consultar' => '1',
            'mes' => '2026-06',
        ]);
        $view = (new SeguimientoAgenciaController($service))->index($request);

        $this->assertTrue($view->getData()['debeConsultar']);
        $this->assertSame('2026-06', $view->getData()['mesSeleccionado']);

        Carbon::setTestNow();
    }

    public function test_filters_agencies_by_partial_name_or_terminal_digits(): void
    {
        DB::table('agencias')->insert([
            [
                'terminal' => '0012345',
                'agencia' => 'A-12345',
                'nombre_agencia' => 'Agencia Centro Colonial',
                'sistema' => 'Lotobet',
                'estatus' => 1,
            ],
            [
                'terminal' => '0098765',
                'agencia' => 'A-98765',
                'nombre_agencia' => 'Agencia Norte',
                'sistema' => 'Lotobet',
                'estatus' => 1,
            ],
        ]);
        $servicio = app(SeguimientoAgenciaService::class);
        $metas = ['tradicional' => 7000, 'no_tradicional' => 1500, 'recargas' => 700];

        $porNombre = $servicio->generar(
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-02'),
            ['sistema' => 'lotobet', 'buscar' => 'Centro'],
            $metas
        );
        $porTerminal = $servicio->generar(
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-02'),
            ['sistema' => 'lotobet', 'buscar' => '987'],
            $metas
        );

        $this->assertSame(1, $porNombre['resumen']['agencias']);
        $this->assertSame(['0012345'], $porNombre['filas']->pluck('terminal')->unique()->values()->all());
        $this->assertSame(1, $porTerminal['resumen']['agencias']);
        $this->assertSame(['0098765'], $porTerminal['filas']->pluck('terminal')->unique()->values()->all());
    }
}
