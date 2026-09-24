<?php

namespace Tests\Feature;

use App\Http\Middleware\ExpireInactiveSession;
use App\Http\Middleware\ForcePasswordChange;
use App\Models\ZonaGeografica;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ZonaGeograficaTest extends TestCase
{
    /** @var array<int, class-string> */
    private array $authMiddleware = [
        Authenticate::class,
        ExpireInactiveSession::class,
        ForcePasswordChange::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('zonas_geograficas', function (Blueprint $table): void {
            $table->id();
            $table->string('region');
            $table->string('provincia');
            $table->string('municipio');
            $table->string('ciudad_seccion');
            $table->string('sector_barrio_paraje');
            $table->char('jerarquia_hash', 64)->unique();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('zonas_geograficas');

        parent::tearDown();
    }

    public function test_module_publishes_the_geographic_zones_crud(): void
    {
        $item = collect(config('module_hubs.mantenimiento.items'))->firstWhere('nombre', 'Zonas Geográficas');

        $this->assertNotNull($item);
        $this->assertSame('/mantenimiento/zonas-geograficas', $item['url']);

        $this->withoutMiddleware($this->authMiddleware)
            ->get(route('mantenimiento.zonas-geograficas.index'))
            ->assertOk()
            ->assertSee('Zonas Geográficas')
            ->assertSee('Sector, barrio o paraje');
    }

    public function test_zone_can_be_created_updated_searched_and_deleted(): void
    {
        $datos = $this->datosZona();

        $this->withoutMiddleware($this->authMiddleware)
            ->post(route('mantenimiento.zonas-geograficas.store'), $datos)
            ->assertRedirect(route('mantenimiento.zonas-geograficas.index'));

        $zona = ZonaGeografica::query()->sole();
        $this->withoutMiddleware($this->authMiddleware)
            ->put(route('mantenimiento.zonas-geograficas.update', $zona), [
                ...$datos,
                'sector_barrio_paraje' => 'Palma Sola',
            ])
            ->assertRedirect();

        $this->withoutMiddleware($this->authMiddleware)
            ->get(route('mantenimiento.zonas-geograficas.index', ['buscar' => 'Palma Sola']))
            ->assertOk()
            ->assertSee('Palma Sola');

        $this->withoutMiddleware($this->authMiddleware)
            ->delete(route('mantenimiento.zonas-geograficas.destroy', $zona->fresh()))
            ->assertRedirect(route('mantenimiento.zonas-geograficas.index'));

        $this->assertDatabaseCount('zonas_geograficas', 0);
    }

    public function test_duplicate_hierarchy_is_rejected(): void
    {
        ZonaGeografica::query()->create($this->datosZona());

        $this->withoutMiddleware($this->authMiddleware)
            ->from(route('mantenimiento.zonas-geograficas.index'))
            ->post(route('mantenimiento.zonas-geograficas.store'), $this->datosZona())
            ->assertRedirect(route('mantenimiento.zonas-geograficas.index'))
            ->assertSessionHasErrors('sector_barrio_paraje');
    }

    public function test_cascade_options_are_filtered_by_parent_levels(): void
    {
        ZonaGeografica::query()->create($this->datosZona());
        ZonaGeografica::query()->create([
            ...$this->datosZona(),
            'municipio' => 'San Francisco de Macorís',
            'ciudad_seccion' => 'Cenoví',
            'sector_barrio_paraje' => 'El Canal',
        ]);

        $this->withoutMiddleware($this->authMiddleware)
            ->getJson(route('mantenimiento.zonas-geograficas.opciones', [
                'nivel' => 'ciudad_seccion',
                'region' => 'Cibao Nordeste',
                'provincia' => 'Duarte',
                'municipio' => 'Arenoso',
            ]))
            ->assertOk()
            ->assertExactJson(['Arenoso']);
    }

    public function test_migration_imports_the_excel_source_data(): void
    {
        Schema::drop('zonas_geograficas');
        $migration = require database_path('migrations/2026_09_23_125726_create_zona_geograficas_table.php');

        $migration->up();

        $this->assertDatabaseCount('zonas_geograficas', 12790);
        $this->assertDatabaseHas('zonas_geograficas', $this->datosZona());
    }

    /** @return array<string, string> */
    private function datosZona(): array
    {
        return [
            'region' => 'Cibao Nordeste',
            'provincia' => 'Duarte',
            'municipio' => 'Arenoso',
            'ciudad_seccion' => 'Arenoso',
            'sector_barrio_paraje' => 'Arenoso',
        ];
    }
}
