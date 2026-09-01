<?php

namespace Tests\Feature;

use App\Http\Middleware\ExpireInactiveSession;
use App\Http\Middleware\ForcePasswordChange;
use App\Mail\VolantePagoSocioMail;
use App\Models\VolantePagoSocioCarga;
use App\Models\VolantePagoSocioDetalle;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContabilidadVolantePagoSocioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            Authenticate::class,
            ForcePasswordChange::class,
            ExpireInactiveSession::class,
        ]);

        Schema::create('volante_pago_socio_cargas', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_archivo');
            $table->string('hash_archivo')->nullable();
            $table->string('banco')->default(VolantePagoSocioCarga::BANCO_SANTA_CRUZ);
            $table->string('empresa_origen');
            $table->string('rnc_origen')->nullable();
            $table->string('cuenta_origen');
            $table->string('tipo_transaccion');
            $table->string('estado');
            $table->decimal('monto_total', 15, 2);
            $table->dateTime('fecha_transaccion');
            $table->date('fecha_correspondiente')->nullable();
            $table->unsignedInteger('cantidad_transacciones');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();
        });

        Schema::create('volante_pago_socio_detalles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('carga_id');
            $table->unsignedInteger('numero_linea');
            $table->string('nombre');
            $table->string('tipo_identificacion');
            $table->string('identificacion');
            $table->string('cuenta');
            $table->string('tipo_cuenta');
            $table->decimal('monto', 15, 2);
            $table->string('estado');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('volante_pago_socio_detalles');
        Schema::dropIfExists('volante_pago_socio_cargas');
        parent::tearDown();
    }

    public function test_reporte_esta_disponible_en_contabilidad(): void
    {
        $this->assertTrue(Route::has('contabilidad.volantes-pago-socios'));
        $this->assertFileExists(public_path('images/banco-santa-cruz-volantes.png'));
        $this->assertFileExists(public_path('images/banreservas-logo-volantes.png'));

        $item = collect(config('module_hubs.contabilidad.items'))
            ->firstWhere('nombre', 'Volantes de Pago de Socios');

        $this->assertSame('/contabilidad/volantes-pago-socios', $item['url']);
    }

    public function test_carga_csv_y_crea_una_fila_por_transaccion(): void
    {
        $response = $this->post(route('contabilidad.volantes-pago-socios.procesar'), [
            'banco' => VolantePagoSocioCarga::BANCO_BANRESERVAS,
            'archivo_csv' => UploadedFile::fake()->createWithContent('pagos.csv', $this->csvValido()),
            'fecha_correspondiente' => '2026-08-15',
        ]);

        $carga = \App\Models\VolantePagoSocioCarga::query()->with('detalles')->sole();
        $response->assertRedirect(route('contabilidad.volantes-pago-socios'));
        $this->assertSame(2, $carga->detalles->count());
        $this->assertSame(VolantePagoSocioCarga::BANCO_BANRESERVAS, $carga->banco);
        $this->assertSame('CONSORCIO DE BANCAS JOSELITO E.I.R.L.', $carga->empresa_origen);
        $this->assertSame('131329888', $carga->rnc_origen);
        $this->assertSame('******3411', $carga->detalles->first()->cuenta);
        $this->assertSame('80700.00', $carga->monto_total);
        $this->assertSame('2026-08-15', $carga->fecha_correspondiente->toDateString());

        $this->get(route('contabilidad.volantes-pago-socios', [
            'nombre' => 'Aramis',
            'fecha_desde' => '2026-08-14',
            'fecha_hasta' => '2026-08-16',
        ]))
            ->assertOk()
            ->assertSee('Aramis Morel Arroyo')
            ->assertSee('Ver volante')
            ->assertSee('Compartir PDF')
            ->assertSee('Enviar por correo')
            ->assertSee('Buscar volantes')
            ->assertSee('Escribe el nombre del socio')
            ->assertSee('Volantes guardados')
            ->assertSee('Banreservas')
            ->assertSee('15/08/2026')
            ->assertSee('bootstrap.Modal.getOrCreateInstance', false)
            ->assertSee("event.target.closest('.btn-ver-volante')", false)
            ->assertSee('prepareShareFile()', false)
            ->assertSee('navigator.share({', false)
            ->assertDontSee("addEventListener('click', async function", false);
    }

    public function test_rechaza_archivo_cuyo_total_no_coincide(): void
    {
        $csv = str_replace('Monto: RD$80700.00', 'Monto: RD$90000.00', $this->csvValido());

        $this->from(route('contabilidad.volantes-pago-socios'))
            ->post(route('contabilidad.volantes-pago-socios.procesar'), [
                'banco' => VolantePagoSocioCarga::BANCO_SANTA_CRUZ,
                'archivo_csv' => UploadedFile::fake()->createWithContent('pagos.csv', $csv),
                'fecha_correspondiente' => '2026-08-15',
            ])
            ->assertRedirect(route('contabilidad.volantes-pago-socios'))
            ->assertSessionHasErrors('archivo_csv');
    }

    public function test_exige_la_fecha_correspondiente_al_guardar(): void
    {
        $this->from(route('contabilidad.volantes-pago-socios'))
            ->post(route('contabilidad.volantes-pago-socios.procesar'), [
                'banco' => VolantePagoSocioCarga::BANCO_SANTA_CRUZ,
                'archivo_csv' => UploadedFile::fake()->createWithContent('pagos.csv', $this->csvValido()),
            ])
            ->assertRedirect(route('contabilidad.volantes-pago-socios'))
            ->assertSessionHasErrors('fecha_correspondiente');

        $this->assertDatabaseCount('volante_pago_socio_cargas', 0);
    }

    public function test_consulta_volantes_guardados_por_nombre_y_fecha(): void
    {
        $cargaEsperada = VolantePagoSocioCarga::factory()->create([
            'usuario_id' => null,
            'nombre_archivo' => 'volantes-15.csv',
            'fecha_correspondiente' => '2026-08-15',
        ]);
        VolantePagoSocioDetalle::factory()->create([
            'carga_id' => $cargaEsperada->id,
            'nombre' => 'Aramis Morel Arroyo',
        ]);
        $otraCarga = VolantePagoSocioCarga::factory()->create([
            'usuario_id' => null,
            'nombre_archivo' => 'volantes-20.csv',
            'fecha_correspondiente' => '2026-08-20',
        ]);
        VolantePagoSocioDetalle::factory()->create([
            'carga_id' => $otraCarga->id,
            'nombre' => 'Leiry Saba De León',
        ]);

        $this->get(route('contabilidad.volantes-pago-socios'))
            ->assertOk()
            ->assertSee('Utiliza el panel de búsqueda para consultar los volantes guardados.')
            ->assertDontSee('Aramis Morel Arroyo')
            ->assertDontSee('Leiry Saba De León');

        $this->get(route('contabilidad.volantes-pago-socios', [
            'nombre' => 'Aramis',
            'fecha_desde' => '2026-08-14',
            'fecha_hasta' => '2026-08-16',
        ]))
            ->assertOk()
            ->assertSee('Aramis Morel Arroyo')
            ->assertSee('volantes-15.csv')
            ->assertSee('15/08/2026')
            ->assertDontSee('Leiry Saba De León')
            ->assertDontSee('volantes-20.csv');
    }

    public function test_rechaza_un_rango_de_fechas_invertido(): void
    {
        $this->get(route('contabilidad.volantes-pago-socios', [
            'fecha_desde' => '2026-08-20',
            'fecha_hasta' => '2026-08-15',
        ]))
            ->assertSessionHasErrors('fecha_hasta');
    }

    public function test_genera_pdf_individual_y_lo_envia_por_correo(): void
    {
        Mail::fake();
        $carga = VolantePagoSocioCarga::factory()->create([
            'usuario_id' => null,
            'banco' => VolantePagoSocioCarga::BANCO_SANTA_CRUZ,
            'fecha_correspondiente' => '2026-08-15',
        ]);
        $detalle = VolantePagoSocioDetalle::factory()->create([
            'nombre' => 'Socio Prueba',
            'carga_id' => $carga->id,
        ]);

        $this->get(route('contabilidad.volantes-pago-socios.vista-previa', $detalle))
            ->assertOk()
            ->assertSee('Socio Prueba')
            ->assertSee('Datos de la Transacción')
            ->assertSee('Fecha correspondiente')
            ->assertSee('15/08/2026')
            ->assertSee('Banco Santa Cruz')
            ->assertSee('data:image/png;base64,', false);

        $this->get(route('contabilidad.volantes-pago-socios.pdf', $detalle))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->post(route('contabilidad.volantes-pago-socios.correo', $detalle), ['correo' => 'socio@example.com'])
            ->assertSessionHasNoErrors();

        Mail::assertSent(VolantePagoSocioMail::class, function (VolantePagoSocioMail $mail): bool {
            return $mail->hasTo('socio@example.com') && count($mail->attachments()) === 1;
        });
    }

    public function test_genera_volante_con_identidad_de_banreservas(): void
    {
        $carga = VolantePagoSocioCarga::factory()->create([
            'usuario_id' => null,
            'banco' => VolantePagoSocioCarga::BANCO_BANRESERVAS,
            'fecha_correspondiente' => '2026-08-20',
        ]);
        $detalle = VolantePagoSocioDetalle::factory()->create([
            'nombre' => 'Socio Banreservas',
            'carga_id' => $carga->id,
        ]);

        $this->get(route('contabilidad.volantes-pago-socios.vista-previa', $detalle))
            ->assertOk()
            ->assertSee('Socio Banreservas')
            ->assertSee('alt="Banreservas"', false)
            ->assertSee('4-01-01006-2')
            ->assertSee('809-960-2121')
            ->assertSee('data:image/png;base64,', false)
            ->assertSee('bank-logo-crop', false);

        $this->get(route('contabilidad.volantes-pago-socios.pdf', $detalle))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function csvValido(): string
    {
        return <<<'CSV'
Cuenta Origen: CONSORCIO DE BANCAS JOSELITO E.I.R.L. (131329888)
Cuenta Origen: 11171000001425
Tipo Transacción: Pago a Suplidores
Estado: Completado
Monto: RD$80700.00
Fecha: 14/08/2026 11:48:31 AM

Nombres,Tipo Identificación,No. Identificación,No. Cuenta,Tipo Cuenta,Monto,Estado,
"Aramis Morel Arroyo","Cédula","001-1852552-6","******3411","Cuenta Corriente","RD$6500.00","Completada",
"Leiry A. Saba De León","Cédula","001-1642962-2","******0011","Cuenta de Ahorro","RD$74200.00","Completada",
CSV;
    }
}
