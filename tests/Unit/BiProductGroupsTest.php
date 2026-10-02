<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class BiProductGroupsTest extends TestCase
{
    public function test_deletion_requires_confirmation_and_resets_only_the_matching_form(): void
    {
        $script = <<<'JS'
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const source = readFileSync(process.argv[1], 'utf8');
const handler = source.slice(source.indexOf('function eliminarLimite(row)'), source.indexOf('function seleccionarProductoLimite()'));
const pending = { value: null };
const saved = { value: true };
let resetCount = 0;
let clearedCount = 0;
const form = { processing: false, producto_id: 'grupo:tradicional', alcance: 'terminal', terminal: '100', reset() { resetCount++; }, clearErrors() { clearedCount++; } };
let accepted = false;
let prompt = '';
let calls = [];
const window = { confirm(message) { prompt = message; return accepted; } };
const router = { delete(url, options) { calls.push({ url, options }); } };
const eliminarLimite = new Function('eliminandoLimite', 'limiteForm', 'limiteGuardado', 'router', 'window', handler + 'return eliminarLimite;')(pending, form, saved, router, window);
const row = { id: 12, nombre: 'Total Tradicionales', producto_id: 'grupo:tradicional', alcance: 'terminal', terminal: '100', eliminarUrl: '/bi/limites-productos/12' };
eliminarLimite(row);
assert.equal(calls.length, 0);
assert.equal(pending.value, null);
assert.match(prompt, /Total Tradicionales.*Terminal 100/);
accepted = true;
eliminarLimite(row);
assert.equal(calls.length, 1);
assert.equal(calls[0].url, row.eliminarUrl);
assert.equal(calls[0].options.preserveScroll, true);
assert.equal(pending.value, 12);
assert.equal(saved.value, false);
eliminarLimite(row);
assert.equal(calls.length, 1);
calls[0].options.onSuccess();
assert.equal(resetCount, 1);
assert.equal(clearedCount, 1);
assert.equal(saved.value, true);
calls[0].options.onFinish();
assert.equal(pending.value, null);
form.terminal = '200';
eliminarLimite(row);
calls[1].options.onSuccess();
assert.equal(resetCount, 1);
calls[1].options.onFinish();
form.processing = true;
eliminarLimite(row);
assert.equal(calls.length, 2);
form.processing = false;
form.alcance = 'global';
eliminarLimite({ ...row, alcance: 'global', terminal: '' });
assert.match(prompt, /Global — Consorcio/);
calls[2].options.onSuccess();
assert.equal(resetCount, 2);
calls[2].options.onFinish();
JS;

        $process = new Process([
            'node', '--input-type=module', '--eval', $script,
            dirname(__DIR__, 2).'/resources/js/bi/pages/Bi/Dashboard.vue',
        ], dirname(__DIR__, 2));
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }

    public function test_product_selector_keeps_individual_products_and_selects_each_category_total(): void
    {
        $script = <<<'JS'
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { compile } from '@vue/compiler-ssr';
const source = readFileSync(process.argv[1], 'utf8');
const template = source.match(/<select id="limite-producto"[\s\S]*?<\/select>/)[0];
const { code } = compile(template, { mode: 'function' });
const ssrRender = new Function('require', code)(createRequire(import.meta.url));
const gruposDisponibles = [
    { producto_id: 'grupo:tradicional', descripcion: 'Total Tradicionales' },
    { producto_id: 'grupo:no_tradicional', descripcion: 'Total No tradicionales' },
];
async function render(productoId, productosDisponibles = [{ producto_id: '43', descripcion: 'Quiniela Loteka' }]) {
    return renderToString(createSSRApp({
        ssrRender,
        setup: () => ({ gruposDisponibles, productosDisponibles, limiteForm: { producto_id: productoId }, seleccionarProductoLimite() {} }),
    }));
}
for (const grupo of gruposDisponibles) {
    const html = await render(grupo.producto_id);
    assert.match(html, /<optgroup label="Totales por tipo">/);
    assert.match(html, /<optgroup label="Productos individuales">/);
    assert.match(html, /value="43"[^>]*>Quiniela Loteka \(43\)/);
    assert.ok(html.includes(`value="${grupo.producto_id}" selected>${grupo.descripcion}</option>`), html);
}
assert.match(await render('43'), /value="43" selected/);
const emptyCatalog = await render('grupo:tradicional', []);
assert.match(emptyCatalog, /value="grupo:tradicional" selected/);
assert.doesNotMatch(emptyCatalog, /label="Productos individuales"/);
JS;

        $process = new Process([
            'node', '--input-type=module', '--eval', $script,
            dirname(__DIR__, 2).'/resources/js/bi/pages/Bi/Dashboard.vue',
        ], dirname(__DIR__, 2));
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }
}
