<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class BiCurrencyInputTest extends TestCase
{
    public function test_currency_input_formats_typing_pasting_decimals_and_preserves_the_saved_amount(): void
    {
        $script = <<<'JS'
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
const { formatCurrencyInput, normalizeCurrencyInput, currencyInputCaret } = await import(pathToFileURL(process.argv[1]).href);
assert.equal(formatCurrencyInput('100000'), '100,000');
assert.equal(formatCurrencyInput('100000', true), '100,000.00');
assert.equal(formatCurrencyInput('100000.5'), '100,000.5');
assert.equal(formatCurrencyInput('100000.5', true), '100,000.50');
assert.equal(formatCurrencyInput('0.01', true), '0.01');
assert.equal(formatCurrencyInput(''), '');
assert.equal(formatCurrencyInput('', true), '');
assert.equal(normalizeCurrencyInput('DOP 100,000.50'), '100000.50');
assert.equal(normalizeCurrencyInput('001000'), '1000');
assert.equal(normalizeCurrencyInput('.'), '0.');
assert.equal(normalizeCurrencyInput('100.123'), '100.12');
assert.equal(currencyInputCaret('1000', 4), 5);
assert.equal(currencyInputCaret('12,000', 0), 0);
assert.equal(currencyInputCaret('12,000', 2), 2);
const source = readFileSync(process.argv[2], 'utf8');
const handlers = source.slice(source.indexOf('async function actualizarMontoLimite'), source.indexOf('function guardarLimite'));
const form = { monto: '' };
const { actualizarMontoLimite, completarMontoLimite, borrarSeparadorMonto } = new Function(
    'limiteForm', 'nextTick', 'formatCurrencyInput', 'normalizeCurrencyInput', 'currencyInputCaret',
    handlers + 'return { actualizarMontoLimite, completarMontoLimite, borrarSeparadorMonto };'
)(form, async () => {}, formatCurrencyInput, normalizeCurrencyInput, currencyInputCaret);
const input = { value: '100000', selectionStart: 6, selectionEnd: 6, setSelectionRange(start, end) { this.selectionStart = start; this.selectionEnd = end; } };
await actualizarMontoLimite({ target: input });
assert.equal(input.value, '100,000');
assert.equal(input.selectionStart, 7);
assert.equal(form.monto, '100000');
completarMontoLimite({ target: input });
assert.equal(input.value, '100,000.00');
assert.equal(form.monto, '100000.00');
input.value = 'DOP 1,234.50'; input.selectionStart = input.selectionEnd = input.value.length;
await actualizarMontoLimite({ target: input });
assert.equal(input.value, '1,234.50');
assert.equal(form.monto, '1234.50');
input.value = '1,234'; input.selectionStart = input.selectionEnd = 2;
let prevented = false;
borrarSeparadorMonto({ target: input, key: 'Backspace', preventDefault() { prevented = true; } });
await Promise.resolve();
assert.equal(prevented, true);
assert.equal(input.value, '234');
assert.equal(form.monto, '234');
assert.equal(input.selectionStart, 0);
input.value = ''; input.selectionStart = input.selectionEnd = 0;
await actualizarMontoLimite({ target: input });
assert.equal(form.monto, '');
JS;

        $projectPath = dirname(__DIR__, 2);
        $process = new Process([
            'node', '--input-type=module', '--eval', $script,
            $projectPath.'/resources/js/bi/currency-input.js',
            $projectPath.'/resources/js/bi/pages/Bi/Dashboard.vue',
        ]);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }
}
