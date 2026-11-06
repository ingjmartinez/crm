document.addEventListener('DOMContentLoaded', function () {
    const configuracion = window.rentabilidadV10;
    const moneda = valor => new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(Number(valor || 0));
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

    const medidas = ['ventas', 'ingresos_calculados', 'porcentaje_ingresos', 'porcentaje_ingresos_2', 'premios_sacados', 'premios_sacados_2', 'utilidad', 'utilidad_2', 'gastos', 'balance_general'];
    const etiquetas = ['Ventas', 'Ingresos calculado', '% ing', '% ing 2', 'Premios sacados', 'Premios sacados 2', 'Utilidad', 'Utilidad 2', 'Gastos', 'Resultado Bruto'];
    const premiosSacados2 = ventas => Math.round(Number(ventas || 0) * 0.63 * 100) / 100;
    const utilidad2 = ventas => Math.round((Number(ventas || 0) - premiosSacados2(ventas)) * 100) / 100;
    const estimadosPeriodo = periodo => {
        const premios = periodo?.premios_sacados_2 ?? premiosSacados2(periodo?.ventas_tradicionales);
        const utilidad = periodo?.utilidad_2 ?? utilidad2(periodo?.ventas_tradicionales);
        const utilidadOriginal = Number(periodo?.utilidad ?? (Number(periodo?.ventas_tradicionales || 0) - Number(periodo?.premios_sacados_tradicionales ?? periodo?.premios_sacados ?? 0)));
        return { premios_sacados_2: premios, utilidad_2: utilidad, balance_general: Math.round((utilidadOriginal - Number(periodo?.gastos || 0)) * 100) / 100 };
    };
    const mostrarEstimado = (campo, importe) => `<div>${moneda(importe)}</div><small class="porcentaje ${campo === 'premios_sacados_2' ? 'porcentaje-premios' : 'porcentaje-utilidad'}">${campo === 'premios_sacados_2' ? '63%' : '37%'}</small>`;
    const porRuta = configuracion.agrupacion === 'ruta';
    const unidad = porRuta ? 'rutas' : 'terminales';
    const escapar = valor => String(valor ?? '').replace(/[&<>"']/g, caracter => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[caracter]);
    const porcentajeGlobal = (referencia, campo) => referencia?.[campo] == null ? '—' : `${Number(referencia[campo]).toFixed(2)}%`;
    const productos = [['tradicionales', 'Tradicional'], ['no_tradicionales', 'No tradicional'], ['recargas', 'Recargas']];
    const porcentajeProducto = (clave, porcentajeNoTradicional) => {
        if (clave === 'tradicionales') return 100;
        if (clave === 'recargas') return 7.5;
        return porcentajeNoTradicional == null ? null : Math.trunc(Number(porcentajeNoTradicional) * 100) / 100;
    };
    const textoPorcentaje = valor => valor == null ? '—' : `${valor.toFixed(2)}%`;
    const mostrarPorcentajeIngresos2 = (valor, tipo = 'display') => tipo === 'display'
        ? `<div class="desglose-productos desglose-porcentajes"><div class="linea-producto"><span class="producto-etiqueta">No tradicional</span><span>${valor == null ? '—' : (Math.trunc(valor * 100) / 100).toFixed(2) + '%'}</span></div></div>` : valor ?? '';
    const desglosePorcentajes = (porcentajeNoTradicional, tipo = 'display') => {
        if (tipo !== 'display') return productos.map(([clave, etiqueta]) => `${etiqueta}: ${textoPorcentaje(porcentajeProducto(clave, porcentajeNoTradicional))}`).join(' | ');
        return `<div class="desglose-productos desglose-porcentajes">${productos.map(([clave, etiqueta]) => `<div class="linea-producto"><span class="producto-etiqueta">${etiqueta}</span><span>${textoPorcentaje(porcentajeProducto(clave, porcentajeNoTradicional))}</span></div>`).join('')}</div>`;
    };
    const desgloseProductos = (campo, periodo) => {
        const prefijo = campo === 'ventas' ? 'ventas_' : 'ingresos_';
        const sinPorcentaje = 'Sin porcentaje contable';
        const lineas = productos.map(([clave, etiqueta]) => {
            const importe = periodo[prefijo + clave];
            const texto = importe == null && prefijo === 'ingresos_' && clave === 'no_tradicionales' ? sinPorcentaje : moneda(importe);
            return `<div class="linea-producto"><span class="producto-etiqueta">${etiqueta}</span><span>${texto}</span></div>`;
        }).join('');
        return `<div class="desglose-productos">${lineas}</div>`;
    };
    const devolucionTerminal = (premios, ventas) => Number(ventas || 0) === 0 ? null : Number(premios || 0) / Number(ventas) * 100;
    const mostrarPremios = (premios, ventas, referencia, estimado, tipo = 'display') => {
        const devolucion = devolucionTerminal(premios, ventas);
        const textoDevolucion = devolucion == null ? '—' : `${devolucion.toFixed(2)}%`;
        const global = porcentajeGlobal(referencia, 'premios_sacados');
        const montoGlobal = referencia?.premios_sacados == null ? '—' : moneda(Number(ventas || 0) * Number(referencia.premios_sacados) / 100);
        if (tipo === 'sort' || tipo === 'type') return devolucion;
        if (tipo !== 'display') return `Dev T ${textoDevolucion}: ${moneda(premios)} | Dev G ${global}: ${montoGlobal} | Dev Proy 63%: ${moneda(estimado)}`;
        return `<div class="desglose-devoluciones"><div class="linea-devolucion"><span>Dev T <span class="devolucion-azul" title="Premios sacados / ventas tradicionales de la terminal">${textoDevolucion}</span></span><span class="devolucion-azul">${moneda(premios)}</span></div><div class="linea-devolucion"><span>Dev G <small class="porcentaje porcentaje-premios" title="Global del grupo">${global}</small></span><span>${montoGlobal}</span></div><div class="linea-devolucion"><span>Dev Proy <small class="porcentaje porcentaje-premios">63%</small></span><span class="monto-proyectado">${moneda(estimado)}</span></div></div>`;
    };
    const encabezados = [porRuta ? 'Ruta / Empresa' : 'Terminal / Agencia', ...configuracion.meses.flatMap(mes => etiquetas.map(etiqueta => `${etiqueta} · ${mes.codigo}`))];
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
            data: ['porcentaje_ingresos', 'porcentaje_ingresos_2', 'premios_sacados_2', 'utilidad_2'].includes(campo) ? null : `periodos.${indiceMes}.${campo}`,
            visible: !['porcentaje_ingresos', 'porcentaje_ingresos_2', 'premios_sacados_2', 'utilidad_2'].includes(campo),
            defaultContent: 0,
            className: `text-end fw-bold${indiceCampo === 0 ? ' inicio-mes' : ''}${campo === 'utilidad' ? ' devolucion-azul' : ''}`,
            render: (valor, tipo, fila) => {
                if (campo === 'premios_sacados') {
                    const periodo = fila.periodos[indiceMes];
                    return mostrarPremios(valor, periodo?.ventas_tradicionales, configuracion.referenciasGlobales.empresas[fila.empresa]?.meses[mes.codigo], estimadosPeriodo(periodo).premios_sacados_2, tipo);
                }
                if (['premios_sacados_2', 'utilidad_2', 'balance_general'].includes(campo)) {
                    const importe = estimadosPeriodo(fila.periodos[indiceMes])[campo];
                    return tipo === 'display' ? (campo === 'balance_general' ? moneda(importe) : mostrarEstimado(campo, importe)) : importe;
                }
                if (campo === 'porcentaje_ingresos_2') {
                    return mostrarPorcentajeIngresos2(configuracion.referenciasGlobales.ingresos_2?.empresas[fila.empresa]?.[mes.codigo]?.porcentaje ?? null, tipo);
                }
                if (campo === 'porcentaje_ingresos') {
                    const porcentaje = configuracion.referenciasGlobales.ingresos_2?.empresas[fila.empresa]?.[mes.codigo]?.porcentaje ?? null;
                    if (tipo === 'sort' || tipo === 'type') return porcentaje;
                    return desglosePorcentajes(porcentaje, tipo);
                }
                if (tipo === 'display' && ['ventas', 'ingresos_calculados'].includes(campo)) {
                    return desgloseProductos(campo, fila.periodos[indiceMes] || {});
                }
                if (campo === 'ingresos_calculados' && valor == null) {
                    return tipo === 'sort' || tipo === 'type' ? null : '';
                }
                if (tipo !== 'display') return Number(valor || 0);
                return moneda(valor);
            },
            createdCell: (celda, valor) => {
                if (campo === 'balance_general' && Number(valor) !== 0) celda.classList.add(Number(valor) > 0 ? 'text-success' : 'text-danger');
            },
        }))),
    ];
    try {
        const tabla = document.getElementById('tablaRentabilidadV10');
        tabla.createTFoot().innerHTML = `<tr>${columnas.map((columna, indice) => `<td class="${indice === 0 ? '' : 'text-end'}">${indice === 0 ? 'Totales' : ''}</td>`).join('')}</tr>`;
        $('#tablaRentabilidadV10').DataTable({
            data: configuracion.datos,
            columns: columnas,
            deferRender: true,
            pageLength: 25,
            scrollX: false,
            autoWidth: false,
            order: [...configuracion.meses.map((mes, indiceMes) => [1 + indiceMes * medidas.length, 'desc']), [0, 'asc']],
            dom: 'Bfrtip',
            buttons: ['copy', 'csv', 'excel', 'print'].map(extend => ({
                extend,
                title: 'Rentabilidad mensual V10',
                exportOptions: {
                    columns: ':visible',
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
                    const total = Math.round(filas.reduce((suma, fila) => suma + Number((['premios_sacados_2', 'utilidad_2', 'balance_general'].includes(campo) ? estimadosPeriodo(fila.periodos[indiceMes])[campo] : fila.periodos[indiceMes]?.[campo]) || 0), 0) * 100) / 100;
                    const celda = api.column(1 + indiceMes * medidas.length + indiceCampo).footer();
                    if (campo === 'premios_sacados') {
                        const ventas = filas.reduce((suma, fila) => suma + Number(fila.periodos[indiceMes]?.ventas_tradicionales || 0), 0);
                        const estimado = filas.reduce((suma, fila) => suma + estimadosPeriodo(fila.periodos[indiceMes]).premios_sacados_2, 0);
                        celda.innerHTML = mostrarPremios(total, ventas, configuracion.referenciasGlobales.total.meses[mes.codigo], Math.round(estimado * 100) / 100);
                        return;
                    }
                    if (['premios_sacados_2', 'utilidad_2'].includes(campo)) {
                        celda.innerHTML = mostrarEstimado(campo, total);
                        return;
                    }
                    if (campo === 'porcentaje_ingresos_2') {
                        celda.innerHTML = mostrarPorcentajeIngresos2(configuracion.referenciasGlobales.ingresos_2?.total[mes.codigo] ?? null);
                        return;
                    }
                    if (campo === 'porcentaje_ingresos') {
                        celda.innerHTML = desglosePorcentajes(configuracion.referenciasGlobales.ingresos_2?.total[mes.codigo] ?? null);
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
                    if (campo === 'utilidad') celda.classList.add('devolucion-azul');
                    celda.classList.remove('text-success', 'text-danger');
                    if (campo === 'balance_general' && total !== 0) celda.classList.add(total > 0 ? 'text-success' : 'text-danger');
                }));
            },
            language: { search: porRuta ? 'Buscar ruta o empresa:' : 'Buscar agencia o terminal:', info: `Mostrando _START_ a _END_ de _TOTAL_ ${unidad}`, infoEmpty: `Sin ${unidad}`, zeroRecords: 'Sin coincidencias', emptyTable: 'Sin datos', paginate: { next: 'Siguiente', previous: 'Anterior' } },
        });
    } catch (error) {
        mostrarErrorCarga(error);
    }
});
