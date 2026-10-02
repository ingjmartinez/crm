export function limitesTerminalesAl80(alertasProductos) {
    return alertasProductos.filter((row) => row.alcance === 'terminal'
        && row.terminal && row.activo && row.ventas !== null
        && Number(row.monto) > 0 && Number(row.ventas) >= Number(row.monto) * 0.8);
}

export function cantidadTerminalesAl80(alertasProductos) {
    return new Set(limitesTerminalesAl80(alertasProductos).map((row) => row.terminal)).size;
}
