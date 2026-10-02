<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class BiProductGroupsTest extends TestCase
{
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
