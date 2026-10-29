(function (global) {
    const productos = ['Tradicional', 'No Tradicional', 'Recarga'];

    function filtrar(datos, filtros) {
        const seleccionados = new Set(filtros.productos);
        const todos = productos.every((producto) => seleccionados.has(producto));
        const todosLosPremios = seleccionados.has('Tradicional') && seleccionados.has('No Tradicional');
        const productosPremios = new Set(['Tradicional', 'No Tradicional']
            .filter((producto) => seleccionados.has(producto))
            .flatMap((producto) => datos.productosPremios[producto]));
        const agencias = datos.agencias.filter((agencia) =>
            (!filtros.empresa || agencia.empresa === filtros.empresa)
            && (!filtros.ruta || agencia.ruta === filtros.ruta)
            && (filtros.alcance !== 'terminal' || agencia.terminal === filtros.terminal.trim()));
        const resumen = {
            terminales: agencias.length,
            terminales_balance_positivo: 0,
            terminales_balance_negativo: 0,
            venta: 0, premios: 0, premios_sacados: 0, ingreso_real: 0, costos: 0, gastos: 0, balance: 0,
        };
        const series = productos.map((producto) => ({ name: producto, data: datos.meses.map(() => 0) }));
        const indicesMeses = new Map(datos.meses.map((mes, indice) => [mes, indice]));
        const mensuales = Object.fromEntries(['venta', 'premios', 'premios_sacados', 'ingreso_real', 'costos', 'gastos', 'balance']
            .map((campo) => [campo, datos.meses.map(() => 0)]));
        const sumarPremios = (filas) => filas.reduce((total, fila) =>
            total + (todosLosPremios || productosPremios.has(fila.producto_id) ? fila.total : 0), 0);

        agencias.forEach((agencia) => {
            let ventas = 0;
            agencia.ventas.forEach((fila) => {
                if (todos || seleccionados.has(fila.tipo)) {
                    ventas += fila.total;
                    const indice = productos.indexOf(fila.tipo);
                    const mes = indicesMeses.get(fila.mes);
                    if (indice !== -1 && mes !== undefined) {
                        series[indice].data[mes] += fila.total;
                    }
                    if (mes !== undefined) {
                        mensuales.venta[mes] += fila.total;
                    }
                }
            });
            const premios = sumarPremios(agencia.premios);
            const ingreso = ventas - premios;
            const balance = ingreso - agencia.costos - agencia.gastos;
            resumen.venta += ventas;
            resumen.premios += premios;
            resumen.premios_sacados += sumarPremios(agencia.sacados);
            resumen.ingreso_real += ingreso;
            resumen.costos += agencia.costos;
            resumen.gastos += agencia.gastos;
            resumen.balance += balance;
            resumen.terminales_balance_positivo += balance > 0 ? 1 : 0;
            resumen.terminales_balance_negativo += balance < 0 ? 1 : 0;
            ['premios', 'sacados'].forEach((campo) => {
                agencia[campo].forEach((fila) => {
                    const mes = indicesMeses.get(fila.mes);
                    if (mes !== undefined && (todosLosPremios || productosPremios.has(fila.producto_id))) {
                        mensuales[campo === 'sacados' ? 'premios_sacados' : 'premios'][mes] += fila.total;
                    }
                });
            });
            agencia.gastosMensuales.forEach((fila) => {
                const mes = indicesMeses.get(fila.mes);
                if (mes !== undefined) {
                    mensuales.costos[mes] += fila.costos;
                    mensuales.gastos[mes] += fila.gastos;
                }
            });
        });

        datos.meses.forEach((mes, indice) => {
            mensuales.ingreso_real[indice] = mensuales.venta[indice] - mensuales.premios[indice];
            mensuales.balance[indice] = mensuales.ingreso_real[indice] - mensuales.costos[indice] - mensuales.gastos[indice];
        });
        const graficos = Object.fromEntries([
            ['premios_sacados', 'Premios sacados'], ['premios', 'Premios propios pagados'],
            ['ingreso_real', 'Ingreso real'], ['gastos', 'Gastos'], ['balance', 'Balance'],
        ].map(([campo, nombre]) => [campo, [{ name: nombre, data: mensuales[campo] }]]));
        const pagos = (filtros.pagos ?? [60]).filter((pago) => [60, 70, 80].includes(Number(pago)));
        graficos.meta_venta = pagos.map((pago) => ({
            name: 'Pago ' + pago + ' %', data: mensuales.gastos.map((gasto) => gasto / (1 - Number(pago) / 100)),
        }));
        graficos.ventas_por_dia = graficos.meta_venta.map((serie) => ({
            name: serie.name,
            data: serie.data.map((meta, indice) => {
                const [anio, mes] = datos.meses[indice].split('-').map(Number);
                const dias = new Date(Date.UTC(anio, mes, 0)).getUTCDate();
                return meta / dias;
            }),
        }));

        return {
            resumen,
            graficos,
            ventasPorProducto: Object.fromEntries(series.map((serie) => [serie.name, serie.data.reduce((suma, venta) => suma + venta, 0)])),
            ventasMensuales: { meses: datos.meses, etiquetas: datos.etiquetas, series },
        };
    }

    function combinarMeses(acumulado, siguiente) {
        if (!acumulado) {
            return siguiente;
        }
        const agencias = new Map(acumulado.agencias.map((agencia) => [agencia.terminal, agencia]));
        siguiente.agencias.forEach((agencia) => {
            const existente = agencias.get(agencia.terminal);
            if (!existente) {
                agencias.set(agencia.terminal, agencia);
                return;
            }
            existente.costos += agencia.costos;
            existente.gastos += agencia.gastos;
            ['ventas', 'premios', 'sacados', 'gastosMensuales'].forEach((campo) => {
                existente[campo].push(...agencia[campo]);
            });
        });
        acumulado.meses.push(...siguiente.meses);
        acumulado.etiquetas.push(...siguiente.etiquetas);
        acumulado.agencias = [...agencias.values()];
        return acumulado;
    }

    async function cargarMeses(meses, obtenerMes, progreso) {
        const mesesOrdenados = [...meses].sort();
        if (mesesOrdenados.length === 0) {
            return null;
        }
        const controlador = new AbortController();
        const resultados = new Array(mesesOrdenados.length);
        let siguiente = 0;
        let completados = 0;
        let fallo = false;
        let errorCarga;
        const cargarSiguiente = async () => {
            while (!fallo && siguiente < mesesOrdenados.length) {
                const indice = siguiente++;
                const mes = mesesOrdenados[indice];
                try {
                    const datos = await obtenerMes(mes, controlador.signal);
                    if (fallo) {
                        return;
                    }
                    resultados[indice] = datos;
                    progreso(++completados, mesesOrdenados.length, mes);
                } catch (error) {
                    if (!fallo) {
                        fallo = true;
                        errorCarga = error;
                        controlador.abort();
                    }
                }
            }
        };
        progreso(0, mesesOrdenados.length, mesesOrdenados[0]);
        await Promise.all(Array.from({ length: Math.min(3, mesesOrdenados.length) }, cargarSiguiente));
        if (fallo) {
            throw errorCarga;
        }
        return resultados.reduce(combinarMeses, null);
    }

    global.FiltrosGraficoRentabilidad = { filtrar, cargarMeses };
})(globalThis);
