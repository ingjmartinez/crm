<?php

namespace Tests\Feature;

use App\Models\IncentivoTerminalTipoPago;
use App\Models\User;
use App\Services\IncentivoV6Calculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IncentivoV6CalendarTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('incentivo_terminal_tipo_pagos');
        Schema::dropIfExists('vt_usuarios_bet');
        Schema::dropIfExists('vt_usuarios_net');
        Schema::dropIfExists('agencias');

        Schema::create('incentivo_terminal_tipo_pagos', function (Blueprint $table): void {
            $table->id();
            $table->string('sistema', 20);
            $table->string('terminal', 50);
            $table->date('fecha');
            $table->string('tipo_pago', 20);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['sistema', 'terminal', 'fecha']);
        });
        Schema::create('vt_usuarios_bet', function (Blueprint $table): void {
            $table->id();
            $table->string('agencia_id', 50);
            $table->string('cedula', 20);
            $table->date('fecha');
            $table->decimal('monto', 15, 2);
        });
        Schema::create('vt_usuarios_net', function (Blueprint $table): void {
            $table->id();
            $table->string('agencia_id', 50);
            $table->string('cedula', 20);
            $table->date('fecha');
            $table->decimal('monto', 15, 2);
        });
        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal', 50);
            $table->string('sistema', 20)->nullable();
            $table->string('empresa', 100)->nullable();
            $table->string('nombre_agencia', 100)->nullable();
            $table->string('agencia', 100)->nullable();
            $table->integer('estatus')->default(1);
        });
    }

    public function test_calendar_assignments_can_be_saved_in_bulk_and_cleared_individually(): void
    {
        $this->actingAs(User::factory()->make(['id' => 55]));

        $this->putJson(route('incentivos.reporte-nuevo-incentivo-v6.calendario.guardar'), [
            'asignaciones' => [
                ['sistema' => 'Lotobet', 'terminal' => '1001', 'fecha' => '2026-07-06', 'tipo_pago' => 'tramos_80'],
                ['sistema' => 'Lotobet', 'terminal' => '1001', 'fecha' => '2026-07-07', 'tipo_pago' => 'tramos_80'],
                ['sistema' => 'Lotobet', 'terminal' => '1002', 'fecha' => '2026-07-06', 'tipo_pago' => 'tramos_70'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('guardadas', 3)
            ->assertJsonPath('eliminadas', 0);

        $this->assertDatabaseHas('incentivo_terminal_tipo_pagos', [
            'sistema' => 'Lotobet',
            'terminal' => '1001',
            'fecha' => '2026-07-06',
            'tipo_pago' => 'tramos_80',
        ]);

        $this->putJson(route('incentivos.reporte-nuevo-incentivo-v6.calendario.guardar'), [
            'asignaciones' => [
                ['sistema' => 'Lotobet', 'terminal' => '1001', 'fecha' => '2026-07-06', 'tipo_pago' => 'tramos_70'],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('incentivo_terminal_tipo_pagos', [
            'sistema' => 'Lotobet',
            'terminal' => '1001',
            'fecha' => '2026-07-06',
            'tipo_pago' => 'tramos_70',
        ]);
        $this->assertDatabaseCount('incentivo_terminal_tipo_pagos', 3);

        $this->putJson(route('incentivos.reporte-nuevo-incentivo-v6.calendario.guardar'), [
            'asignaciones' => [
                ['sistema' => 'Lotobet', 'terminal' => '1001', 'fecha' => '2026-07-06', 'tipo_pago' => null],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('guardadas', 0)
            ->assertJsonPath('eliminadas', 1);

        $this->assertDatabaseMissing('incentivo_terminal_tipo_pagos', [
            'sistema' => 'Lotobet',
            'terminal' => '1001',
            'fecha' => '2026-07-06',
        ]);
        $this->assertDatabaseCount('incentivo_terminal_tipo_pagos', 2);
    }

    public function test_calendar_rejects_an_unknown_payment_type(): void
    {
        $this->actingAs(User::factory()->make(['id' => 55]));

        $this->putJson(route('incentivos.reporte-nuevo-incentivo-v6.calendario.guardar'), [
            'asignaciones' => [
                ['sistema' => 'Lotobet', 'terminal' => '1001', 'fecha' => '2026-07-06', 'tipo_pago' => 'tramos_90'],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('asignaciones.0.tipo_pago');

        $this->assertDatabaseCount('incentivo_terminal_tipo_pagos', 0);
    }

    public function test_calendar_lists_active_agency_terminals_even_without_sales_and_excludes_inactive_ones(): void
    {
        $this->actingAs(User::factory()->make(['id' => 55]));
        DB::table('agencias')->insert([
            [
                'terminal' => '1001',
                'sistema' => 'LOTOBET',
                'empresa' => 'Grupo Joselito',
                'nombre_agencia' => 'Agencia Central',
                'estatus' => 1,
            ],
            [
                'terminal' => '1002',
                'sistema' => 'LOTOBET',
                'empresa' => 'Grupo Joselito',
                'nombre_agencia' => 'Agencia Inactiva',
                'estatus' => 0,
            ],
        ]);
        DB::table('vt_usuarios_bet')->insert([
            'agencia_id' => '1002',
            'cedula' => '00112345678',
            'fecha' => '2026-07-06',
            'monto' => 75000,
        ]);
        IncentivoTerminalTipoPago::query()->create([
            'sistema' => 'Lotobet',
            'terminal' => '1001',
            'fecha' => '2026-07-06',
            'tipo_pago' => 'tramos_80',
        ]);

        $this->getJson(route('incentivos.reporte-nuevo-incentivo-v6.calendario', [
            'fecha_ini' => '2026-07-06',
            'fecha_fin' => '2026-07-12',
            'sistema' => 'Lotobet',
        ]))
            ->assertOk()
            ->assertJsonCount(7, 'fechas')
            ->assertJsonCount(1, 'terminales')
            ->assertJsonPath('terminales.0.terminal', '1001')
            ->assertJsonPath('terminales.0.agencia', 'Agencia Central')
            ->assertJsonPath('terminales.0.ventas', 0)
            ->assertJsonPath('terminales.0.tipos_por_fecha.2026-07-06', 'tramos_80');
    }

    public function test_calendar_normalizes_lotenet_system_name_from_agencies_table(): void
    {
        $this->actingAs(User::factory()->make(['id' => 55]));
        DB::table('agencias')->insert([
            'terminal' => '2001',
            'sistema' => 'LOTENET',
            'empresa' => 'Grupo Central',
            'nombre_agencia' => 'Agencia Net',
            'estatus' => 1,
        ]);

        $this->getJson(route('incentivos.reporte-nuevo-incentivo-v6.calendario', [
            'fecha_ini' => '2026-07-06',
            'fecha_fin' => '2026-07-12',
            'sistema' => 'Lotonet',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'terminales')
            ->assertJsonPath('terminales.0.sistema', 'Lotonet')
            ->assertJsonPath('terminales.0.terminal', '2001');
    }

    public function test_calendar_places_agencies_with_payment_configuration_first(): void
    {
        $this->actingAs(User::factory()->make(['id' => 55]));
        DB::table('agencias')->insert([
            [
                'terminal' => '1001',
                'sistema' => 'LOTOBET',
                'empresa' => 'Grupo Central',
                'nombre_agencia' => 'Agencia General',
                'estatus' => 1,
            ],
            [
                'terminal' => '1002',
                'sistema' => 'LOTOBET',
                'empresa' => 'Grupo Central',
                'nombre_agencia' => 'Agencia Configurada',
                'estatus' => 1,
            ],
        ]);
        IncentivoTerminalTipoPago::query()->create([
            'sistema' => 'Lotobet',
            'terminal' => '1002',
            'fecha' => '2026-07-08',
            'tipo_pago' => 'tramos_80',
        ]);

        $this->getJson(route('incentivos.reporte-nuevo-incentivo-v6.calendario', [
            'fecha_ini' => '2026-07-06',
            'fecha_fin' => '2026-07-12',
            'sistema' => 'Lotobet',
        ]))
            ->assertOk()
            ->assertJsonPath('terminales.0.terminal', '1002')
            ->assertJsonPath('terminales.0.tiene_configuracion', true)
            ->assertJsonPath('terminales.1.terminal', '1001')
            ->assertJsonPath('terminales.1.tiene_configuracion', false);
    }

    public function test_calendar_paginates_active_agencies_to_keep_the_grid_lightweight(): void
    {
        $this->actingAs(User::factory()->make(['id' => 55]));
        DB::table('agencias')->insert(collect(range(1, 60))->map(fn (int $number): array => [
            'terminal' => (string) (3000 + $number),
            'sistema' => 'LOTOBET',
            'empresa' => 'Grupo Central',
            'nombre_agencia' => 'Agencia '.$number,
            'estatus' => 1,
        ])->all());

        $this->getJson(route('incentivos.reporte-nuevo-incentivo-v6.calendario', [
            'fecha_ini' => '2026-07-06',
            'fecha_fin' => '2026-07-12',
            'sistema' => 'Lotobet',
            'page' => 2,
            'per_page' => 25,
        ]))
            ->assertOk()
            ->assertJsonCount(25, 'terminales')
            ->assertJsonPath('paginacion.pagina_actual', 2)
            ->assertJsonPath('paginacion.ultima_pagina', 3)
            ->assertJsonPath('paginacion.total', 60)
            ->assertJsonPath('paginacion.desde', 26)
            ->assertJsonPath('paginacion.hasta', 50);
    }

    public function test_calendar_recognizes_manual_terminals_across_pages_and_reports_missing_ones(): void
    {
        $this->actingAs(User::factory()->make(['id' => 55]));
        DB::table('agencias')->insert(collect(range(1, 60))->map(fn (int $number): array => [
            'terminal' => (string) (4000 + $number),
            'sistema' => $number === 60 ? 'LOTENET' : 'LOTOBET',
            'empresa' => 'Grupo Central',
            'nombre_agencia' => 'Agencia '.$number,
            'estatus' => 1,
        ])->push([
            'terminal' => '4999',
            'sistema' => 'LOTOBET',
            'empresa' => 'Grupo Central',
            'nombre_agencia' => 'Agencia Inactiva',
            'estatus' => 0,
        ])->all());

        $terminales = collect(range(1, 60))
            ->map(fn (int $number): string => (string) (4000 + $number))
            ->push('4001')
            ->push('4999')
            ->push('9999')
            ->implode("\n");

        $this->postJson(route('incentivos.reporte-nuevo-incentivo-v6.calendario.terminales.reconocer'), [
            'terminales_manual' => $terminales,
            'sistema' => 'Todos',
        ])
            ->assertOk()
            ->assertJsonPath('terminales_leidas', 63)
            ->assertJsonPath('terminales_unicas', 62)
            ->assertJsonPath('encontradas', 61)
            ->assertJsonPath('coincidencias', 61)
            ->assertJsonPath('asignaciones_preparadas', 122)
            ->assertJsonCount(61, 'terminales')
            ->assertJsonPath('terminales.59.terminal', '4060')
            ->assertJsonPath('terminales.59.sistemas.0', 'Lotobet')
            ->assertJsonPath('terminales.59.sistemas.1', 'Lotonet')
            ->assertJsonPath('terminales.60.terminal', '4999')
            ->assertJsonPath('terminales_no_encontradas.0', '9999');
    }

    public function test_calendar_recognizes_active_terminals_from_csv_and_uses_selected_system(): void
    {
        $this->actingAs(User::factory()->make(['id' => 55]));
        DB::table('agencias')->insert([
            [
                'terminal' => '5001',
                'sistema' => 'LOTOBET',
                'empresa' => 'Grupo Bet',
                'nombre_agencia' => 'Agencia Bet',
                'estatus' => 1,
            ],
            [
                'terminal' => '5002',
                'sistema' => 'LOTENET',
                'empresa' => 'Grupo Net',
                'nombre_agencia' => 'Agencia Net',
                'estatus' => 1,
            ],
        ]);
        $file = UploadedFile::fake()->createWithContent('terminales.csv', "Terminal\n5001\n5002\n");

        $this->post(route('incentivos.reporte-nuevo-incentivo-v6.calendario.terminales.reconocer'), [
            'file' => $file,
            'sistema' => 'Lotobet',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('total_filas', 2)
            ->assertJsonPath('encontradas', 2)
            ->assertJsonCount(2, 'terminales')
            ->assertJsonPath('terminales.0.terminal', '5001')
            ->assertJsonPath('terminales.0.sistemas.0', 'Lotobet')
            ->assertJsonPath('terminales.1.terminal', '5002')
            ->assertJsonPath('terminales.1.sistemas.0', 'Lotobet')
            ->assertJsonCount(0, 'terminales_no_encontradas');
    }

    public function test_calendar_recognizes_an_inactive_registered_terminal_without_system(): void
    {
        $this->actingAs(User::factory()->make(['id' => 55]));
        DB::table('agencias')->insert([
            'terminal' => '05892',
            'sistema' => null,
            'empresa' => 'Negosur',
            'nombre_agencia' => 'Oviedo-36 Ltk',
            'estatus' => 0,
        ]);

        $this->postJson(route('incentivos.reporte-nuevo-incentivo-v6.calendario.terminales.reconocer'), [
            'terminales_manual' => '05892',
            'sistema' => 'Lotonet',
        ])
            ->assertOk()
            ->assertJsonPath('encontradas', 1)
            ->assertJsonPath('coincidencias', 1)
            ->assertJsonPath('terminales.0.terminal', '05892')
            ->assertJsonPath('terminales.0.sistemas.0', 'Lotonet')
            ->assertJsonPath('terminales.0.agencia', 'Oviedo-36 Ltk')
            ->assertJsonPath('terminales.0.empresa', 'Negosur')
            ->assertJsonCount(0, 'terminales_no_encontradas');
    }

    public function test_calendar_terminal_recognition_requires_a_file_or_manual_list(): void
    {
        $this->actingAs(User::factory()->make(['id' => 55]));

        $this->postJson(route('incentivos.reporte-nuevo-incentivo-v6.calendario.terminales.reconocer'), [
            'sistema' => 'Todos',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('terminales_manual');
    }

    public function test_daily_calculator_splits_sales_between_payment_80_and_default_payment_60(): void
    {
        DB::table('agencias')->insert([
            'terminal' => '1001',
            'sistema' => 'Lotobet',
            'empresa' => 'Grupo Joselito',
        ]);

        foreach ([
            '2026-07-06' => 75000,
            '2026-07-07' => 75000,
            '2026-07-08' => 75000,
            '2026-07-09' => 75000,
            '2026-07-10' => 100000,
            '2026-07-11' => 100000,
            '2026-07-12' => 100000,
        ] as $date => $amount) {
            DB::table('vt_usuarios_bet')->insert([
                'agencia_id' => '1001',
                'cedula' => '00112345678',
                'fecha' => $date,
                'monto' => $amount,
            ]);
        }

        foreach (['2026-07-06', '2026-07-07', '2026-07-08', '2026-07-09'] as $date) {
            IncentivoTerminalTipoPago::query()->create([
                'sistema' => 'Lotobet',
                'terminal' => '1001',
                'fecha' => $date,
                'tipo_pago' => 'tramos_80',
            ]);
        }

        $payload = app(IncentivoV6Calculator::class)->applyDailyPaymentTypes([
            'meta' => [
                'coordinador_detalle_usuarios' => [],
                'coordinador_monto_usuarios' => [],
            ],
            'data' => [[
                'cedula' => '00112345678',
                'empresa' => 'Grupo Joselito',
                'ventas_mes_actual' => '600,000',
                'nuevo_incentivo' => '6,000',
            ]],
        ], [
            'fecha_ini' => '2026-07-06',
            'fecha_fin' => '2026-07-12',
            'sistema' => 'Lotobet',
            'tipo_pago' => 'tramos_60',
            'min_dias_venta' => 1,
            'terminales_excluidas' => [],
        ], $this->paymentRanges());

        $this->assertSame('3,000', $payload['data'][0]['nuevo_incentivo']);
        $this->assertSame(3000, $payload['meta']['total_incentivo']);
        $this->assertSame(4, $payload['meta']['configuraciones_diarias_aplicadas']);
        $this->assertSame(1, $payload['meta']['distribucion_tipos_pago']['tramos_60']['agencias']);
        $this->assertSame(1, $payload['meta']['distribucion_tipos_pago']['tramos_80']['agencias']);
        $this->assertSame(1, $payload['meta']['distribucion_tipos_pago']['tramos_80']['agencias_por_empresa']['Grupo Joselito']);
        $this->assertSame([
            'agencias' => 1,
            'rangos' => [[
                'desde' => '2026-07-10',
                'hasta' => '2026-07-12',
                'agencias' => 1,
            ]],
        ], $payload['meta']['detalle_calendario_tipos_pago']['tramos_60']);
        $this->assertSame([
            'agencias' => 1,
            'rangos' => [[
                'desde' => '2026-07-06',
                'hasta' => '2026-07-09',
                'agencias' => 1,
            ]],
        ], $payload['meta']['detalle_calendario_tipos_pago']['tramos_80']);
        $this->assertCount(2, $payload['data'][0]['tipos_pago_detalle']);
        $this->assertSame(
            ['tramos_80', 'tramos_60'],
            array_column($payload['data'][0]['tipos_pago_detalle'], 'tipo_pago')
        );
    }

    public function test_calendar_pdf_breakdown_groups_agencies_with_the_same_effective_range(): void
    {
        foreach (['2001', '2002', '2003'] as $index => $terminal) {
            DB::table('agencias')->insert([
                'terminal' => $terminal,
                'sistema' => 'Lotobet',
                'empresa' => 'Grupo Joselito',
            ]);
            DB::table('vt_usuarios_bet')->insert([
                'agencia_id' => $terminal,
                'cedula' => '0011234567'.($index + 1),
                'fecha' => '2026-07-15',
                'monto' => 100000,
            ]);

            $firstPaymentDay = $terminal === '2003' ? 12 : 10;
            foreach (range($firstPaymentDay, 31) as $day) {
                IncentivoTerminalTipoPago::query()->create([
                    'sistema' => 'Lotobet',
                    'terminal' => $terminal,
                    'fecha' => sprintf('2026-07-%02d', $day),
                    'tipo_pago' => 'tramos_80',
                ]);
            }
        }

        $payload = app(IncentivoV6Calculator::class)->applyDailyPaymentTypes([
            'meta' => [
                'coordinador_detalle_usuarios' => [],
                'coordinador_monto_usuarios' => [],
            ],
            'data' => collect(['2001', '2002', '2003'])->map(fn (string $terminal, int $index): array => [
                'cedula' => '0011234567'.($index + 1),
                'empresa' => 'Grupo Joselito',
                'ventas_mes_actual' => '100,000',
                'nuevo_incentivo' => '1,000',
            ])->all(),
        ], [
            'fecha_ini' => '2026-07-01',
            'fecha_fin' => '2026-07-31',
            'sistema' => 'Lotobet',
            'tipo_pago' => 'tramos_60',
            'min_dias_venta' => 1,
            'terminales_excluidas' => [],
        ], $this->paymentRanges());

        $this->assertSame(3, $payload['meta']['detalle_calendario_tipos_pago']['tramos_80']['agencias']);
        $this->assertSame([
            [
                'desde' => '2026-07-10',
                'hasta' => '2026-07-31',
                'agencias' => 2,
            ],
            [
                'desde' => '2026-07-12',
                'hasta' => '2026-07-31',
                'agencias' => 1,
            ],
        ], $payload['meta']['detalle_calendario_tipos_pago']['tramos_80']['rangos']);
    }

    public function test_v6_view_contains_calendar_without_changing_v5_view(): void
    {
        $data = [
            'coordinadores' => collect(),
            'administrativosConfig' => [],
            'terminalesExcluidasIncentivo' => [],
        ];
        $v5 = view('incentivos.reporte-nuevo-incentivo-v5', $data)->render();
        $v6 = view('incentivos.reporte-nuevo-incentivo-v6', $data)->render();

        $this->assertStringNotContainsString('btnCalendarioTiposPago', $v5);
        $this->assertStringContainsString('btnCalendarioTiposPago', $v6);
        $this->assertStringContainsString('/incentivos/reporte-nuevo-incentivo-v6?', $v6);
        $this->assertStringContainsString('Agencias calculadas por tipo de pago', $v6);
        $this->assertStringContainsString('Informe Gerencial de Incentivos V6', $v6);
        $this->assertStringContainsString('calendarioPaginaSiguiente', $v6);
        $this->assertStringContainsString('document.createDocumentFragment()', $v6);
        $this->assertStringContainsString('btnReconocerTerminalesCalendario', $v6);
        $this->assertStringContainsString('btnLimpiarTerminalesCalendario', $v6);
        $this->assertStringContainsString('clearRecognizedCalendarTerminalScenario', $v6);
        $this->assertStringContainsString('btnAplicarTerminalesReconocidas', $v6);
        $this->assertStringContainsString('calendarRecognizedTerminals', $v6);
        $this->assertStringContainsString('calendarioFechaInicioMasiva', $v6);
        $this->assertStringContainsString('calendarioFechaFinMasiva', $v6);
        $this->assertStringContainsString('btnDetalleCalendarioPdf', $v6);
        $this->assertStringContainsString('generarPdfDetalleCalendario', $v6);
        $this->assertStringContainsString('btnInformeCoordinadoresPdf', $v6);
        $this->assertStringContainsString('generarPdfInformeCoordinadores', $v6);
        $this->assertStringContainsString('Informe de Validacion de Coordinadores', $v6);
        $this->assertStringNotContainsString("{ text: 'Detalle por coordinador'", $v6);
        $this->assertStringNotContainsString('<th>Sistema</th>', $v6);
        $this->assertStringContainsString('getRecognizedCalendarDates', $v6);
        $this->assertStringContainsString('<option value="desde">', $v6);
        $this->assertStringContainsString('<option value="rango">', $v6);

        $coordinatorModal = substr($v6, strpos($v6, 'id="modalCoordinadores"'), 5000);
        $validAgenciesColumn = strpos($coordinatorModal, '>Validas</th>');
        $retentionColumn = strpos($coordinatorModal, '>Monto en retenci');
        $amountColumn = strpos($coordinatorModal, '>Monto</th>');
        $this->assertGreaterThan($validAgenciesColumn, $retentionColumn);
        $this->assertLessThan($amountColumn, $retentionColumn);
    }

    /**
     * @return array<string, array<int, array<string, int|float|string|null>>>
     */
    private function paymentRanges(): array
    {
        return [
            'tramos_60' => $this->buildRanges(1, [1000, 2000, 4000, 6000, 8000, 9000]),
            'tramos_70' => $this->buildRanges(0.75, [750, 1500, 3000, 4500, 6000, 6750]),
            'tramos_80' => $this->buildRanges(0.5, [500, 1000, 2000, 3000, 4000, 4500]),
        ];
    }

    /**
     * @param  array<int, int>  $payments
     * @return array<int, array<string, int|float|string|null>>
     */
    private function buildRanges(float $percentage, array $payments): array
    {
        return [
            ['desde' => 100001, 'hasta' => 250000, 'pago' => $payments[0], 'tipo' => 'fijo'],
            ['desde' => 250001, 'hasta' => 400000, 'pago' => $payments[1], 'tipo' => 'fijo'],
            ['desde' => 400001, 'hasta' => 550000, 'pago' => $payments[2], 'tipo' => 'fijo'],
            ['desde' => 550001, 'hasta' => 700000, 'pago' => $payments[3], 'tipo' => 'fijo'],
            ['desde' => 700001, 'hasta' => 850000, 'pago' => $payments[4], 'tipo' => 'fijo'],
            ['desde' => 850001, 'hasta' => 1000000, 'pago' => $payments[5], 'tipo' => 'fijo'],
            ['desde' => 1000001, 'hasta' => null, 'pago' => $percentage, 'tipo' => 'porcentaje'],
        ];
    }
}
