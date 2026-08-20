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

class DesglosePagoCedulaTest extends TestCase
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

    public function test_user_can_generate_an_exact_payment_breakdown_from_a_saved_period(): void
    {
        $detail = $this->createDetail();

        $this->get(route('incentivos.desglose-pago-cedula.index'))
            ->assertOk()
            ->assertSee('Julio 2026')
            ->assertSee('Consulta una cédula')
            ->assertDontSee('Klismairy Corporan Reyes');

        $this->get(route('incentivos.desglose-pago-cedula.index', [
            'consultar' => 1,
            'periodo_id' => $detail->incentivo_periodo_id,
            'cedula' => '402-3850962-0',
        ]))
            ->assertOk()
            ->assertSee('Klismairy Corporan Reyes')
            ->assertSee('RD$ 111,717')
            ->assertSee('RD$ 25,025')
            ->assertSee('22.40%')
            ->assertSee('RD$ 224')
            ->assertSee('RD$ 388')
            ->assertSee('RD$ 612');
    }

    public function test_user_can_download_the_payment_breakdown_as_pdf(): void
    {
        $detail = $this->createDetail();

        $this->get(route('incentivos.desglose-pago-cedula.pdf', $detail))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('desglose-incentivo-40238509620-2026-07.pdf');
    }

    private function createDetail(): IncentivoPeriodoDetalle
    {
        $period = IncentivoPeriodo::query()->create([
            'anio' => 2026,
            'mes' => 7,
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-07-31',
            'sistema' => 'Todos',
            'modo_calculo' => 'separado_empresa',
            'tipo_pago_defecto' => 'tramos_60',
            'min_dias_venta' => 1,
            'revision' => 1,
        ]);

        return IncentivoPeriodoDetalle::query()->create([
            'incentivo_periodo_id' => $period->id,
            'cedula' => '40238509620',
            'empleadoid' => '5906',
            'nombre' => 'Klismairy Corporan Reyes',
            'empresa' => 'Grupo Joselito',
            'ultima_terminal' => '050141',
            'ultima_agencia_nombre' => 'Agencia Central',
            'ventas_ultimo_mes' => 100610,
            'ventas_mes_actual' => 111717,
            'dias_ventas' => 29,
            'horas_total' => 160,
            'incentivo_generado' => 612,
            'monto_pagado' => 612,
            'monto_no_pagado' => 0,
            'estado' => 'pagado',
            'motivos' => [],
            'tipos_pago_detalle' => [
                ['tipo_pago' => 'tramos_60', 'ventas' => 25025, 'ventas_base_escala' => 111717, 'incentivo' => 224, 'dias' => 7, 'terminales' => 1],
                ['tipo_pago' => 'tramos_80', 'ventas' => 86692, 'ventas_base_escala' => 111717, 'incentivo' => 388, 'dias' => 22, 'terminales' => 1],
            ],
        ]);
    }
}
