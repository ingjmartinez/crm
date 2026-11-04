<?php

namespace Tests\Feature;

use App\Models\Premio;
use App\Services\Lotobet\LotobetSessionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PremiosLotobetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        Schema::create('premios_bet', function (Blueprint $table): void {
            $table->id('premio_id');
            $table->string('consorcio_id')->nullable();
            $table->string('agencia_id')->nullable();
            $table->string('producto_id')->nullable();
            $table->string('descripcion')->nullable();
            $table->decimal('monto', 15, 2)->nullable();
            $table->date('fecha')->nullable();
        });
    }

    public function test_guarda_y_consulta_premios_y_no_repite_la_fecha(): void
    {
        $payload = ['code' => 200, 'Content' => [['agencia_id' => '0051', 'monto' => 150, 'fecha' => '2026-11-03']]];
        $this->mock(LotobetSessionService::class)->shouldReceive('getPremios')->with('2026-11-03')->twice()->andReturn($payload);
        $this->getJson('/get-premios-lotobet?fecha=2026-11-03')->assertOk()->assertJsonPath('code', 0)->assertJsonPath('premios.0.monto', 150);
        $this->getJson('/save-premios-lotobet?fecha=2026-11-03')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/save-premios-lotobet?fecha=2026-11-03')->assertOk()->assertJsonPath('message', 'Ya hay data guardada en la fecha: 2026-11-03');
        $this->assertSame(1, Premio::query()->count());
        $this->assertSame('0051', Premio::query()->first()->agencia_id);
    }

    public function test_error_del_api_devuelve_json_sin_guardar_ni_exponer_html(): void
    {
        $this->mock(LotobetSessionService::class)->shouldReceive('getPremios')->twice()->andThrow(new RuntimeException('LotoBet no devolvió una lista válida de premios.'));
        foreach (['get', 'save'] as $accion) {
            $this->getJson('/'.$accion.'-premios-lotobet?fecha=2026-11-03')
                ->assertStatus(502)->assertJsonPath('error', 'LotoBet no devolvió una lista válida de premios.');
        }
        $this->assertSame(0, Premio::query()->count());
    }

    public function test_lista_vacia_es_valida_y_fecha_invalida_no_consulta_api(): void
    {
        $this->mock(LotobetSessionService::class)->shouldReceive('getPremios')->once()->andReturn(['Content' => []]);
        $this->getJson('/save-premios-lotobet?fecha=2026-11-03')->assertOk()->assertJsonPath('total', 0);
        foreach (['get', 'save'] as $accion) {
            $this->getJson('/'.$accion.'-premios-lotobet?fecha=incorrecta')->assertUnprocessable()->assertJsonValidationErrors('fecha');
        }
    }

    public function test_javascript_muestra_errores_legibles_para_html_json_y_sesion_vencida(): void
    {
        $codigo = <<<'JS'
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
global.window = {};
let respuesta;
global.fetch = async (url, opciones) => {
    assert.equal(opciones.headers.Accept, 'application/json');
    return respuesta;
};
vm.runInThisContext(fs.readFileSync(process.argv[2], 'utf8'));
(async () => {
    respuesta = {ok: false, status: 500, async json(){throw new SyntaxError('<!DOCTYPE html>');}};
    await assert.rejects(window.consultarPremiosJson('/save'), /HTTP 500/);
    respuesta = {ok: false, status: 502, async json(){return {error: 'LotoBet no devolvió premios válidos.'};}};
    await assert.rejects(window.consultarPremiosJson('/save'), /no devolvió premios válidos/);
    respuesta = {ok: false, status: 422, async json(){return {errors: {fecha: ['Seleccione una fecha.']}};}};
    await assert.rejects(window.consultarPremiosJson('/save'), /Seleccione una fecha/);
    respuesta = {ok: true, redirected: true, status: 200, async json(){throw new SyntaxError('HTML login');}};
    await assert.rejects(window.consultarPremiosJson('/save'), /sesión venció/);
    respuesta = {ok: true, status: 200, async json(){return {code: 0, total: 0};}};
    assert.deepEqual(await window.consultarPremiosJson('/save'), {code: 0, total: 0});
    respuesta = {ok: false, status: 500, async json(){return {error: '<html>Error</html>'};}};
    await assert.rejects(window.consultarPremiosJson('/save'), /HTTP 500/);
})().catch(error => {console.error(error);process.exitCode = 1;});
JS;
        $process = new Process(['node', '-', public_path('js/pages/premios-lotobet-response.js')]);
        $process->setInput($codigo)->setTimeout(15)->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }
}
