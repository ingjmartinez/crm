<?php

namespace Tests\Feature;

use App\Models\TipoPagoTerminalDia;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class MantenimientoTipoPagoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('tipo_pago_terminal_dias', function (Blueprint $table): void {
            $table->id();
            $table->date('fecha');
            $table->string('terminal', 50);
            $table->unsignedTinyInteger('tipo_pago');
            $table->string('tipo_pago_original');
            $table->string('archivo_origen');
            $table->unsignedBigInteger('cargado_por_id')->nullable();
            $table->timestamps();
            $table->unique(['fecha', 'terminal']);
        });
    }

    public function test_it_imports_only_columns_b_and_w_and_preserves_daily_history(): void
    {
        $this->withoutMiddleware();

        $this->post(route('mantenimiento.tipo-pago.store'), [
            'fecha' => '2026-09-29',
            'archivo' => $this->archivoCsv([
                ['00050005', '60-08-04 P1,000 T20,000'],
                ['', '60-08-04 P1,000 T20,000'],
                ['50006', '70-08-04 P1,000 T20,000'],
            ]),
        ])->assertRedirect(route('mantenimiento.tipo-pago.index', ['fecha' => '2026-09-29']));

        $this->assertDatabaseCount('tipo_pago_terminal_dias', 2);
        $this->assertDatabaseHas('tipo_pago_terminal_dias', [
            'fecha' => '2026-09-29',
            'terminal' => '50005',
            'tipo_pago' => 60,
            'tipo_pago_original' => '60-08-04 P1,000 T20,000',
        ]);

        $this->post(route('mantenimiento.tipo-pago.store'), [
            'fecha' => '2026-09-30',
            'archivo' => $this->archivoCsv([['50005', '80-08-04 P1,000 T20,000']]),
        ])->assertRedirect();

        $this->post(route('mantenimiento.tipo-pago.store'), [
            'fecha' => '2026-09-30',
            'archivo' => $this->archivoCsv([['50005', '70-08-04 P1,000 T20,000']]),
        ])->assertRedirect();

        $this->assertDatabaseCount('tipo_pago_terminal_dias', 3);
        $this->assertDatabaseHas('tipo_pago_terminal_dias', ['fecha' => '2026-09-30', 'terminal' => '50005', 'tipo_pago' => 70]);
        $this->assertDatabaseHas('tipo_pago_terminal_dias', ['fecha' => '2026-09-29', 'terminal' => '50005', 'tipo_pago' => 60]);
    }

    public function test_it_rejects_invalid_payment_values_without_saving_partial_rows(): void
    {
        $this->withoutMiddleware();

        $this->post(route('mantenimiento.tipo-pago.store'), [
            'fecha' => '2026-09-30',
            'archivo' => $this->archivoCsv([
                ['50005', '60-08-04 P1,000 T20,000'],
                ['50006', 'PAGO DESCONOCIDO'],
            ]),
        ])->assertSessionHasErrors('archivo');

        $this->assertDatabaseCount('tipo_pago_terminal_dias', 0);
    }

    public function test_it_pivots_each_loaded_day_of_the_month_by_terminal_and_can_filter_one_day(): void
    {
        $this->withoutMiddleware();
        TipoPagoTerminalDia::factory()->create(['fecha' => '2026-09-29', 'terminal' => '50005']);
        TipoPagoTerminalDia::factory()->create(['fecha' => '2026-09-29', 'terminal' => '50006']);
        TipoPagoTerminalDia::factory()->create(['fecha' => '2026-09-30', 'terminal' => '50005', 'tipo_pago' => 70]);
        TipoPagoTerminalDia::factory()->create(['fecha' => '2026-10-01', 'terminal' => '50007']);

        $monthlyResponse = $this->getJson(route('mantenimiento.tipo-pago.data', [
            'mes' => '2026-09',
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => '50005'],
        ]));
        $monthlyResponse
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonCount(2, 'fechas')
            ->assertJsonPath('fechas.0', '2026-09-29')
            ->assertJsonPath('fechas.1', '2026-09-30')
            ->assertJsonPath('data.0.terminal', '50005')
            ->assertJsonPath('data.0.pagos.2026-09-29', 60)
            ->assertJsonPath('data.0.pagos.2026-09-30', 70)
            ->assertJsonMissingPath('data.0.tipo_pago_original');

        $this->getJson(route('mantenimiento.tipo-pago.data', [
            'fecha' => '2026-09-30',
            'length' => 25,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'fechas')
            ->assertJsonPath('fechas.0', '2026-09-30')
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.pagos.2026-09-30', 70)
            ->assertJsonMissingPath('data.0.pagos.2026-09-29');
    }

    public function test_maintenance_card_and_upload_page_are_available(): void
    {
        $card = collect(config('module_hubs.mantenimiento.items'))->firstWhere('nombre', 'Tipo Pago');

        $this->assertSame('/mantenimiento/tipo-pago', $card['url']);
        View::share('errors', new ViewErrorBag);
        $this->withoutMiddleware()
            ->get(route('mantenimiento.tipo-pago.index'))
            ->assertOk()
            ->assertSee('Cargar tipos de pago por día')
            ->assertSee('tablaTipoPago')
            ->assertSee('Mes completo')
            ->assertSee('Día específico')
            ->assertDontSee('Valor original');
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $datos
     */
    private function archivoCsv(array $datos): UploadedFile
    {
        $stream = fopen('php://temp', 'w+');
        $header = array_fill(0, 23, 'Otra columna');
        $header[1] = 'Textbox40';
        $header[22] = 'Textbox34';
        fputcsv($stream, $header);

        foreach ($datos as [$terminal, $tipoPago]) {
            $row = array_fill(0, 23, "Dato, con coma\ny salto de línea");
            $row[1] = $terminal;
            $row[22] = $tipoPago;
            fputcsv($stream, $row);
        }

        rewind($stream);
        $contenido = stream_get_contents($stream);
        fclose($stream);

        return UploadedFile::fake()->createWithContent('LTKBancas.csv', $contenido);
    }
}
