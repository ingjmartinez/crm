<?php

namespace Tests\Feature;

use App\Http\Controllers\EmpleadoController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmpleadoVentasBetSinMaestraTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        Cache::forget('empleados.ventas_bet_sin_empleado');

        Schema::dropIfExists('vt_usuarios_bet');
        Schema::dropIfExists('empleados');

        Schema::create('empleados', function (Blueprint $table): void {
            $table->id();
            $table->string('cedula')->nullable();
        });

        Schema::create('vt_usuarios_bet', function (Blueprint $table): void {
            $table->id('vt_usuario_id');
            $table->string('agencia_id')->nullable();
            $table->string('cedula')->nullable();
            $table->decimal('monto', 12, 2)->nullable();
            $table->date('fecha')->nullable();
        });
    }

    public function test_it_lists_only_sales_cedulas_missing_from_the_employee_master(): void
    {
        DB::table('empleados')->insert(['cedula' => '001-1111111-1']);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '10', 'cedula' => '00111111111', 'monto' => 500, 'fecha' => '2026-09-18'],
            ['agencia_id' => '20', 'cedula' => '402-2696451-4', 'monto' => 100, 'fecha' => '2026-09-19'],
            ['agencia_id' => '21', 'cedula' => '40226964514', 'monto' => 50, 'fecha' => '2026-09-20'],
            ['agencia_id' => '30', 'cedula' => '123', 'monto' => 75, 'fecha' => '2026-09-20'],
        ]);

        $response = app(EmpleadoController::class)->listVentasBetSinMaestra(
            Request::create('/empleados/ventas-bet-sin-maestra/list', 'GET', ['refresh' => '1'])
        );

        $payload = $response->getData(true);

        $this->assertSame(1, $payload['total']);
        $this->assertSame('40226964514', $payload['data'][0]['cedula']);
        $this->assertSame('2026-09-20', $payload['meta']['fecha_ventas']);
    }

    public function test_employee_page_links_to_the_separate_missing_sales_page(): void
    {
        $this->get('/empleados')
            ->assertOk()
            ->assertSee('Revisar cédulas de ventas')
            ->assertSee(route('empleados.ventas-bet-sin-maestra'), false);
    }

    public function test_missing_sales_page_contains_the_table_and_api_action(): void
    {
        $this->get(route('empleados.ventas-bet-sin-maestra'))
            ->assertOk()
            ->assertSee('Cédulas de ventas fuera de la maestra')
            ->assertSee('tablaPendientes', false)
            ->assertSee('Consultar API')
            ->assertSee("for (const empresa of ['168', '169'])", false);
    }
}
