import test from 'node:test';
import assert from 'node:assert/strict';
import { compareHourlySales } from './hourly-comparison.js';

test('shows positive and negative differences against the same hour', () => {
    assert.deepEqual(compareHourlySales(120, 100), { percentage: 20, direction: 'above', label: '+20.0%' });
    assert.deepEqual(compareHourlySales(80, 100), { percentage: -20, direction: 'below', label: '-20.0%' });
    assert.deepEqual(compareHourlySales(100, 100), { percentage: 0, direction: 'equal', label: '0.0%' });
});

test('omits a comparison when the prior reading is missing or zero', () => {
    assert.equal(compareHourlySales(100, undefined), null);
    assert.equal(compareHourlySales(100, 0), null);
});
