<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgenciaEvidenciaPdfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Schema::dropIfExists('agencias');
        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('agencia')->nullable();
            $table->string('terminal')->nullable();
            $table->string('nombre_agencia')->nullable();
            $table->string('empresa')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('ruta')->nullable();
            $table->unsignedTinyInteger('estatus')->default(1);
            $table->timestamps();
        });
    }

    public function test_it_downloads_the_inactive_agencies_evidence_as_pdf(): void
    {
        DB::table('agencias')->insert([
            'agencia' => '1001',
            'terminal' => '5001',
            'nombre_agencia' => 'Agencia de prueba',
            'empresa' => 'Joselito',
            'ciudad' => 'Santo Domingo',
            'ruta' => 'Ruta 1',
            'estatus' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withoutMiddleware()
            ->get(route('agencias.evidencia-pdf', ['tipo' => 'inactivas']));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload();

        $this->assertStringStartsWith('%PDF', (string) $response->getContent());

        $archivos = Storage::disk('local')->files('agencias/boletines');
        $this->assertCount(2, $archivos);

        $rutaPdf = collect($archivos)->first(fn (string $ruta): bool => str_ends_with($ruta, '.pdf'));
        $rutaMetadata = collect($archivos)->first(fn (string $ruta): bool => str_ends_with($ruta, '.json'));

        $this->assertNotNull($rutaPdf);
        $this->assertNotNull($rutaMetadata);
        Storage::disk('local')->assertExists([$rutaPdf, $rutaMetadata]);
        $this->assertSame(Storage::disk('local')->get($rutaPdf), $response->getContent());

        $metadata = json_decode(Storage::disk('local')->get($rutaMetadata), true);
        $this->assertSame('Agencias desactivadas', $metadata['titulo']);
        $this->assertSame(1, $metadata['total']);
        $this->assertSame(basename($rutaPdf), $metadata['archivo']);

        $this->withoutMiddleware()
            ->get(route('agencias.boletines.index'))
            ->assertOk()
            ->assertSee('Boletines de cambios de agencias')
            ->assertSee('Agencias desactivadas')
            ->assertSee('Ver PDF');

        $this->withoutMiddleware()
            ->get(route('agencias.boletines.ver', ['archivo' => basename($rutaPdf)]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_it_rejects_an_unknown_evidence_type(): void
    {
        $this->withoutMiddleware()
            ->getJson(route('agencias.evidencia-pdf', ['tipo' => 'desconocido']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tipo');
    }

    public function test_it_does_not_expose_a_missing_saved_bulletin(): void
    {
        $this->withoutMiddleware()
            ->get(route('agencias.boletines.ver', [
                'archivo' => 'boletin_cambios_inactivas_20260822_120000_1234abcd.pdf',
            ]))
            ->assertNotFound();
    }

    public function test_agencies_modal_exposes_the_adaptive_pdf_button(): void
    {
        $view = file_get_contents(resource_path('views/agencias/index.blade.php'));

        $this->assertIsString($view);
        $modalStart = strpos($view, 'id="inactivasModal"');
        $modalEnd = strpos($view, 'id="paraActualizarModal"');
        $this->assertNotFalse($modalStart);
        $this->assertNotFalse($modalEnd);

        $inactivasModal = substr($view, $modalStart, $modalEnd - $modalStart);
        $this->assertStringContainsString('id="btnDescargarEvidenciaInactivas"', $inactivasModal);
        $this->assertStringContainsString("route('agencias.boletines.index')", $inactivasModal);
        $this->assertStringContainsString("route('agencias.evidencia-pdf')", $view);
        $this->assertStringContainsString('encodeURIComponent(inactivasModo)', $view);
    }
}
