document.addEventListener('DOMContentLoaded', function () {
    const configuracion = window.rentabilidadV10;
    if (configuracion.ingresosPendientes) {
        setTimeout(() => {
            const formulario = document.getElementById('filtrosRentabilidadV10');
            const editado = Array.from(formulario.elements).some(campo => campo.tagName === 'SELECT'
                ? Array.from(campo.options).some(opcion => opcion.selected !== opcion.defaultSelected)
                : ['checkbox', 'radio'].includes(campo.type) ? campo.checked !== campo.defaultChecked : campo.value !== campo.defaultValue);
            if (!editado) window.location.reload();
        }, 15000);
    }
    const moneda = valor => new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(Number(valor || 0));
    const empresa = document.getElementById('empresaV10');
    const ruta = document.getElementById('rutaV10');
    const actualizarRutas = () => {
        const seleccionada = ruta.value;
        const rutas = [...new Set(configuracion.rutasDisponibles.filter(fila => !empresa.value || fila.empresa === empresa.value).map(fila => fila.ruta))];
        ruta.replaceChildren(new Option('Todas las rutas', ''), ...rutas.map(nombre => new Option(nombre, nombre)));
        ruta.value = rutas.includes(seleccionada) ? seleccionada : '';
    };
    empresa.addEventListener('change', actualizarRutas);
    actualizarRutas();
    const completo = document.getElementById('anioCompletoV10');
    const inicio = document.getElementById('mes_inicioV10');
    const fin = document.getElementById('mes_finV10');
    const actualizarAnio = () => {
        if (completo.checked) { inicio.value = '1'; fin.value = '12'; }
    };
    completo.addEventListener('change', actualizarAnio);
    [inicio, fin].forEach(select => select.addEventListener('change', () => { completo.checked = false; }));
    actualizarAnio();
    const alcance = document.getElementById('alcanceV10');
    const terminal = document.getElementById('terminalV10');
    const agrupacion = document.getElementById('agrupacionV10');
    const actualizarTerminal = () => {
        const porRuta = agrupacion.value === 'ruta';
        alcance.disabled = porRuta;
        terminal.disabled = porRuta || alcance.value !== 'terminal';
        terminal.required = !porRuta && alcance.value === 'terminal';
    };
    alcance.addEventListener('change', actualizarTerminal);
    agrupacion.addEventListener('change', actualizarTerminal);
    actualizarTerminal();
    let consultando = false;
    document.getElementById('filtrosRentabilidadV10').addEventListener('submit', function (event) {
        if (consultando) {
            event.preventDefault();
            return;
        }
        consultando = true;
        const boton = this.querySelector('button[type="submit"]');
        boton.disabled = true;
        boton.textContent = 'Consultando meses...';
        if (window.Swal) {
            event.preventDefault();
            const formulario = this;
            window.Swal.fire({
                title: 'Consultando datos...',
                text: 'Estamos preparando la comparación de los meses seleccionados.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    window.Swal.showLoading();
                    setTimeout(() => HTMLFormElement.prototype.submit.call(formulario), 50);
                },
            });
        }
    });
    window.addEventListener('pageshow', function () {
        if (consultando) window.Swal?.close();
        consultando = false;
        const boton = document.querySelector('#filtrosRentabilidadV10 button[type="submit"]');
        boton.disabled = false;
        boton.textContent = 'Consultar';
    });
    if (!configuracion.datos.length || !document.getElementById('tablaRentabilidadV10')) return;

    const mostrarErrorCarga = error => {
        const estado = document.getElementById('cargandoV10');
        estado.classList.remove('d-none');
        estado.classList.add('text-danger');
        estado.setAttribute('role', 'alert');
        estado.textContent = `No se pudo preparar la tabla: ${error.message}. Recarga la página para volver a intentar.`;
        console.error('Error al preparar rentabilidad V10:', error);
    };
    if (typeof window.jQuery !== 'function' || typeof window.jQuery.fn?.DataTable !== 'function') {
        mostrarErrorCarga(new Error('no se pudo cargar DataTables desde su servidor'));
        return;
    }

    const medidas = ['ventas', 'ingresos_calculados', 'porcentaje_ingresos', 'premios_sacados', 'utilidad', 'gastos', 'balance_general'];
    const etiquetas = ['Ventas', 'Ingresos calculado', '% ing', 'Premios sacados', 'Utilidad', 'Gastos', 'Balance general'];
    const porRuta = configuracion.agrupacion === 'ruta';
    const unidad = porRuta ? 'rutas' : 'terminales';
    const escapar = valor => String(valor ?? '').replace(/[&<>"']/g, caracter => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[caracter]);
    const porcentajeGlobal = (referencia, campo) => referencia?.[campo] == null ? '—' : `${Number(referencia[campo]).toFixed(2)}%`;
    const productos = [['tradicionales', 'Tradicional'], ['no_tradicionales', 'No tradicional'], ['recargas', 'Recargas']];
    const porcentajeProducto = (clave, periodo) => {
        if (clave === 'tradicionales') return 100;
        if (clave === 'recargas') return 7.5;
        return periodo.porcentaje_no_tradicional == null ? null : Number(periodo.porcentaje_no_tradicional) * 100;
    };
    const textoPorcentaje = valor => valor == null ? '—' : `${valor.toFixed(2)}%`;
    const desglosePorcentajes = (periodo, tipo = 'display') => {
        if (tipo !== 'display') return productos.map(([clave, etiqueta]) => `${etiqueta}: ${textoPorcentaje(porcentajeProducto(clave, periodo))}`).join(' | ');
        return `<div class="desglose-productos desglose-porcentajes">${productos.map(([clave, etiqueta]) => `<div class="linea-producto"><span class="producto-etiqueta">${etiqueta}</span><span>${textoPorcentaje(porcentajeProducto(clave, periodo))}</span></div>`).join('')}</div>`;
    };
    const desgloseProductos = (campo, periodo) => {
        const prefijo = campo === 'ventas' ? 'ventas_' : 'ingresos_';
        const sinPorcentaje = periodo.estado_ingresos === 'pendiente' ? 'Pendiente' : 'Sin porcentaje contable';
        const lineas = productos.map(([clave, etiqueta]) => {
            const importe = periodo[prefijo + clave];
            const texto = importe == null && prefijo === 'ingresos_' && clave === 'no_tradicionales' ? sinPorcentaje : moneda(importe);
            return `<div class="linea-producto"><span class="producto-etiqueta">${etiqueta}</span><span>${texto}</span></div>`;
        }).join('');
        return `<div class="desglose-productos">${lineas}</div>`;
    };
    const ventasMenosPremios = (ventas, referencia) => {
        const importe = referencia?.premios_sacados == null ? '—' : moneda(Number(ventas || 0) * (1 - Number(referencia.premios_sacados) / 100));
        return `<div class="importe-general" title="Ventas menos el porcentaje global de premios">${importe}</div>`;
    };
    const ventasPorPremios = (ventas, referencia) => {
        const importe = referencia?.premios_sacados == null ? '—' : moneda(Number(ventas || 0) * Number(referencia.premios_sacados) / 100);
        return `<div class="importe-general" title="Ventas por el porcentaje global de premios">${importe}</div>`;
    };
    const conPorcentaje = (campo, importe, referencia, ventas) => {
        const porcentaje = porcentajeGlobal(referencia, campo);
        const clase = campo === 'premios_sacados' ? 'porcentaje-premios' : 'porcentaje-utilidad';
        return `<div>${moneda(importe)}</div><small class="porcentaje ${clase}">${porcentaje}</small>${campo === 'utilidad' ? ventasMenosPremios(ventas, referencia) : ventasPorPremios(ventas, referencia)}`;
    };
    const resumenPeriodo = filas => {
        const totales = { ventas: 0, premios_sacados: 0, utilidad: 0, balance_general: 0 };
        filas.forEach(fila => configuracion.meses.forEach((mes, indiceMes) => {
            Object.keys(totales).forEach(campo => { totales[campo] += Number(fila.periodos[indiceMes]?.[campo] || 0); });
        }));
        Object.keys(totales).forEach(campo => { totales[campo] = Math.round(totales[campo] * 100) / 100; });
        return totales;
    };
    const mostrarResultadoBruto = (resumen, referencia) => {
        const porcentaje = campo => porcentajeGlobal(referencia, campo);
        const clase = resumen.balance_general > 0 ? 'text-success' : resumen.balance_general < 0 ? 'text-danger' : '';
        return `<div class="${clase}">${moneda(resumen.balance_general)}</div><div><small class="porcentaje porcentaje-premios">% Premios: ${porcentaje('premios_sacados')}</small></div>${ventasPorPremios(resumen.ventas, referencia)}<div><small class="porcentaje porcentaje-utilidad">% Utilidad: ${porcentaje('utilidad')}</small></div>${ventasMenosPremios(resumen.ventas, referencia)}`;
    };
    const encabezados = [porRuta ? 'Ruta / Empresa' : 'Terminal / Agencia', ...configuracion.meses.flatMap(mes => etiquetas.map(etiqueta => `${etiqueta} · ${mes.codigo}`)), 'Resultado Bruto'];
    const columnas = [
        { data: 'terminal', render: (valor, tipo, fila) => {
            if (porRuta) {
                if (tipo === 'display') return `<div class="fw-semibold">${escapar(fila.nombre)}</div><small class="text-muted">${escapar(fila.empresa)} · ${Number(fila.cantidad_terminales)} terminales</small>`;
                return `${fila.nombre} - ${fila.empresa}`;
            }
            if (tipo === 'display') return `<div class="fw-semibold">${escapar(fila.nombre)}</div><small class="text-muted">Terminal: ${escapar(valor)}</small>`;
            if (tipo === 'sort' || tipo === 'type') return valor;
            return `${valor} - ${fila.nombre}`;
        } },
        ...configuracion.meses.flatMap((mes, indiceMes) => medidas.map((campo, indiceCampo) => ({
            data: campo === 'porcentaje_ingresos' ? null : `periodos.${indiceMes}.${campo}`,
            defaultContent: 0,
            className: `text-end fw-bold${indiceCampo === 0 ? ' inicio-mes' : ''}`,
            render: (valor, tipo, fila) => {
                if (campo === 'porcentaje_ingresos') {
                    const periodo = fila.periodos[indiceMes] || {};
                    if (tipo === 'sort' || tipo === 'type') return periodo.ingresos_calculados == null || !Number(periodo.ventas) ? null : Number(periodo.ingresos_calculados) / Number(periodo.ventas) * 100;
                    return desglosePorcentajes(periodo, tipo);
                }
                if (tipo === 'display' && ['ventas', 'ingresos_calculados'].includes(campo)) {
                    return desgloseProductos(campo, fila.periodos[indiceMes] || {});
                }
                if (campo === 'ingresos_calculados' && valor == null) {
                    return tipo === 'sort' || tipo === 'type' ? null : '';
                }
                if (tipo !== 'display') return Number(valor || 0);
                return ['premios_sacados', 'utilidad'].includes(campo)
                    ? conPorcentaje(campo, valor, configuracion.referenciasGlobales.empresas[fila.empresa]?.meses[mes.codigo], fila.periodos[indiceMes]?.ventas)
                    : moneda(valor);
            },
            createdCell: (celda, valor) => {
                if (campo === 'balance_general' && Number(valor) !== 0) celda.classList.add(Number(valor) > 0 ? 'text-success' : 'text-danger');
            },
        }))),
        {
            data: null,
            className: 'text-end fw-bold inicio-mes',
            render: (valor, tipo, fila) => {
                const resumen = resumenPeriodo([fila]);
                return tipo === 'display' ? mostrarResultadoBruto(resumen, configuracion.referenciasGlobales.empresas[fila.empresa]?.periodo) : resumen.balance_general;
            },
        },
    ];
    try {
        const tabla = document.getElementById('tablaRentabilidadV10');
        tabla.createTFoot().innerHTML = `<tr>${columnas.map((columna, indice) => `<td class="${indice === 0 ? '' : 'text-end'}${indice === columnas.length - 1 ? ' inicio-mes' : ''}">${indice === 0 ? 'Totales' : ''}</td>`).join('')}</tr>`;
        $('#tablaRentabilidadV10').DataTable({
            data: configuracion.datos,
            columns: columnas,
            deferRender: true,
            pageLength: 25,
            scrollX: true,
            autoWidth: false,
            order: [...configuracion.meses.map((mes, indiceMes) => [1 + indiceMes * medidas.length, 'desc']), [0, 'asc']],
            dom: 'Bfrtip',
            buttons: ['copy', 'csv', 'excel', 'print'].map(extend => ({
                extend,
                title: 'Rentabilidad mensual V10',
                exportOptions: {
                    orthogonal: 'export',
                    modifier: { search: 'applied' },
                    format: { header: (contenido, indice) => encabezados[indice] },
                },
            })),
            initComplete: function () {
                document.getElementById('cargandoV10').classList.add('d-none');
                document.getElementById('contenedorTablaV10').classList.remove('d-none');
                this.api().columns.adjust();
            },
            footerCallback: function () {
                const api = this.api();
                const filas = api.rows({ search: 'applied' }).data().toArray();
                api.column(0).footer().textContent = `Totales filtrados · ${filas.length} ${unidad}`;
                configuracion.meses.forEach((mes, indiceMes) => medidas.forEach((campo, indiceCampo) => {
                    const total = Math.round(filas.reduce((suma, fila) => suma + Number(fila.periodos[indiceMes]?.[campo] || 0), 0) * 100) / 100;
                    const celda = api.column(1 + indiceMes * medidas.length + indiceCampo).footer();
                    if (campo === 'porcentaje_ingresos') {
                        const periodo = {
                            porcentaje_no_tradicional: configuracion.referenciasGlobales.ingresos?.[mes.codigo] ?? null,
                        };
                        celda.innerHTML = desglosePorcentajes(periodo);
                        return;
                    }
                    if (['ventas', 'ingresos_calculados'].includes(campo)) {
                        const periodo = {};
                        const prefijo = campo === 'ventas' ? 'ventas_' : 'ingresos_';
                        productos.forEach(([clave]) => {
                            const nombre = prefijo + clave;
                            periodo[nombre] = campo === 'ingresos_calculados' && clave === 'no_tradicionales' && filas.some(fila => fila.periodos[indiceMes]?.[nombre] == null)
                                ? null : filas.reduce((suma, fila) => suma + Number(fila.periodos[indiceMes]?.[nombre] || 0), 0);
                        });
                        celda.innerHTML = desgloseProductos(campo, periodo);
                        return;
                    }
                    celda.textContent = moneda(total);
                    if (['premios_sacados', 'utilidad'].includes(campo)) {
                        const ventas = filas.reduce((suma, fila) => suma + Number(fila.periodos[indiceMes]?.ventas || 0), 0);
                        celda.innerHTML = conPorcentaje(campo, total, configuracion.referenciasGlobales.total.meses[mes.codigo], ventas);
                    }
                    celda.classList.remove('text-success', 'text-danger');
                    if (campo === 'balance_general' && total !== 0) celda.classList.add(total > 0 ? 'text-success' : 'text-danger');
                }));
                api.column(columnas.length - 1).footer().innerHTML = mostrarResultadoBruto(resumenPeriodo(filas), configuracion.referenciasGlobales.total.periodo);
            },
            language: { search: porRuta ? 'Buscar ruta o empresa:' : 'Buscar agencia o terminal:', info: `Mostrando _START_ a _END_ de _TOTAL_ ${unidad}`, infoEmpty: `Sin ${unidad}`, zeroRecords: 'Sin coincidencias', emptyTable: 'Sin datos', paginate: { next: 'Siguiente', previous: 'Anterior' } },
        });
    } catch (error) {
        mostrarErrorCarga(error);
    }
});
