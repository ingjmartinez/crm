<?php

namespace Tests\Feature;

use App\Http\Controllers\IncentivoConfiguracionController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class IncentivoAdministrativoSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('incentivo_administrativos', function (Blueprint $table): void {
            $table->id();
            $table->string('grupo');
            $table->string('nombre');
            $table->string('cedula')->nullable();
            $table->string('empresa');
            $table->decimal('pct_total', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('empleados', function (Blueprint $table): void {
            $table->id();
            $table->string('cedula')->nullable();
            $table->string('empleadoid')->nullable();
        });

        DB::table('incentivo_administrativos')->insert([
            [
                'grupo' => 'Administración',
                'nombre' => 'Ana Pérez',
                'cedula' => '00112345678',
                'empresa' => 'Consorcio Joselito',
                'pct_total' => 10,
            ],
            [
                'grupo' => 'Administración',
                'nombre' => 'Carlos Gómez',
                'cedula' => '40298765432',
                'empresa' => 'Consorcio Joselito',
                'pct_total' => 10,
            ],
        ]);
    }

    public function test_search_finds_an_administrative_incentive_by_formatted_cedula(): void
    {
        $resultados = $this->search('001-1234567-8');

        $this->assertSame(['Ana Pérez'], $resultados);
    }

    public function test_search_continues_finding_an_administrative_incentive_by_name(): void
    {
        $resultados = $this->search('Carlos');

        $this->assertSame(['Carlos Gómez'], $resultados);
    }

    public function test_search_field_explains_both_supported_values(): void
    {
        $view = file_get_contents(resource_path('views/incentivos/incentivo_administrativo/index.blade.php'));

        $this->assertStringContainsString('placeholder="Buscar por nombre o cédula"', $view);
    }

    /** @return array<int, string> */
    private function search(string $term): array
    {
        $controller = app(IncentivoConfiguracionController::class);
        $method = new ReflectionMethod($controller, 'queryIncentivosAdministrativos');
        $request = Request::create('/incentivos/incentivo-administrativo', 'GET', [
            'buscar_nombre' => $term,
        ]);

        return $method->invoke($controller, $request)
            ->orderBy('incentivo_administrativos.nombre')
            ->pluck('incentivo_administrativos.nombre')
            ->all();
    }
}
