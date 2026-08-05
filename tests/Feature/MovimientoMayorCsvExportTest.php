<?php

namespace Tests\Feature;

use App\Models\EntradaDiario;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MovimientoMayorCsvExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('entradas_diario', function (Blueprint $table): void {
            $table->id();
            $table->string('external_key')->unique();
            $table->string('payload_hash')->nullable();
            $table->string('no_asiento')->nullable();
            $table->string('company_id')->nullable();
            $table->date('fecha')->nullable();
            $table->string('fecha_raw')->nullable();
            $table->string('ref')->nullable();
            $table->string('no_ref')->nullable();
            $table->string('cuenta')->nullable();
            $table->decimal('debito', 18, 2)->default(0);
            $table->decimal('credito', 18, 2)->default(0);
            $table->text('descripcion')->nullable();
            $table->string('id_centro_costo')->nullable();
            $table->string('id_grupo')->nullable();
            $table->string('id_sub_grupo')->nullable();
            $table->string('id_division')->nullable();
            $table->string('id_sociedad')->nullable();
            $table->boolean('conciliado')->default(false);
            $table->string('modulo')->nullable();
            $table->dateTime('fecha_grabado')->nullable();
            $table->dateTime('fecha_modificado')->nullable();
            $table->string('id_viejo')->nullable();
            $table->string('centro_costo')->nullable();
            $table->string('grupo')->nullable();
            $table->string('sub_grupo')->nullable();
            $table->string('division')->nullable();
            $table->string('creado_por')->nullable();
            $table->string('modificado_por')->nullable();
            $table->string('ref_desc')->nullable();
            $table->string('sociedad')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('entradas_diario');

        parent::tearDown();
    }

    public function test_screen_contains_complete_csv_button(): void
    {
        $viewSource = file_get_contents(resource_path('views/contabilidad/movimiento-mayor.blade.php'));

        $this->assertTrue(Route::has('contabilidad.movimiento-mayor.exportar-csv'));
        $this->assertIsString($viewSource);
        $this->assertStringContainsString('id="btnExportarEntradasDiarioCsv"', $viewSource);
        $this->assertStringContainsString("route('contabilidad.movimiento-mayor.exportar-csv')", $viewSource);
        $this->assertStringContainsString("title: 'Generando CSV completo'", $viewSource);
        $this->assertStringContainsString("title: 'Descarga iniciada'", $viewSource);
        $this->assertStringContainsString('Swal.showLoading()', $viewSource);
    }

    public function test_csv_export_downloads_every_row_matching_selected_filters(): void
    {
        $matchingFirst = $this->createEntradaDiario([
            'external_key' => 'matching-first',
            'fecha' => '2026-08-01',
            'cuenta' => '5101',
            'descripcion' => 'Primer movimiento',
        ]);
        $matchingSecond = $this->createEntradaDiario([
            'external_key' => 'matching-second',
            'fecha' => '2026-08-02',
            'cuenta' => '5101',
            'descripcion' => '=FORMULA',
        ]);
        $this->createEntradaDiario([
            'external_key' => 'outside-range',
            'fecha' => '2026-08-03',
            'cuenta' => '5101',
            'descripcion' => 'No debe exportarse',
        ]);
        $this->createEntradaDiario([
            'external_key' => 'other-company',
            'company_id' => '169',
            'fecha' => '2026-08-01',
            'cuenta' => '5101',
            'descripcion' => 'Otra empresa',
        ]);

        $this->assertSame(2, EntradaDiario::query()
            ->where('company_id', '168')
            ->whereBetween('fecha', ['2026-08-01', '2026-08-02'])
            ->where('cuenta', '5101')
            ->count());

        $response = $this->actingAs($this->user())
            ->get(route('contabilidad.movimiento-mayor.exportar-csv', [
                'empresa' => '168',
                'fecha_inicio' => '2026-08-01',
                'fecha_fin' => '2026-08-02',
                'cuenta' => '5101',
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertDownload('movimiento-mayor-168-20260801-a-20260802.csv');

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBFNoAsiento,Fecha", $content);
        $this->assertStringContainsString((string) $matchingFirst->no_asiento, $content);
        $this->assertStringContainsString((string) $matchingSecond->no_asiento, $content);
        $this->assertStringContainsString("'=FORMULA", $content);
        $this->assertStringNotContainsString('No debe exportarse', $content);
        $this->assertStringNotContainsString('Otra empresa', $content);
        $this->assertCount(3, preg_split('/\r\n|\r|\n/', trim($content)));
    }

    public function test_csv_export_rejects_an_invalid_date_range(): void
    {
        $response = $this->actingAs($this->user())
            ->getJson(route('contabilidad.movimiento-mayor.exportar-csv', [
                'empresa' => '168',
                'fecha_inicio' => '2026-08-02',
                'fecha_fin' => '2026-08-01',
            ]));

        $response->assertUnprocessable();
        $response->assertJsonPath('message', 'La fecha inicio no puede ser mayor que la fecha fin.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createEntradaDiario(array $attributes): EntradaDiario
    {
        $entradaDiario = EntradaDiario::query()->create(array_merge([
            'external_key' => 'entry-'.fake()->unique()->uuid(),
            'payload_hash' => hash('sha256', fake()->uuid()),
            'no_asiento' => fake()->unique()->numerify('ASIENTO-#####'),
            'company_id' => '168',
            'fecha' => '2026-08-01',
            'fecha_raw' => '2026-08-01',
            'ref' => 'DIARIO',
            'no_ref' => fake()->numerify('REF-#####'),
            'cuenta' => '5101',
            'debito' => 125.50,
            'credito' => 0,
            'descripcion' => 'Movimiento de prueba',
            'id_centro_costo' => 'CC-01',
        ], $attributes));

        DB::table('entradas_diario')
            ->where('id', $entradaDiario->id)
            ->update(['fecha' => $attributes['fecha'] ?? '2026-08-01']);

        return $entradaDiario->refresh();
    }

    private function user(): User
    {
        $user = new User([
            'name' => 'Usuario de prueba',
            'email' => 'movimiento@example.com',
            'must_change_password' => false,
        ]);
        $user->id = 1;
        $user->exists = true;

        return $user;
    }
}
