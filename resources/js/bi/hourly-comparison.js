export function compareHourlySales(currentTotal, previousTotal) {
    if (!Number.isFinite(currentTotal) || !Number.isFinite(previousTotal) || previousTotal <= 0) {
        return null;
    }

    const percentage = ((currentTotal - previousTotal) / previousTotal) * 100;

    return {
        percentage,
        direction: percentage > 0 ? 'above' : percentage < 0 ? 'below' : 'equal',
        label: `${percentage > 0 ? '+' : ''}${percentage.toFixed(1)}%`,
    };
}
