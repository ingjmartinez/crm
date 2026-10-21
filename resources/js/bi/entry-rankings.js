export function rankEntries(rows) {
    const days = new Map();

    for (const row of rows) {
        if (row.minutos_entrada === null || row.minutos_entrada === undefined) continue;
        const key = `${row.cedula}|${row.fecha}`;
        const previous = days.get(key);
        if (!previous || row.primer_login < previous.primer_login) days.set(key, row);
    }

    const employees = new Map();
    for (const row of days.values()) {
        const employee = employees.get(row.cedula) || {
            cedula: row.cedula, nombre: row.nombre, dias: 0, puntuales: 0, tardanzas: 0,
            minutosTemprano: 0, minutosTarde: 0,
        };
        employee.dias++;
        if (row.minutos_entrada <= 0) {
            employee.puntuales++;
            employee.minutosTemprano += Math.abs(row.minutos_entrada);
        } else {
            employee.tardanzas++;
            employee.minutosTarde += row.minutos_entrada;
        }
        employees.set(row.cedula, employee);
    }

    const ranked = [...employees.values()].map((employee) => ({
        ...employee,
        porcentajePuntual: employee.puntuales / employee.dias,
        promedioTemprano: employee.puntuales ? Math.round(employee.minutosTemprano / employee.puntuales) : 0,
        promedioTarde: employee.tardanzas ? Math.round(employee.minutosTarde / employee.tardanzas) : 0,
    }));

    return {
        mejores: [...ranked].sort((a, b) => b.porcentajePuntual - a.porcentajePuntual || b.puntuales - a.puntuales || b.promedioTemprano - a.promedioTemprano || a.nombre.localeCompare(b.nombre)).slice(0, 25),
        peores: [...ranked].sort((a, b) => a.porcentajePuntual - b.porcentajePuntual || b.tardanzas - a.tardanzas || b.promedioTarde - a.promedioTarde || a.nombre.localeCompare(b.nombre)).slice(0, 25),
    };
}

export function paginateEntries(rows, page, pageSize = 25) {
    const pageCount = Math.max(1, Math.ceil(rows.length / pageSize));
    const currentPage = Math.min(Math.max(1, page), pageCount);

    return {
        pageCount,
        currentPage,
        rows: rows.slice((currentPage - 1) * pageSize, currentPage * pageSize),
    };
}
