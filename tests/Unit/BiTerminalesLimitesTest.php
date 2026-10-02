<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class BiTerminalesLimitesTest extends TestCase
{
    public function test_counts_unique_terminals_at_or_above_eighty_percent_of_active_limits(): void
    {
        $script = <<<'JS'
import assert from 'node:assert/strict';
import { pathToFileURL } from 'node:url';
const { limitesTerminalesAl80, cantidadTerminalesAl80 } = await import(pathToFileURL(process.argv[1]).href);
const row = (terminal, ventas, monto = 100, extra = {}) => ({ alcance: 'terminal', terminal, ventas, monto, activo: true, ...extra });
const rows = [
    row('100', 80), row('100', 120), row('200', 79.99), row('300', 160, 200),
    row('400', null), row('500', 90, 100, { activo: false }),
    row('', 90), row('600', 90, 100, { alcance: 'global' }),
];
assert.deepEqual(limitesTerminalesAl80(rows).map((item) => item.terminal), ['100', '100', '300']);
assert.equal(cantidadTerminalesAl80(rows), 2);
assert.equal(cantidadTerminalesAl80([]), 0);
JS;

        $process = new Process(['node', '--input-type=module', '--eval', $script, dirname(__DIR__, 2).'/resources/js/bi/terminales-limites.js']);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }
}
