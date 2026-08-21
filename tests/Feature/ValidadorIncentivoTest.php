<?php

namespace Tests\Feature;

use App\Models\IncentivoPeriodo;
use App\Models\IncentivoPeriodoDetalle;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class ValidadorIncentivoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 77;
        $user->shouldReceive('hasRole')->andReturnFalse();
        $user->shouldReceive('can')->andReturnFalse();
        $this->actingAs($user);
        Gate::before(fn (): bool => false);

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });

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
            $table->bigInteger('ventas_ultimo_mes')->default(0);
            $table->bigInteger('ventas_mes_actual')->default(0);
            $table->unsignedSmallInteger('dias_ventas')->default(0);
            $table->decimal('horas_total', 10, 2)->default(0);
            $table->bigInteger('incentivo_generado')->default(0);
            $table->bigInteger('monto_pagado')->default(0);
            $table->bigInteger('monto_no_pagado')->default(0);
            $table->string('estado', 30);
            $table->json('motivos')->nullable();
            $table->json('tipos_pago_detalle')->nullable();
            $table->timestamps();
        });
    }

    public function test_report_defaults_to_latest_period_without_querying_its_details(): void
    {
        $olderPeriod = $this->period(2026, 6);
        $latestPeriod = $this->period(2026, 7, revision: 2);
        $this->detail($olderPeriod, '00100000001', 'Persona anterior', 'pagado', 1000, 1000);
        $this->detail($latestPeriod, '40211111111', 'Persona pagada', 'pagado', 2000, 2000);

        $this->get(route('recursos-humanos.validador-incentivos.index'))
            ->assertOk()
            ->assertSee('Junio 2026')
            ->assertSee('Julio 2026')
            ->assertSee('Revisión 2')
            ->assertSee('El último mes guardado está seleccionado')
            ->assertDontSee('Persona pagada')
            ->assertDontSee('Persona anterior');
    }

    public function test_report_lists_all_payment_states_after_user_generates_the_query(): void
    {
        $olderPeriod = $this->period(2026, 6);
        $latestPeriod = $this->period(2026, 7, revision: 2);
        $this->detail($olderPeriod, '00100000001', 'Persona anterior', 'pagado', 1000, 1000);
        $this->detail($latestPeriod, '40211111111', 'Persona pagada', 'pagado', 2000, 2000);
        $this->detail($latestPeriod, '40222222222', 'Persona retenida', 'no_pagado', 3000, 0, ['faltante']);
        $this->detail($latestPeriod, '40233333333', 'Persona parcial', 'pagado_parcial', 4000, 2500, ['agencia_excluida']);
        $this->detail($latestPeriod, '40244444444', 'Persona sin incentivo', 'no_califica', 0, 0, ['meta_no_alcanzada']);

        $this->get(route('recursos-humanos.validador-incentivos.index', ['consultar' => 1]))
            ->assertOk()
            ->assertSee('Julio 2026')
            ->assertSee('Revisión 2')
            ->assertSee('Persona pagada')
            ->assertSee('Persona retenida')
            ->assertSee('Persona parcial')
            ->assertSee('Faltante')
            ->assertSee('Agencia excluida')
            ->assertSee('Persona sin incentivo')
            ->assertSee('Meta no alcanzada')
            ->assertSee('estado-motivo-column', false)
            ->assertSee('detalle-validacion-card', false)
            ->assertDontSee('Persona anterior');
    }

    public function test_report_filters_by_identity_state_reason_and_company(): void
    {
        $period = $this->period(2026, 7);
        $this->detail($period, '40238509620', 'Objetivo retenido', 'no_pagado', 3500, 0, ['desvinculado'], 'Grupo Joselito');
        $this->detail($period, '40299999999', 'Otro retenido', 'no_pagado', 1000, 0, ['faltante'], 'Negosur');
        $this->detail($period, '40288888888', 'Persona pagada', 'pagado', 2000, 2000, [], 'Grupo Joselito');

        $this->get(route('recursos-humanos.validador-incentivos.index', [
            'periodo_id' => $period->id,
            'buscar' => '402-3850962-0',
            'estado' => 'no_pagado',
            'motivo' => 'desvinculado',
            'empresa' => 'Grupo Joselito',
            'consultar' => 1,
        ]))
            ->assertOk()
            ->assertSee('Objetivo retenido')
            ->assertSee('Desvinculado')
            ->assertDontSee('Otro retenido')
            ->assertDontSee('Persona pagada');
    }

    public function test_export_respects_filters_and_contains_human_readable_reason(): void
    {
        $period = $this->period(2026, 7);
        $this->detail($period, '40238509620', 'Persona exportada', 'no_pagado', 3500, 0, ['agencia_excluida']);
        $this->detail($period, '40299999999', 'Persona pagada', 'pagado', 2000, 2000);

        $response = $this->get(route('recursos-humanos.validador-incentivos.export', [
            'periodo_id' => $period->id,
            'estado' => 'no_pagado',
        ]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload('validador-incentivos-2026-07.csv');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Persona exportada', $content);
        $this->assertStringContainsString('Agencia excluida', $content);
        $this->assertStringNotContainsString('Persona pagada', $content);
    }

    public function test_report_can_filter_people_who_did_not_reach_the_goal(): void
    {
        $period = $this->period(2026, 7);
        $this->detail($period, '40211111111', 'No llegó a la meta', 'no_califica', 0, 0, ['meta_no_alcanzada']);
        $this->detail($period, '40222222222', 'Persona pagada', 'pagado', 2000, 2000);

        $this->get(route('recursos-humanos.validador-incentivos.index', [
            'periodo_id' => $period->id,
            'estado' => 'no_califica',
            'motivo' => 'meta_no_alcanzada',
            'consultar' => 1,
        ]))
            ->assertOk()
            ->assertSee('No llegó a la meta')
            ->assertSee('Meta no alcanzada')
            ->assertDontSee('Persona pagada');
    }

    public function test_report_identifies_only_payable_people_without_employee_id(): void
    {
        $period = $this->period(2026, 7);
        $payableWithoutId = $this->detail($period, '40211111111', 'Sin ID pagable', 'pagado', 2000, 2000);
        $excludedWithoutId = $this->detail($period, '40222222222', 'Sin ID excluido', 'no_pagado', 2000, 0, ['faltante']);
        $this->detail($period, '40233333333', 'Con ID pagable', 'pagado', 2000, 2000);
        $payableWithoutId->update(['empleadoid' => null]);
        $excludedWithoutId->update(['empleadoid' => null]);

        $this->get(route('recursos-humanos.validador-incentivos.index', [
            'periodo_id' => $period->id,
            'estado' => 'sin_idempleado',
            'consultar' => 1,
        ]))
            ->assertOk()
            ->assertSee('Sin ID pagable')
            ->assertSee('Sin IdEmpleado')
            ->assertDontSee('Sin ID excluido')
            ->assertDontSee('Con ID pagable');
    }

    public function test_export_marks_payable_people_without_employee_id(): void
    {
        $period = $this->period(2026, 7);
        $detail = $this->detail($period, '40211111111', 'Sin ID exportable', 'pagado', 2000, 2000);
        $detail->update(['empleadoid' => null]);

        $response = $this->get(route('recursos-humanos.validador-incentivos.export', [
            'periodo_id' => $period->id,
            'estado' => 'sin_idempleado',
        ]));

        $response->assertOk();

        $content = $response->streamedContent();
        $this->assertStringContainsString('Sin ID exportable', $content);
        $this->assertStringContainsString('Sin IdEmpleado / Pagado', $content);
    }

    public function test_report_shows_guidance_when_there_are_no_saved_periods(): void
    {
        $this->get(route('recursos-humanos.validador-incentivos.index'))
            ->assertOk()
            ->assertSee('No hay períodos guardados')
            ->assertSee('Guardar período');
    }

    private function period(int $year, int $month, int $revision = 1): IncentivoPeriodo
    {
        return IncentivoPeriodo::query()->create([
            'anio' => $year,
            'mes' => $month,
            'fecha_inicio' => sprintf('%d-%02d-01', $year, $month),
            'fecha_fin' => sprintf('%d-%02d-%02d', $year, $month, cal_days_in_month(CAL_GREGORIAN, $month, $year)),
            'sistema' => 'Todos',
            'modo_calculo' => 'separado_empresa',
            'tipo_pago_defecto' => 'tramos_60',
            'min_dias_venta' => 1,
            'revision' => $revision,
        ]);
    }

    /** @param array<int, string> $reasons */
    private function detail(
        IncentivoPeriodo $period,
        string $identity,
        string $name,
        string $state,
        int $generated,
        int $paid,
        array $reasons = [],
        string $company = 'Grupo Joselito'
    ): IncentivoPeriodoDetalle {
        return IncentivoPeriodoDetalle::query()->create([
            'incentivo_periodo_id' => $period->id,
            'cedula' => $identity,
            'empleadoid' => '501',
            'nombre' => $name,
            'empresa' => $company,
            'ultima_terminal' => '1001',
            'ultima_agencia_nombre' => 'Agencia Central',
            'ventas_ultimo_mes' => 200000,
            'ventas_mes_actual' => 300000,
            'dias_ventas' => 20,
            'horas_total' => 160.5,
            'incentivo_generado' => $generated,
            'monto_pagado' => $paid,
            'monto_no_pagado' => $generated - $paid,
            'estado' => $state,
            'motivos' => $reasons,
            'tipos_pago_detalle' => [],
        ]);
    }
}
