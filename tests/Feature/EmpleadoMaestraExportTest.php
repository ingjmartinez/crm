<?php

namespace Tests\Feature;

use App\Exports\EmpleadoMaestraExport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class EmpleadoMaestraExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        Schema::dropIfExists('empleados');
        Schema::create('empleados', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('companyid');
            $table->unsignedInteger('empleadoid');
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('cedula')->nullable();
            $table->string('ciudad')->nullable();
            $table->decimal('salariomensual', 12, 2)->nullable();
            $table->date('fechaingreso')->nullable();
            $table->date('fechasalida')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_excel_download_contains_every_employee_instead_of_only_the_visible_page(): void
    {
        foreach (range(1, 12) as $index) {
            $this->insertEmployee([
                'empleadoid' => 1000 + $index,
                'cedula' => str_pad((string) $index, 11, '0', STR_PAD_LEFT),
            ]);
        }

        Carbon::setTestNow('2026-08-10 10:11:12');
        Excel::fake();

        $this->get(route('empleados.export'))->assertOk();

        Excel::assertDownloaded(
            'maestra_empleados_2026-08-10_101112.xlsx',
            fn (EmpleadoMaestraExport $export): bool => $export->query()->count() === 12
        );
    }

    public function test_excel_download_respects_company_and_datatable_search_filters(): void
    {
        $this->insertEmployee(['nombres' => 'Ana', 'empleadoid' => 1001]);
        $this->insertEmployee(['nombres' => 'Luis', 'empleadoid' => 1002, 'cedula' => '00111111112']);
        $this->insertEmployee([
            'companyid' => 169,
            'nombres' => 'Ana',
            'empleadoid' => 2001,
            'cedula' => '00111111113',
        ]);

        Excel::fake();

        $this->get(route('empleados.export', [
            'empresa' => '168',
            'buscar' => 'Ana',
        ]))->assertOk();

        Excel::assertDownloaded(
            'maestra_empleados_'.now()->format('Y-m-d_His').'.xlsx',
            function (EmpleadoMaestraExport $export): bool {
                $employees = $export->query()->get();

                return $employees->count() === 1
                    && $employees->first()->nombres === 'Ana'
                    && (string) $employees->first()->companyid === '168';
            }
        );
    }

    public function test_employee_view_uses_the_complete_server_side_excel_download(): void
    {
        $this->get('/empleados')
            ->assertOk()
            ->assertSee('Excel completo')
            ->assertSee('empleadosExportUrl', false)
            ->assertSee('empleadosTable.search().trim()', false);
    }

    /** @param array<string, mixed> $overrides */
    private function insertEmployee(array $overrides = []): void
    {
        DB::table('empleados')->insert(array_merge([
            'companyid' => 168,
            'empleadoid' => 1001,
            'nombres' => 'Empleado',
            'apellidos' => 'Prueba',
            'cedula' => '00111111111',
            'ciudad' => 'Santo Domingo',
            'salariomensual' => 25000,
            'fechaingreso' => '2025-01-01',
            'fechasalida' => null,
        ], $overrides));
    }
}
