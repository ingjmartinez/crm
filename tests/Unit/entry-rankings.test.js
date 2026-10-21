import assert from 'node:assert/strict';
import test from 'node:test';
import { paginateEntries, rankEntries, rankLiveEntries } from '../../resources/js/bi/entry-rankings.js';

test('ranks employees across days and counts a day with two systems once', () => {
    const rows = [
        { cedula: '1', nombre: 'Ana', fecha: '2026-10-10', primer_login: '2026-10-10 07:45:00', minutos_entrada: -15 },
        { cedula: '1', nombre: 'Ana', fecha: '2026-10-10', primer_login: '2026-10-10 08:05:00', minutos_entrada: 5 },
        { cedula: '1', nombre: 'Ana', fecha: '2026-10-11', primer_login: '2026-10-11 08:00:00', minutos_entrada: 0 },
        { cedula: '2', nombre: 'Luis', fecha: '2026-10-10', primer_login: '2026-10-10 08:20:00', minutos_entrada: 20 },
        { cedula: '3', nombre: 'Sin ponche', fecha: '2026-10-10', primer_login: null, minutos_entrada: null },
    ];
    const result = rankEntries(rows);

    assert.deepEqual(result.mejores.map((employee) => employee.cedula), ['1', '2']);
    assert.deepEqual(result.peores.map((employee) => employee.cedula), ['2', '1']);
    assert.equal(result.mejores[0].dias, 2);
    assert.equal(result.mejores[0].porcentajePuntual, 1);
    assert.equal(result.peores[0].promedioTarde, 20);
});

test('each ranking is limited to 25 employees', () => {
    const rows = Array.from({ length: 30 }, (_, index) => ({
        cedula: String(index), nombre: `Empleado ${index}`, fecha: '2026-10-10',
        primer_login: `2026-10-10 08:${String(index).padStart(2, '0')}:00`, minutos_entrada: index,
    }));

    assert.equal(rankEntries(rows).mejores.length, 25);
    assert.equal(rankEntries(rows).peores.length, 25);
});

test('pagination shows 25 rows and keeps the last page in range', () => {
    const rows = Array.from({ length: 53 }, (_, index) => index);

    assert.deepEqual(paginateEntries(rows, 1).rows, rows.slice(0, 25));
    assert.deepEqual(paginateEntries(rows, 2).rows, rows.slice(25, 50));
    assert.deepEqual(paginateEntries(rows, 3).rows, rows.slice(50));
    assert.equal(paginateEntries(rows, 8).currentPage, 3);
});

test('live ranking separates early and late terminals and excludes those without a punch', () => {
    const rows = [
        { terminal: '1', minutos_entrada: -20 },
        { terminal: '2', minutos_entrada: 0 },
        { terminal: '3', minutos_entrada: 15 },
        { terminal: '4', minutos_entrada: null },
        { terminal: '5', minutos_entrada: -5 },
        { terminal: '6', minutos_entrada: 30 },
    ];

    assert.deepEqual(rankLiveEntries(rows).mejores.map((row) => row.terminal), ['1', '5', '2']);
    assert.deepEqual(rankLiveEntries(rows).peores.map((row) => row.terminal), ['6', '3']);
});
