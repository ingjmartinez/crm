<?php

namespace Tests\Feature;

use App\Http\Controllers\ContabilidadClasificacionCuentaController;
use App\Models\CuentaContable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContabilidadClasificacionCuentaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('cuentas_contables', function (Blueprint $table): void {
            $table->id();
            $table->string('cuenta')->unique();
            $table->string('descripcion');
            $table->string('ctacontrol')->nullable();
            $table->string('tipo')->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_10_23_171204_create_clasificacion_cuentas_table.php'))->up();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('clasificacion_cuentas');
        Schema::dropIfExists('cuentas_contables');

        parent::tearDown();
    }

    public function test_hub_card_uses_the_existing_account_catalog_permission(): void
    {
        $item = collect(config('module_hubs.contabilidad.items'))
            ->firstWhere('nombre', 'Clasificación de cuenta');

        $this->assertNotNull($item);
        $this->assertSame('/contabilidad/clasificacion-cuentas', $item['url']);
        $this->assertSame('contabilidad.inicio.view', $item['permission']);
        $this->assertCount(73, config('clasificacion_cuentas.cuentas'));
        $this->assertTrue(Route::has('contabilidad.clasificacion-cuentas'));
        $this->assertTrue(view()->exists('contabilidad.clasificacion-cuentas'));
    }

    public function test_table_reads_account_names_from_the_catalog_and_preserves_image_criteria(): void
    {
        CuentaContable::query()->create(['cuenta' => '600110001', 'descripcion' => 'Sueldos & Salarios']);
        CuentaContable::query()->create(['cuenta' => '6001226', 'descripcion' => 'Recogida De Basura']);
        CuentaContable::query()->create(['cuenta' => '600220001', 'descripcion' => 'Impuesto Ir-20']);

        $view = app(ContabilidadClasificacionCuentaController::class)->index();
        $cuentas = $view->getData()['cuentas'];
        $basura = $cuentas->firstWhere('cuenta', '6001226');
        $impuesto = $cuentas->firstWhere('cuenta', '600220001');

        $this->assertSame('contabilidad.clasificacion-cuentas', $view->name());
        $this->assertCount(73, $cuentas);
        $this->assertSame('Sueldos & Salarios', $cuentas->firstWhere('cuenta', '600110001')['nombre']);
        $this->assertSame('VARIABLE', $basura['clasificacion']);
        $this->assertSame('Estructura recurrente necesaria para operar', $basura['criterio']);
        $this->assertSame('FIJO', $impuesto['clasificacion']);
        $this->assertStringStartsWith('Excluida del gasto contable', $impuesto['criterio']);
        $this->assertSame('EXCLUIDA; sustituida por R20 estándar', $impuesto['observacion']);
        $this->assertContains('600110002', $view->getData()['cuentasFaltantes']);
    }

    public function test_account_can_be_added_removed_and_added_again(): void
    {
        CuentaContable::query()->create(['cuenta' => '999001', 'descripcion' => 'Cuenta nueva']);
        $datos = ['cuenta' => '999001', 'clasificacion' => 'FIJO', 'criterio' => 'FIJO', 'observacion' => 'Recurrente'];

        $this->withoutMiddleware()->post(route('contabilidad.clasificacion-cuentas.store'), $datos)
            ->assertRedirect(route('contabilidad.clasificacion-cuentas'));
        $this->assertDatabaseHas('clasificacion_cuentas', ['cuenta' => '999001', 'activo' => 1, 'clasificacion' => 'FIJO']);
        $view = app(ContabilidadClasificacionCuentaController::class)->index();
        $this->assertCount(74, $view->getData()['cuentas']);
        $this->assertSame('Cuenta nueva', $view->getData()['cuentas']->firstWhere('cuenta', '999001')['nombre']);
        $this->assertFalse($view->getData()['cuentasDisponibles']->contains('cuenta', '999001'));

        $this->delete(route('contabilidad.clasificacion-cuentas.destroy', '999001'))
            ->assertRedirect(route('contabilidad.clasificacion-cuentas'));
        $this->assertDatabaseHas('clasificacion_cuentas', ['cuenta' => '999001', 'activo' => 0]);
        $view = app(ContabilidadClasificacionCuentaController::class)->index();
        $this->assertCount(73, $view->getData()['cuentas']);
        $this->assertTrue($view->getData()['cuentasDisponibles']->contains('cuenta', '999001'));

        $this->post(route('contabilidad.clasificacion-cuentas.store'), $datos)
            ->assertRedirect(route('contabilidad.clasificacion-cuentas'));
        $this->assertDatabaseHas('clasificacion_cuentas', ['cuenta' => '999001', 'activo' => 1]);
    }

    public function test_base_account_can_be_removed_and_reclassified(): void
    {
        CuentaContable::query()->create(['cuenta' => '600110001', 'descripcion' => 'Sueldos']);

        $this->withoutMiddleware()->delete(route('contabilidad.clasificacion-cuentas.destroy', '600110001'))
            ->assertRedirect(route('contabilidad.clasificacion-cuentas'));
        $this->assertNull(app(ContabilidadClasificacionCuentaController::class)->index()
            ->getData()['cuentas']->firstWhere('cuenta', '600110001'));

        $this->post(route('contabilidad.clasificacion-cuentas.store'), [
            'cuenta' => '600110001', 'clasificacion' => 'VARIABLE', 'criterio' => 'VARIABLE',
        ])->assertRedirect(route('contabilidad.clasificacion-cuentas'));
        $this->assertSame('VARIABLE', app(ContabilidadClasificacionCuentaController::class)->index()
            ->getData()['cuentas']->firstWhere('cuenta', '600110001')['clasificacion']);
    }

    public function test_addition_rejects_unknown_duplicate_and_invalid_classification(): void
    {
        CuentaContable::query()->create(['cuenta' => '600110001', 'descripcion' => 'Sueldos']);
        CuentaContable::query()->create(['cuenta' => '999001', 'descripcion' => 'Cuenta nueva']);

        $this->withoutMiddleware()->from(route('contabilidad.clasificacion-cuentas'))
            ->post(route('contabilidad.clasificacion-cuentas.store'), [
                'cuenta' => '999999', 'clasificacion' => 'FIJO', 'criterio' => 'FIJO',
            ])->assertSessionHasErrors('cuenta');
        $this->post(route('contabilidad.clasificacion-cuentas.store'), [
            'cuenta' => '600110001', 'clasificacion' => 'FIJO', 'criterio' => 'FIJO',
        ])->assertSessionHasErrors('cuenta');
        $this->post(route('contabilidad.clasificacion-cuentas.store'), [
            'cuenta' => '999001', 'clasificacion' => 'OTRA', 'criterio' => 'FIJO',
        ])->assertSessionHasErrors('clasificacion');
        $this->assertDatabaseCount('clasificacion_cuentas', 0);
    }
}
