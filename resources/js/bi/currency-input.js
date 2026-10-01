export function normalizeCurrencyInput(value) {
    const cleaned = String(value ?? '').replace(/,/g, '').replace(/[^\d.]/g, '');
    if (!cleaned) return '';

    const [integer, ...fractions] = cleaned.split('.');
    const whole = integer.replace(/^0+(?=\d)/, '') || '0';
    return fractions.length ? `${whole}.${fractions.join('').slice(0, 2)}` : whole;
}

export function formatCurrencyInput(value, complete = false) {
    const normalized = normalizeCurrencyInput(value);
    if (!normalized) return '';

    const [integer, fraction] = normalized.split('.');
    const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    if (complete) return `${grouped}.${(fraction || '').padEnd(2, '0')}`;
    return fraction === undefined ? grouped : `${grouped}.${fraction}`;
}

export function currencyInputCaret(value, caret) {
    const meaningful = value.slice(0, caret).replace(/[^\d.]/g, '').length;
    if (meaningful === 0) return 0;
    const formatted = formatCurrencyInput(value);
    let count = 0;
    for (let index = 0; index < formatted.length; index++) {
        if (/[\d.]/.test(formatted[index])) count++;
        if (count === meaningful) return index + 1;
    }
    return formatted.length;
}
