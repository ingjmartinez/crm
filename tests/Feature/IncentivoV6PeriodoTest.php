<?php

namespace Tests\Feature;

use App\Http\Middleware\ForcePasswordChange;
use App\Models\IncentivoPeriodoDetalle;
use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IncentivoV6PeriodoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('incentivo_periodos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('sistema', 20);
            $table->string('modo_calculo', 30);
            $table->string('tipo_pago_defecto', 30);
            $table->unsignedSmallInteger('min_dias_venta');
            $table->json('rangos_pago_por_tipo')->nullable();
            $table->json('terminales_excluidas')->nullable();
            $table->json('resumen')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['anio', 'mes']);
        });
        Schema::create('incentivo_periodo_detalles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('incentivo_periodo_id');
            $table->string('cedula', 30);
            $table->string('empleadoid', 50)->nullable();
            $table->string('nombre', 200);
            $table->string('empresa', 100);
            $table->string('ultima_terminal', 50)->nullable();
            $table->string('ultima_agencia_nombre', 200)->nullable();
            $table->bigInteger('ventas_ultimo_mes');
            $table->bigInteger('ventas_mes_actual');
            $table->unsignedSmallInteger('dias_ventas');
            $table->decimal('horas_total', 10, 2);
            $table->bigInteger('incentivo_generado');
            $table->bigInteger('monto_pagado');
            $table->bigInteger('monto_no_pagado');
            $table->string('estado', 30);
            $table->json('motivos')->nullable();
            $table->json('tipos_pago_detalle')->nullable();
            $table->timestamps();
            $table->unique(['incentivo_periodo_id', 'cedula', 'empresa']);
        });
    }

    public function test_period_stores_paid_unpaid_and_partial_details(): void
    {
        $response = $this->actingAs($this->userWithId(7))
            ->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->postJson(
                route('incentivos.reporte-nuevo-incentivo-v6.periodo.guardar'),
                $this->payload([
                    $this->detail('40211111111', 1000, 1000),
                    $this->detail('40222222222', 2000, 0, ['faltante']),
                    $this->detail('40233333333', 3000, 1500, ['agencia_excluida']),
                ])
            );

        $response
            ->assertOk()
            ->assertJsonPath('actualizado', false)
            ->assertJsonPath('periodo.revision', 1)
            ->assertJsonPath('resumen.registros', 3)
            ->assertJsonPath('resumen.pagados', 1)
            ->assertJsonPath('resumen.pagados_parciales', 1)
            ->assertJsonPath('resumen.no_pagados', 1)
            ->assertJsonPath('resumen.incentivo_generado', 6000)
            ->assertJsonPath('resumen.monto_pagado', 2500)
            ->assertJsonPath('resumen.monto_no_pagado', 3500);

        $this->assertDatabaseHas('incentivo_periodos', [
            'anio' => 2026,
            'mes' => 7,
            'revision' => 1,
            'created_by' => 7,
            'updated_by' => 7,
        ]);
        $this->assertDatabaseHas('incentivo_periodo_detalles', [
            'cedula' => '40222222222',
            'incentivo_generado' => 2000,
            'monto_pagado' => 0,
            'monto_no_pagado' => 2000,
            'estado' => 'no_pagado',
        ]);
        $this->assertDatabaseHas('incentivo_periodo_detalles', [
            'cedula' => '40233333333',
            'estado' => 'pagado_parcial',
        ]);
    }

    public function test_saving_the_same_month_updates_the_period_and_replaces_old_details(): void
    {
        $route = route('incentivos.reporte-nuevo-incentivo-v6.periodo.guardar');

        $this->actingAs($this->userWithId(7))
            ->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->postJson($route, $this->payload([
                $this->detail('40211111111', 1000, 1000),
                $this->detail('40222222222', 2000, 0, ['faltante']),
            ]))
            ->assertOk();

        $response = $this->actingAs($this->userWithId(9))
            ->postJson($route, $this->payload([
                $this->detail('40299999999', 4500, 4500),
            ]));

        $response
            ->assertOk()
            ->assertJsonPath('actualizado', true)
            ->assertJsonPath('periodo.revision', 2)
            ->assertJsonPath('resumen.registros', 1)
            ->assertJsonPath('resumen.monto_pagado', 4500);

        $this->assertDatabaseCount('incentivo_periodos', 1);
        $this->assertDatabaseCount('incentivo_periodo_detalles', 1);
        $this->assertDatabaseMissing('incentivo_periodo_detalles', ['cedula' => '40211111111']);
        $this->assertDatabaseHas('incentivo_periodo_detalles', [
            'cedula' => '40299999999',
            'monto_pagado' => 4500,
        ]);
        $this->assertDatabaseHas('incentivo_periodos', [
            'anio' => 2026,
            'mes' => 7,
            'revision' => 2,
            'created_by' => 7,
            'updated_by' => 9,
        ]);
    }

    public function test_period_cannot_span_multiple_months(): void
    {
        $payload = $this->payload([$this->detail('40211111111', 1000, 1000)]);
        $payload['fecha_fin'] = '2026-08-01';

        $this->actingAs($this->userWithId(7))
            ->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->postJson(route('incentivos.reporte-nuevo-incentivo-v6.periodo.guardar'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fecha_fin');

        $this->assertDatabaseCount('incentivo_periodos', 0);
    }

    public function test_period_requires_faltantes_and_desvinculados_to_be_applied(): void
    {
        $payload = $this->payload([$this->detail('40211111111', 1000, 1000)]);
        $payload['faltantes_aplicados'] = false;
        $payload['desvinculados_aplicados'] = false;

        $this->actingAs($this->userWithId(7))
            ->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->postJson(route('incentivos.reporte-nuevo-incentivo-v6.periodo.guardar'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['faltantes_aplicados', 'desvinculados_aplicados']);

        $this->assertDatabaseCount('incentivo_periodos', 0);
    }

    public function test_same_reason_can_be_saved_for_multiple_people(): void
    {
        $response = $this->actingAs($this->userWithId(7))
            ->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->postJson(route('incentivos.reporte-nuevo-incentivo-v6.periodo.guardar'), $this->payload([
                $this->detail('40211111111', 1000, 0, ['faltante']),
                $this->detail('40222222222', 2000, 0, ['faltante']),
            ]));

        $response
            ->assertOk()
            ->assertJsonPath('resumen.registros', 2)
            ->assertJsonPath('resumen.no_pagados', 2);

        $this->assertDatabaseCount('incentivo_periodo_detalles', 2);
    }

    public function test_person_below_goal_is_saved_as_not_qualified(): void
    {
        $response = $this->actingAs($this->userWithId(7))
            ->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->postJson(route('incentivos.reporte-nuevo-incentivo-v6.periodo.guardar'), $this->payload([
                $this->detail('40211111111', 0, 0),
            ]));

        $response
            ->assertOk()
            ->assertJsonPath('resumen.no_califican', 1)
            ->assertJsonPath('resumen.no_pagados', 0);

        $this->assertDatabaseHas('incentivo_periodo_detalles', [
            'cedula' => '40211111111',
            'incentivo_generado' => 0,
            'monto_pagado' => 0,
            'monto_no_pagado' => 0,
            'estado' => 'no_califica',
        ]);
        $this->assertSame(
            ['meta_no_alcanzada'],
            IncentivoPeriodoDetalle::query()->where('cedula', '40211111111')->firstOrFail()->motivos
        );
    }

    private function userWithId(int $id): User
    {
        $user = new User(['name' => "Usuario {$id}"]);
        $user->id = $id;

        return $user;
    }

    /**
     * @param  array<int, array<string, mixed>>  $details
     * @return array<string, mixed>
     */
    private function payload(array $details): array
    {
        return [
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-07-31',
            'sistema' => 'Todos',
            'modo_calculo' => 'separado_empresa',
            'tipo_pago_defecto' => 'tramos_60',
            'min_dias_venta' => 1,
            'rangos_pago_por_tipo' => ['tramos_60' => []],
            'terminales_excluidas' => ['1001'],
            'faltantes_aplicados' => true,
            'desvinculados_aplicados' => true,
            'detalles' => $details,
        ];
    }

    /**
     * @param  array<int, string>  $reasons
     * @return array<string, mixed>
     */
    private function detail(string $identity, int $generated, int $paid, array $reasons = []): array
    {
        return [
            'cedula' => $identity,
            'empleadoid' => '501',
            'nombre' => 'Persona de prueba',
            'empresa' => 'Grupo Joselito',
            'ultima_terminal' => '1001',
            'ultima_agencia_nombre' => 'Agencia de prueba',
            'ventas_ultimo_mes' => 200000,
            'ventas_mes_actual' => 300000,
            'dias_ventas' => 20,
            'horas_total' => 160.5,
            'incentivo_generado' => $generated,
            'monto_pagado' => $paid,
            'motivos' => $reasons,
            'tipos_pago_detalle' => [],
        ];
    }
}
