<?php

namespace Tests\Feature;

use App\Models\PagoAOtraEmpresa;
use App\Models\PagoAOtraEmpresaNet;
use App\Models\PagoPorOtraEmpresa;
use App\Models\PagoPorOtraEmpresaNet;
use App\Models\Premio;
use App\Models\PremioNet;
use App\Models\User;
use App\ViewPermissionCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReporteCuadrePremiosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();

        foreach ([Premio::class, PremioNet::class, PagoAOtraEmpresa::class, PagoAOtraEmpresaNet::class, PagoPorOtraEmpresa::class, PagoPorOtraEmpresaNet::class] as $modelo) {
            Schema::create((new $modelo)->getTable(), function (Blueprint $table): void {
                $table->id();
                $table->dateTime('fecha');
                $table->decimal('monto', 15, 2)->nullable();
            });
        }
    }

    public static function sistemas(): array
    {
        return [
            'Lotobet' => ['Lotobet', Premio::class, PagoAOtraEmpresa::class, PagoPorOtraEmpresa::class, PremioNet::class],
            'Lotonet' => ['Lotonet', PremioNet::class, PagoAOtraEmpresaNet::class, PagoPorOtraEmpresaNet::class, Premio::class],
        ];
    }

    #[DataProvider('sistemas')]
    public function test_agrupa_por_dia_y_fuente_sin_mezclar_sistemas(string $sistema, string $premios, string $aOtra, string $porOtra, string $otroSistema): void
    {
        $premios::query()->insert([
            ['fecha' => '2026-11-01 00:00:00', 'monto' => 100.25],
            ['fecha' => '2026-11-01 23:59:59', 'monto' => 25.50],
            ['fecha' => '2026-11-03 23:59:59', 'monto' => 7],
            ['fecha' => '2026-10-31 23:59:59', 'monto' => 999],
            ['fecha' => '2026-11-04 00:00:00', 'monto' => 999],
        ]);
        $aOtra::query()->insert(['fecha' => '2026-11-01 10:00:00', 'monto' => 0]);
        $porOtra::query()->insert(['fecha' => '2026-11-01 10:00:00', 'monto' => 40]);
        $otroSistema::query()->insert(['fecha' => '2026-11-02', 'monto' => 8000]);

        $this->getJson(route('reportes.cuadre-premios.list', ['sistema' => $sistema, 'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-03']))
            ->assertOk()
            ->assertJsonCount(4, 'resultados')
            ->assertJsonPath('resultados.0.premios_sacados', 125.75)
            ->assertJsonPath('resultados.0.pagados_a_otra_empresa', 0)
            ->assertJsonPath('resultados.0.pagados_por_otra_empresa', 40)
            ->assertJsonPath('resultados.1.fecha', '2026-11-02')
            ->assertJsonPath('resultados.1.premios_sacados', null)
            ->assertJsonPath('resultados.2.premios_sacados', 7)
            ->assertJsonPath('resultados.3.fecha', 'TOTAL')
            ->assertJsonPath('resultados.3.premios_sacados', 132.75)
            ->assertJsonPath('resultados.3.pagados_a_otra_empresa', 0)
            ->assertJsonPath('resultados.3.pagados_por_otra_empresa', 40)
            ->assertJsonPath('fechas_faltantes', [
                ['fecha' => '2026-11-02', 'fuentes' => ['premios_sacados', 'pagados_a_otra_empresa', 'pagados_por_otra_empresa']],
                ['fecha' => '2026-11-03', 'fuentes' => ['pagados_a_otra_empresa', 'pagados_por_otra_empresa']],
            ]);
    }

    public function test_un_dia_sin_registros_muestra_las_tres_fuentes_faltantes(): void
    {
        $this->getJson(route('reportes.cuadre-premios.list', ['sistema' => 'Lotobet', 'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-01']))
            ->assertOk()->assertJsonCount(2, 'resultados')
            ->assertJsonPath('resultados.0', ['fecha' => '2026-11-01', 'premios_sacados' => null, 'pagados_a_otra_empresa' => null, 'pagados_por_otra_empresa' => null])
            ->assertJsonCount(3, 'fechas_faltantes.0.fuentes')
            ->assertJsonPath('resultados.1.premios_sacados', 0);
    }

    public function test_valida_sistema_fechas_y_orden_del_rango(): void
    {
        $url = route('reportes.cuadre-premios.list');
        $this->getJson($url)->assertUnprocessable()->assertJsonValidationErrors(['sistema', 'fecha_inicio', 'fecha_fin']);
        $this->getJson($url.'?sistema=invalido&fecha_inicio=2026-02-30&fecha_fin=no-fecha')
            ->assertUnprocessable()->assertJsonValidationErrors(['sistema', 'fecha_inicio', 'fecha_fin']);
        $this->getJson($url.'?sistema=Lotobet&fecha_inicio=2026-11-03&fecha_fin=2026-11-01')
            ->assertUnprocessable()->assertJsonValidationErrors('fecha_fin');
    }

    public function test_tarjeta_y_vista_del_reporte(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        $usuario = Mockery::mock(User::class)->makePartial();
        $usuario->shouldReceive('can')->andReturnFalse();
        $usuario->shouldReceive('hasRole')->andReturnFalse();
        $usuario->shouldReceive('hasAnyRole')->andReturnFalse();
        $usuario->setRelation('viewPermissions', collect());
        $this->actingAs($usuario);
        Gate::before(fn (): bool => false);

        $tarjeta = collect(config('reportes'))->firstWhere('url', '/reportes-cuadre-premios');
        $this->assertSame('Cuadre de Premios', $tarjeta['nombre']);
        $this->assertSame('reportes.cuadre_de_premios.view', app(ViewPermissionCatalog::class)->permission('reportes', $tarjeta));
        $this->get(route('reportes.cuadre-premios'))->assertOk()->assertViewIs('reportes.cuadre-premios')
            ->assertSee('Premios sacados')->assertSee('Pagados a otra empresa')->assertSee('Pagados por otra empresa');
    }

    public function test_javascript_renderiza_ceros_ausencias_y_alertas_por_fuente(): void
    {
        $codigo = <<<'JS'
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const botones = {};
global.document = {getElementById(id) {return botones[id] ||= {classList: {add(){}, remove(){}}, addEventListener(){}};}};
let alerta;
global.Swal = {fire(opciones){alerta = opciones;}};
let script = fs.readFileSync(process.argv[2], 'utf8').split('<script>')[1].split('</script>')[0];
script = script.replace(/@json\(route\('reportes.cuadre-premios.list'\)\)/, '"/list"');
vm.runInThisContext(script);
assert.equal(mostrarMonto(0, 'display'), '0.00');
assert.equal(mostrarMonto(1234.5, 'display'), '1,234.50');
assert.match(mostrarMonto(null, 'display'), /Sin data/);
assert.equal(mostrarMonto(null, 'export'), 'Sin data');
assert.equal(botones.btnDiasFaltantes.disabled, true);
vm.runInThisContext(`fechasFaltantesGlobal = [{fecha: '2026-11-02', fuentes: ['pagados_por_otra_empresa']}]; actualizarBotonDiasFaltantes(true);`);
assert.equal(botones.btnDiasFaltantes.disabled, false);
mostrarDiasFaltantes({preventDefault(){}});
assert.match(alerta.html, /02\/11\/2026/);
assert.match(alerta.html, /Pagados por otra empresa/);
assert.doesNotMatch(alerta.html, /Premios sacados/);
vm.runInThisContext('fechasFaltantesGlobal = []; actualizarBotonDiasFaltantes(true);');
assert.equal(botones.btnDiasFaltantes.disabled, true);
assert.equal(botones.btnDiasFaltantes.textContent, 'No faltan días del rango');
JS;
        $process = new Process(['node', '-', resource_path('views/reportes/cuadre-premios.blade.php')]);
        $process->setInput($codigo)->setTimeout(15)->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }
}
