<?php

namespace Tests\Feature;

use App\Http\Middleware\ExpireInactiveSession;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\PreventDeletionForAdmin2;
use App\Mail\SolicitudTerminalMail;
use App\Models\SolicitudTerminal;
use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SolicitudTerminalTest extends TestCase
{
    /** @var array<int, Migration> */
    private array $migraciones = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventDeletionForAdmin2::class);

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('must_change_password')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal')->nullable();
        });

        Schema::create('zonas_geograficas', function (Blueprint $table): void {
            $table->id();
            $table->string('region', 80);
            $table->string('provincia', 100);
            $table->string('municipio', 120);
            $table->string('ciudad_seccion', 160);
            $table->string('sector_barrio_paraje', 190);
            $table->char('jerarquia_hash', 64)->unique();
            $table->timestamps();
        });

        foreach ([
            '2026_09_05_131517_create_solicitudes_terminales_table.php',
            '2026_09_05_131518_create_solicitud_terminal_codigos_table.php',
            '2026_09_23_155008_add_formulario_fields_to_solicitud_terminal_codigos_table.php',
            '2026_09_23_161838_expand_longitud_precision_on_solicitud_terminal_codigos_table.php',
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
        Schema::dropIfExists('zonas_geograficas');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_module_is_published_and_index_counts_existing_terminals(): void
    {
        $this->insertarAgencia('05330001');
        $this->insertarAgencia('05330002');
        $this->insertarAgencia('05330002');
        $this->insertarAgencia(null);

        $item = collect(config('module_hubs.mantenimiento.items'))
            ->firstWhere('nombre', 'Solicitud de Terminales');

        $this->assertNotNull($item);
        $this->assertSame('/mantenimiento/solicitudes-terminales', $item['url']);

        $this->withoutMiddleware([
            Authenticate::class,
            ExpireInactiveSession::class,
            ForcePasswordChange::class,
        ])
            ->get(route('mantenimiento.solicitudes-terminales.index'))
            ->assertOk()
            ->assertSee('Solicitud de Terminales')
            ->assertSee('Terminales creadas')
            ->assertSee('Correo o correos destinatarios')
            ->assertSee('document.body.appendChild(modalCorreoElemento);', false)
            ->assertSee('2');
    }

    public function test_preview_generates_unique_codes_and_avoids_agencies_and_reserved_codes(): void
    {
        $user = User::factory()->create();
        $this->insertarAgencia('05330001');
        $solicitud = $this->crearSolicitud($user, '33', 1);
        $solicitud->codigos()->create(['codigo' => '05330002', 'estado' => 'pendiente']);

        $response = $this->actingAs($user)->postJson(
            route('mantenimiento.solicitudes-terminales.preview'),
            ['prefijo_seleccionado' => '33', 'cantidad' => 20]
        );

        $response->assertOk()->assertJsonCount(20, 'codigos');

        $codigos = $response->json('codigos');

        $this->assertCount(20, array_unique($codigos));
        $this->assertNotContains('05330001', $codigos);
        $this->assertNotContains('05330002', $codigos);

        foreach ($codigos as $codigo) {
            $this->assertMatchesRegularExpression('/^0533\d{4}$/', $codigo);
        }
    }

    public function test_user_can_create_request_track_partial_approval_and_download_pdf(): void
    {
        $user = User::factory()->create();
        $codigos = ['05331234', '05335678', '05339012'];

        $this->actingAs($user)
            ->post(route('mantenimiento.solicitudes-terminales.store'), [
                'prefijo_seleccionado' => '33',
                'cantidad' => 3,
                'codigos' => $codigos,
            ])
            ->assertRedirect(route('mantenimiento.solicitudes-terminales.index'));

        $solicitud = SolicitudTerminal::query()->firstOrFail();
        $this->assertSame('GJ-000001', $solicitud->numero);
        $this->assertSame('pendiente', $solicitud->estado);
        $this->assertSame(3, $solicitud->codigos()->count());

        $primerCodigo = $solicitud->codigos()->where('codigo', $codigos[0])->firstOrFail();

        $this->actingAs($user)
            ->put(route('mantenimiento.solicitudes-terminales.aprobaciones', $solicitud), [
                'codigos_aprobados' => [$primerCodigo->id],
            ])
            ->assertRedirect();

        $this->assertSame('parcial', $solicitud->fresh()->estado);
        $this->assertDatabaseHas('solicitud_terminal_codigos', [
            'id' => $primerCodigo->id,
            'estado' => 'aprobado',
            'aprobado_por' => $user->id,
        ]);
        $this->assertSame(2, $solicitud->codigos()->where('estado', 'pendiente')->count());

        $archivoParcial = storage_path('app/private/solicitudes-terminales/Solicitud de agencia loteka #1.xlsx');
        $hojaParcial = IOFactory::load($archivoParcial)->getSheet(0);
        $this->assertSame('Pendiente', $hojaParcial->getCell('B3')->getValue());
        $this->assertSame('FFFCE4D6', $hojaParcial->getStyle('B3')->getFill()->getStartColor()->getARGB());

        $this->actingAs($user)
            ->put(route('mantenimiento.solicitudes-terminales.aprobaciones', $solicitud), [
                'codigos_aprobados' => $solicitud->codigos()->pluck('id')->all(),
            ])
            ->assertRedirect();

        $this->assertSame('aprobada', $solicitud->fresh()->estado);
        $this->assertSame(3, $solicitud->codigos()->where('estado', 'aprobado')->count());

        $this->actingAs($user)
            ->get(route('mantenimiento.solicitudes-terminales.pdf', $solicitud))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)
            ->get(route('mantenimiento.solicitudes-terminales.excel', $solicitud))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $archivo = storage_path('app/private/solicitudes-terminales/Solicitud de agencia loteka #1.xlsx');
        $hoja = IOFactory::load($archivo)->getSheet(0);
        $this->assertSame('Id', $hoja->getCell('A1')->getValue());
        $this->assertSame('Estatus', $hoja->getCell('B1')->getValue());
        $this->assertSame('Aprobada', $hoja->getCell('B2')->getValue());
        $this->assertSame('FFC6EFCE', $hoja->getStyle('B2')->getFill()->getStartColor()->getARGB());
        $this->assertFalse($hoja->getProtection()->getSheet());
        $this->assertSame('Grupo Joselito', $hoja->getCell('C2')->getValue());
        $this->assertSame($codigos[0], $hoja->getCell('E2')->getValue());
        $this->assertSame($codigos[2], $hoja->getCell('E4')->getValue());
        Storage::disk('local')->delete('solicitudes-terminales/Solicitud de agencia loteka #1.xlsx');
    }

    public function test_request_rejects_existing_codes_and_invalid_prefixes(): void
    {
        $user = User::factory()->create();
        $this->insertarAgencia('05331234');

        $this->actingAs($user)
            ->from(route('mantenimiento.solicitudes-terminales.index'))
            ->post(route('mantenimiento.solicitudes-terminales.store'), [
                'prefijo_seleccionado' => '33',
                'cantidad' => 1,
                'codigos' => ['05331234'],
            ])
            ->assertRedirect(route('mantenimiento.solicitudes-terminales.index'))
            ->assertSessionHasErrors('codigos');

        $this->actingAs($user)
            ->post(route('mantenimiento.solicitudes-terminales.store'), [
                'prefijo_seleccionado' => '44',
                'cantidad' => 1,
                'codigos' => ['05339999'],
            ])
            ->assertSessionHasErrors('codigos');

        $this->assertDatabaseCount('solicitudes_terminales', 0);
    }

    public function test_user_can_update_form_data_with_geographic_hierarchy(): void
    {
        $user = User::factory()->create();
        $solicitud = SolicitudTerminal::query()->create([
            'solicitado_por' => $user->id,
            'prefijo_empresa' => '05',
            'prefijo_seleccionado' => '33',
            'cantidad' => 1,
            'estado' => 'pendiente',
        ]);
        $codigo = $solicitud->codigos()->create([
            'codigo' => '05331234',
            'estado' => 'pendiente',
        ]);

        Schema::getConnection()->table('zonas_geograficas')->insert([
            'region' => 'Ozama',
            'provincia' => 'Distrito Nacional',
            'municipio' => 'Santo Domingo de Guzman',
            'ciudad_seccion' => 'Zona Urbana',
            'sector_barrio_paraje' => 'Gazcue',
            'jerarquia_hash' => hash('sha256', 'zona-prueba'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->put(route('mantenimiento.solicitudes-terminales.datos.update', $solicitud), [
                'codigos' => [[
                    'id' => $codigo->id,
                    'nombre_banca' => 'Agencia Principal',
                    'region' => 'Ozama',
                    'provincia' => 'Distrito Nacional',
                    'municipio' => 'Santo Domingo de Guzman',
                    'ciudad' => 'Zona Urbana',
                    'sector' => 'Gazcue',
                    'calle' => 'Calle Central',
                    'direccion_local' => 'Local 1',
                    'latitud' => '18.4763890',
                    'longitud' => '-50.77777777',
                    'rja' => 'RJA-100',
                ]],
            ])
            ->assertRedirect(route('mantenimiento.solicitudes-terminales.index', ['page' => 1]));

        $this->assertDatabaseHas('solicitud_terminal_codigos', [
            'id' => $codigo->id,
            'region' => 'Ozama',
            'provincia' => 'Distrito Nacional',
            'municipio' => 'Santo Domingo de Guzman',
            'ciudad' => 'Zona Urbana',
            'sector' => 'Gazcue',
            'longitud' => '-50.77777777',
        ]);

        $this->actingAs($user)
            ->getJson(route('mantenimiento.solicitudes-terminales.datos', $solicitud))
            ->assertOk()
            ->assertJsonPath('codigos.0.nombre_banca', 'Agencia Principal')
            ->assertJsonPath('codigos.0.region', 'Ozama');

        $archivo = storage_path('app/private/solicitudes-terminales/Solicitud de agencia loteka #'.$solicitud->id.'.xlsx');
        $hoja = IOFactory::load($archivo)->getSheet(0);
        $this->assertSame('Agencia Principal', $hoja->getCell('D2')->getValue());
        $this->assertSame('Ozama', $hoja->getCell('F2')->getValue());
        $this->assertSame('Gazcue', $hoja->getCell('J2')->getValue());
        Storage::disk('local')->delete('solicitudes-terminales/Solicitud de agencia loteka #'.$solicitud->id.'.xlsx');

        $this->actingAs($user)
            ->from(route('mantenimiento.solicitudes-terminales.index'))
            ->put(route('mantenimiento.solicitudes-terminales.datos.update', $solicitud), [
                'codigos' => [[
                    'id' => $codigo->id,
                    'region' => 'Ozama',
                    'provincia' => 'Provincia inexistente',
                ]],
            ])
            ->assertRedirect(route('mantenimiento.solicitudes-terminales.index'))
            ->assertSessionHasErrors('codigos.0.region');

        $this->actingAs($user)
            ->from(route('mantenimiento.solicitudes-terminales.index'))
            ->put(route('mantenimiento.solicitudes-terminales.datos.update', $solicitud), [
                'codigos' => [[
                    'id' => $codigo->id,
                    'latitud' => '184763890',
                    'longitud' => '50.77777777',
                ]],
            ])
            ->assertRedirect(route('mantenimiento.solicitudes-terminales.index'))
            ->assertSessionHasErrors([
                'codigos.0.latitud',
                'codigos.0.longitud',
            ]);
    }

    public function test_repeated_confirmation_does_not_duplicate_the_request_or_show_a_conflict(): void
    {
        $user = User::factory()->create();
        $datos = [
            'prefijo_seleccionado' => '33',
            'cantidad' => 2,
            'codigos' => ['05331234', '05335678'],
        ];

        $this->actingAs($user)
            ->post(route('mantenimiento.solicitudes-terminales.store'), $datos)
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('mantenimiento.solicitudes-terminales.store'), $datos)
            ->assertRedirect(route('mantenimiento.solicitudes-terminales.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'La solicitud GJ-000001 ya había sido creada correctamente.');

        $this->assertDatabaseCount('solicitudes_terminales', 1);
        $this->assertDatabaseCount('solicitud_terminal_codigos', 2);
    }

    public function test_approval_cannot_include_a_code_from_another_request(): void
    {
        $user = User::factory()->create();
        $primera = $this->crearSolicitud($user, '33', 1);
        $segunda = $this->crearSolicitud($user, '44', 1);
        $primera->codigos()->create(['codigo' => '05330001', 'estado' => 'pendiente']);
        $codigoAjeno = $segunda->codigos()->create(['codigo' => '05440001', 'estado' => 'pendiente']);

        $this->actingAs($user)
            ->put(route('mantenimiento.solicitudes-terminales.aprobaciones', $primera), [
                'codigos_aprobados' => [$codigoAjeno->id],
            ])
            ->assertSessionHasErrors('codigos_aprobados');

        $this->assertSame('pendiente', $codigoAjeno->fresh()->estado);
        $this->assertSame('pendiente', $primera->fresh()->estado);
    }

    public function test_pdf_uses_the_required_company_and_requesting_department(): void
    {
        $user = User::factory()->create();
        $solicitud = $this->crearSolicitud($user, '33', 1);
        $solicitud->codigos()->create(['codigo' => '05330001', 'estado' => 'pendiente']);
        $solicitud->load(['solicitante', 'codigos']);

        $html = view('mantenimiento.solicitudes-terminales.pdf', compact('solicitud'))->render();

        $this->assertStringContainsString('Grupo Joselito', $html);
        $this->assertStringContainsString('Tecnología Joselito', $html);
        $this->assertStringNotContainsString($user->name, $html);
    }

    public function test_pdf_remains_a_formal_request_without_internal_approval_statuses(): void
    {
        $user = User::factory()->create();
        $solicitud = $this->crearSolicitud($user, '33', 2);
        $solicitud->codigos()->create([
            'codigo' => '05330001',
            'estado' => 'aprobado',
            'aprobado_por' => $user->id,
            'aprobado_at' => now(),
        ]);
        $solicitud->codigos()->create(['codigo' => '05330002', 'estado' => 'pendiente']);
        $solicitud->load(['solicitante', 'codigos']);

        $html = view('mantenimiento.solicitudes-terminales.pdf', compact('solicitud'))->render();

        $this->assertStringContainsString('05330001', $html);
        $this->assertStringContainsString('05330002', $html);
        $this->assertStringNotContainsString('Estado interno', $html);
        $this->assertStringNotContainsString('aprobado', $html);
        $this->assertStringNotContainsString('pendiente', $html);
    }

    public function test_user_can_email_the_formal_request_to_multiple_valid_recipients(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $solicitud = $this->crearSolicitud($user, '33', 1);
        $solicitud->codigos()->create(['codigo' => '05330001', 'estado' => 'pendiente']);

        $this->actingAs($user)
            ->post(route('mantenimiento.solicitudes-terminales.correo', $solicitud), [
                'solicitud_correo_id' => $solicitud->id,
                'correos' => "CENTRAL@LOTEKA.COM; tecnologia@grupojoselito.com\notro@ejemplo.com",
            ])
            ->assertRedirect(route('mantenimiento.solicitudes-terminales.index'))
            ->assertSessionHasNoErrors();

        Mail::assertSent(SolicitudTerminalMail::class, function (SolicitudTerminalMail $mail) use ($solicitud): bool {
            return $mail->solicitud->is($solicitud)
                && $mail->hasTo('central@loteka.com')
                && $mail->hasTo('tecnologia@grupojoselito.com')
                && $mail->hasTo('otro@ejemplo.com')
                && count($mail->attachments()) === 1;
        });
    }

    public function test_invalid_email_address_is_rejected_without_sending(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $solicitud = $this->crearSolicitud($user, '33', 1);
        $solicitud->codigos()->create(['codigo' => '05330001', 'estado' => 'pendiente']);

        $this->actingAs($user)
            ->post(route('mantenimiento.solicitudes-terminales.correo', $solicitud), [
                'solicitud_correo_id' => $solicitud->id,
                'correos' => 'correo-invalido',
            ])
            ->assertSessionHasErrors('destinatarios.0');

        Mail::assertNothingSent();
    }

    private function crearSolicitud(User $user, string $prefijo, int $cantidad): SolicitudTerminal
    {
        return SolicitudTerminal::query()->create([
            'solicitado_por' => $user->id,
            'prefijo_empresa' => '05',
            'prefijo_seleccionado' => $prefijo,
            'cantidad' => $cantidad,
            'estado' => 'pendiente',
        ]);
    }

    private function insertarAgencia(?string $terminal): void
    {
        Schema::getConnection()->table('agencias')->insert(['terminal' => $terminal]);
    }
}
