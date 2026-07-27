<?php

namespace Tests\Feature;

use App\Http\Controllers\GestionAgenciasReporteController;
use App\Models\Agencia;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GestionAgenciasReporteCiudadFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('agencias');
        Schema::create('agencias', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('agencia')->nullable();
            $table->string('terminal')->nullable();
            $table->string('empresa')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('ruta')->nullable();
            $table->timestamps();
        });
    }

    public function test_city_filter_is_read_from_the_request_and_applied_to_agencies(): void
    {
        Agencia::query()->insert([
            [
                'agencia' => 'Agencia Capital',
                'terminal' => '001',
                'empresa' => 'Empresa Uno',
                'ciudad' => 'Santo Domingo',
                'ruta' => 'Ruta Sur',
            ],
            [
                'agencia' => 'Agencia Cibao',
                'terminal' => '002',
                'empresa' => 'Empresa Uno',
                'ciudad' => 'Santiago',
                'ruta' => 'Ruta Norte',
            ],
        ]);

        $controller = app(GestionAgenciasReporteController::class);
        $readFilters = fn (Request $request): array => $this->filtrosAgencia($request);
        $applyFilters = fn ($query, array $filters) => $this->aplicarFiltrosAgenciaTablaAgencias($query, $filters);
        $readFilters = $readFilters->bindTo($controller, $controller);
        $applyFilters = $applyFilters->bindTo($controller, $controller);
        $filters = $readFilters(Request::create('/reportes-gestion-agencias/data', 'GET', [
            'empresa_filter' => 'Empresa Uno',
            'ciudad_filter' => 'Santiago',
            'ruta_filter' => '',
        ]));

        $agencies = $applyFilters(Agencia::query(), $filters)->pluck('agencia');

        $this->assertSame('Santiago', $filters['ciudad']);
        $this->assertSame(['Agencia Cibao'], $agencies->all());
    }

    public function test_city_selector_is_between_company_and_route_and_is_sent_to_ajax_and_pdf(): void
    {
        $view = file_get_contents(resource_path('views/reportes/gestion-agencias.blade.php'));
        $pdf = file_get_contents(resource_path('views/reportes/gestion-agencias-pdf.blade.php'));

        $companyPosition = strpos($view, 'id="filtroEmpresaGestion"');
        $cityPosition = strpos($view, 'id="filtroCiudadGestion"');
        $routePosition = strpos($view, 'id="filtroRutaGestion"');

        $this->assertNotFalse($companyPosition);
        $this->assertNotFalse($cityPosition);
        $this->assertNotFalse($routePosition);
        $this->assertLessThan($cityPosition, $companyPosition);
        $this->assertLessThan($routePosition, $cityPosition);
        $this->assertStringContainsString("ciudad_filter: filtroCiudadGestion?.value || ''", $view);
        $this->assertStringContainsString("Ciudad: {{ \$filtrosActivos['ciudad'] ?? 'Todas' }}", $pdf);
    }

    public function test_detailed_pdf_summary_groups_card_metrics_without_repeating_agencies(): void
    {
        $controller = app(GestionAgenciasReporteController::class);
        $buildReport = fn (
            string $grouping,
            $sales,
            $withoutSales,
            array $statusDetail,
            array $salesPerHour
        ): array => $this->construirDatosInformePdf(
            $grouping,
            $sales,
            $withoutSales,
            $statusDetail,
            $salesPerHour
        );
        $buildReport = $buildReport->bindTo($controller, $controller);

        $report = $buildReport(
            'ciudad',
            collect([
                [
                    'clave' => '1',
                    'terminal' => '001',
                    'agencia' => 'Agencia Capital',
                    'empresa' => 'Empresa Uno',
                    'ciudad' => 'Santo Domingo',
                    'ruta' => 'Ruta Sur',
                    'coordinador' => 'Ana Pérez',
                    'total' => 1500,
                ],
                [
                    'clave' => '2',
                    'terminal' => '002',
                    'agencia' => 'Agencia Oriental',
                    'empresa' => 'Empresa Uno',
                    'ciudad' => 'Santo Domingo',
                    'ruta' => 'Ruta Este',
                    'coordinador' => 'Luis Pérez',
                    'total' => 500,
                ],
            ]),
            collect([
                [
                    'terminal' => '003',
                    'nombre_agencia' => 'Agencia Cerrada',
                    'empresa' => 'Empresa Uno',
                    'ciudad' => 'Santiago',
                    'ruta' => 'Ruta Norte',
                    'coordinador' => 'María Díaz',
                ],
            ]),
            [
                'Al dia' => [
                    ['clave' => '1', 'fecha' => '27-07-2026 10:00:00 AM'],
                ],
                'Aviso' => [
                    ['clave' => '2', 'fecha' => '27-07-2026 09:30:00 AM'],
                ],
                'En Alerta' => [],
                'Requiere llamada' => [],
            ],
            [
                'Santo Domingo' => 1000,
                'Santiago' => 0,
            ]
        );

        $capital = $report['resumen']->firstWhere('grupo', 'Santo Domingo');
        $cibao = $report['resumen']->firstWhere('grupo', 'Santiago');

        $this->assertSame(2, $capital['total_agencias']);
        $this->assertSame(2, $capital['con_ventas']);
        $this->assertSame(0, $capital['sin_ventas']);
        $this->assertSame(2000.0, $capital['total_vendido']);
        $this->assertSame(1000.0, $capital['venta_por_hora']);
        $this->assertSame(100.0, $capital['cumplimiento_porcentaje']);
        $this->assertSame('verde', $capital['cumplimiento_color']);
        $this->assertSame(1, $capital['al_dia']);
        $this->assertSame(1, $capital['aviso']);
        $this->assertSame(1, $cibao['total_agencias']);
        $this->assertSame(1, $cibao['sin_ventas']);
        $this->assertSame(0.0, $cibao['cumplimiento_porcentaje']);
        $this->assertSame('rojo', $cibao['cumplimiento_color']);
        $this->assertSame(3, $report['detalle_total']);
    }

    public function test_pdf_button_allows_company_city_or_route_grouping_and_view_contains_detailed_sections(): void
    {
        $view = file_get_contents(resource_path('views/reportes/gestion-agencias.blade.php'));
        $pdf = file_get_contents(resource_path('views/reportes/gestion-agencias-pdf.blade.php'));

        $this->assertStringContainsString('Informe PDF detallado', $view);
        $this->assertStringContainsString('value="empresa"', $view);
        $this->assertStringContainsString('value="ciudad"', $view);
        $this->assertStringContainsString('value="ruta"', $view);
        $this->assertStringContainsString("params.set('agrupacion', result.value)", $view);
        $this->assertStringContainsString('Detalle de los indicadores', $pdf);
        $this->assertStringContainsString('Distribucion de estatus con los filtros aplicados', $pdf);
        $this->assertStringContainsString('Consolidado por', $pdf);
        $this->assertStringContainsString('Comparativo de ventas por', $pdf);
        $this->assertStringContainsString('Mini tabla de detalle de agencias', $pdf);
    }

    public function test_compliance_color_scale_uses_the_confirmed_boundaries(): void
    {
        $controller = app(GestionAgenciasReporteController::class);
        $color = fn (float $percentage): string => $this->colorCumplimiento($percentage);
        $color = $color->bindTo($controller, $controller);

        $this->assertSame('verde', $color(90.01));
        $this->assertSame('naranja', $color(90));
        $this->assertSame('naranja', $color(80));
        $this->assertSame('amarillo', $color(79.99));
        $this->assertSame('amarillo', $color(75));
        $this->assertSame('rojo', $color(74.99));
        $this->assertSame('rojo', $color(0));
    }
}
