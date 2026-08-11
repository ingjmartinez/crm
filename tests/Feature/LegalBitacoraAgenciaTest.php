<?php

namespace Tests\Feature;

use App\Http\Middleware\ExpireInactiveSession;
use App\Http\Middleware\ForcePasswordChange;
use App\Models\Agencia;
use App\Models\LegalContrato;
use App\Models\LegalObligacion;
use App\Models\LegalPagoProgramado;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegalBitacoraAgenciaTest extends TestCase
{
    /** @var array<int, Migration> */
    private array $migraciones = [];

    private bool $creoTablaUsuarios = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
            });
            $this->creoTablaUsuarios = true;
        }

        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('agencia')->nullable();
            $table->string('nombre_agencia')->nullable();
            $table->string('terminal')->nullable();
            $table->string('empresa')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('ruta')->nullable();
            $table->unsignedTinyInteger('estatus')->default(1);
            $table->timestamps();
        });

        foreach ([
            '2026_08_07_140043_create_legal_contratos_table.php',
            '2026_08_07_140046_create_legal_obligacions_table.php',
            '2026_08_07_140049_create_legal_pago_programados_table.php',
        ] as $archivo) {
            $migracion = require database_path("migrations/{$archivo}");
            $migracion->up();
            $this->migraciones[] = $migracion;
        }
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->migraciones) as $migracion) {
            $migracion->down();
        }

        Schema::dropIfExists('agencias');

        if ($this->creoTablaUsuarios) {
            Schema::dropIfExists('users');
        }

        parent::tearDown();
    }

    public function test_publica_el_modulo_legal_y_la_tarjeta_bitacora_de_agencia(): void
    {
        $item = collect(config('module_hubs.legal.items'))->firstWhere('nombre', 'Bitácora de agencia');
        $layout = file_get_contents(resource_path('views/app.blade.php'));

        $this->assertNotNull($item);
        $this->assertSame('/legal/bitacora-agencias', $item['url']);
        $this->assertIsString($layout);
        $this->assertStringContainsString("route('legal.index')", $layout);
    }

    public function test_lista_y_busca_las_terminales_desde_el_catalogo_de_agencias(): void
    {
        $this->crearAgencia('5501001', 'Agencia Central');
        $this->crearAgencia('5501002', 'Agencia Norte');

        $this->sinMiddlewareDeAcceso()
            ->get(route('legal.bitacora-agencias.index', ['buscar' => '5501002']))
            ->assertOk()
            ->assertSee('5501002')
            ->assertSee('Agencia Norte')
            ->assertDontSee('5501001');
    }

    public function test_registra_contrato_pdf_y_genera_el_calendario_mensual(): void
    {
        Storage::fake('local');
        $agencia = $this->crearAgencia('5501001', 'Agencia Central');

        $this->sinMiddlewareDeAcceso()
            ->post(route('legal.contratos.store', $agencia), [
                'titulo' => 'Contrato de alquiler del local',
                'numero_contrato' => 'LEG-001',
                'contraparte' => 'Juan Propietario',
                'fecha_inicio' => '2026-01-01',
                'fecha_fin' => '2026-04-30',
                'estado' => 'activo',
                'documento_pdf' => UploadedFile::fake()->create('contrato-local.pdf', 100, 'application/pdf'),
                'obligacion_tipo' => 'local',
                'obligacion_descripcion' => 'Alquiler mensual',
                'monto' => '25000.00',
                'frecuencia' => 'mensual',
                'fecha_primer_pago' => '2026-01-15',
                'fecha_fin_pagos' => '2026-04-15',
            ])
            ->assertRedirect(route('legal.bitacora-agencias.show', $agencia))
            ->assertSessionHas('success');

        $contrato = LegalContrato::query()->firstOrFail();
        $obligacion = LegalObligacion::query()->firstOrFail();

        Storage::disk('local')->assertExists($contrato->documento_path);
        $this->assertSame($agencia->id, $contrato->agencia_id);
        $this->assertSame('local', $obligacion->tipo);
        $this->assertSame(25000.0, (float) $obligacion->monto);
        $this->assertSame([
            '2026-01-15',
            '2026-02-15',
            '2026-03-15',
            '2026-04-15',
        ], LegalPagoProgramado::query()->orderBy('fecha_vencimiento')->pluck('fecha_vencimiento')->map(
            fn (mixed $fecha): string => substr((string) $fecha, 0, 10)
        )->all());

        $this->sinMiddlewareDeAcceso()
            ->get(route('legal.bitacora-agencias.show', $agencia))
            ->assertOk()
            ->assertSee('Contrato de alquiler del local')
            ->assertSee('Alquiler mensual')
            ->assertSee('25,000.00');
    }

    public function test_agrega_otra_obligacion_y_genera_pagos_trimestrales_sin_duplicados(): void
    {
        $agencia = $this->crearAgencia('5501001', 'Agencia Central');
        $contrato = LegalContrato::factory()->for($agencia)->create([
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-08-31',
        ]);

        $payload = [
            'tipo' => 'internet',
            'descripcion' => 'Servicio de fibra',
            'monto' => '4500.00',
            'frecuencia' => 'trimestral',
            'fecha_primer_pago' => '2026-01-31',
            'fecha_fin' => '2026-08-31',
        ];

        $this->sinMiddlewareDeAcceso()
            ->post(route('legal.obligaciones.store', $contrato), $payload)
            ->assertRedirect(route('legal.bitacora-agencias.show', $agencia));

        $obligacion = LegalObligacion::query()->firstOrFail();

        $this->assertSame([
            '2026-01-31',
            '2026-04-30',
            '2026-07-31',
        ], $obligacion->pagosProgramados()->orderBy('fecha_vencimiento')->pluck('fecha_vencimiento')->map(
            fn (mixed $fecha): string => substr((string) $fecha, 0, 10)
        )->all());
    }

    public function test_sirve_el_pdf_desde_el_almacenamiento_privado(): void
    {
        Storage::fake('local');
        $agencia = $this->crearAgencia('5501001', 'Agencia Central');
        $contrato = LegalContrato::factory()->for($agencia)->create([
            'documento_path' => 'legal/contratos/1/contrato.pdf',
            'documento_nombre_original' => 'contrato.pdf',
        ]);
        Storage::disk('local')->put($contrato->documento_path, '%PDF-1.4 contrato');

        $this->sinMiddlewareDeAcceso()
            ->get(route('legal.contratos.documento', $contrato))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function crearAgencia(string $terminal, string $nombre): Agencia
    {
        return Agencia::query()->create([
            'agencia' => $terminal,
            'nombre_agencia' => $nombre,
            'terminal' => $terminal,
            'empresa' => 'GRUPO JOSELITO',
            'ciudad' => 'Santo Domingo',
            'ruta' => 'GJ RUTA CENTRAL',
            'estatus' => 1,
        ]);
    }

    private function sinMiddlewareDeAcceso(): static
    {
        return $this->withoutMiddleware([
            Authenticate::class,
            ForcePasswordChange::class,
            ExpireInactiveSession::class,
        ]);
    }
}
