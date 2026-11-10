document.addEventListener('DOMContentLoaded', function () {
    const configuracion = window.rentabilidadV11;
    const moneda = valor => valor == null ? '—' : new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(Number(valor || 0));
    const empresa = document.getElementById('empresaV11');
    const ruta = document.getElementById('rutaV11');
    const actualizarRutas = () => {
        const seleccionada = ruta.value;
        const rutas = [...new Set(configuracion.rutasDisponibles.filter(fila => !empresa.value || fila.empresa === empresa.value).map(fila => fila.ruta))];
        ruta.replaceChildren(new Option('Todas las rutas', ''), ...rutas.map(nombre => new Option(nombre, nombre)));
        ruta.value = rutas.includes(seleccionada) ? seleccionada : '';
    };
    empresa.addEventListener('change', actualizarRutas);
    actualizarRutas();
    const completo = document.getElementById('anioCompletoV11');
    const inicio = document.getElementById('mes_inicioV11');
    const fin = document.getElementById('mes_finV11');
    const actualizarAnio = () => {
        if (completo.checked) { inicio.value = '1'; fin.value = '12'; }
    };
    completo.addEventListener('change', actualizarAnio);
    [inicio, fin].forEach(select => select.addEventListener('change', () => { completo.checked = false; }));
    actualizarAnio();
    const alcance = document.getElementById('alcanceV11');
    const terminal = document.getElementById('terminalV11');
    const agrupacion = document.getElementById('agrupacionV11');
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
    document.getElementById('filtrosRentabilidadV11').addEventListener('submit', function (event) {
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
        const boton = document.querySelector('#filtrosRentabilidadV11 button[type="submit"]');
        boton.disabled = false;
        boton.textContent = 'Consultar';
    });
    if (!configuracion.datos.length || !document.getElementById('tablaRentabilidadV11')) return;

    const mostrarErrorCarga = error => {
        const estado = document.getElementById('cargandoV11');
        estado.classList.remove('d-none');
        estado.classList.add('text-danger');
        estado.setAttribute('role', 'alert');
        estado.textContent = `No se pudo preparar la tabla: ${error.message}. Recarga la página para volver a intentar.`;
        console.error('Error al preparar rentabilidad V11:', error);
    };
    if (typeof window.jQuery !== 'function' || typeof window.jQuery.fn?.DataTable !== 'function') {
        mostrarErrorCarga(new Error('no se pudo cargar DataTables desde su servidor'));
        return;
    }

    const medidas = ['ventas', 'ingresos_calculados', 'premios_sacados', 'utilidad', 'gastos', 'balance_general'];
    const etiquetas = ['Ventas', 'Ingresos', 'Premios', 'Utilidad', 'Gastos', 'Neto'];
    const porRuta = configuracion.agrupacion === 'ruta';
    const unidad = porRuta ? 'rutas' : 'terminales';
    const escapar = valor => String(valor ?? '').replace(/[&<>"']/g, caracter => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[caracter]);
    const mostrarClasificacion = clasificacion => {
        if (!clasificacion) return '';
        return `<div class="clasificacion-terminal"><span class="badge" style="background-color:${escapar(clasificacion.fondo)};color:${escapar(clasificacion.color)}">${escapar(clasificacion.etiqueta)}</span>${clasificacion.provisional ? '<small class="d-block text-muted">Evaluación provisional</small>' : ''}${clasificacion.deterioro ? '<small class="d-block text-danger">En deterioro</small>' : ''}</div>`;
    };
    const productos = ['tradicionales', 'no_tradicionales', 'recargas'];
    const colorVentas = valor => valor == null ? '' : Number(valor) > 1000000 ? 'venta-millon' : Number(valor) >= 100000 ? 'venta-alta' : 'venta-baja';
    const desgloseProductos = (campo, periodo) => {
        const prefijo = campo === 'ventas' ? 'ventas_' : 'ingresos_';
        return '<div class="desglose-productos">' + productos.map(clave => {
            const etiqueta = campo === 'ventas' ? 'strong' : 'span';
            const clase = campo === 'ventas' ? colorVentas(periodo[prefijo + clave]) : '';
            return '<div class="linea-producto"><' + etiqueta + ' class="' + clase + '">' + moneda(periodo[prefijo + clave]) + '</' + etiqueta + '></div>';
        }).join('') + '</div>';
    };
    const mostrarPremios = (importe, porcentaje, tipo = 'display') => {
        const textoPorcentaje = porcentaje == null ? '—' : Number(porcentaje).toFixed(2) + '%';
        if (tipo === 'sort' || tipo === 'type') return importe;
        if (tipo !== 'display') return importe == null ? '' : Number(importe);
        return '<div class="linea-devolucion"><span class="monto-devolucion">' + moneda(importe) + '</span><small class="porcentaje porcentaje-premios">' + textoPorcentaje + '</small></div>';
    };
    const encabezados = [porRuta ? 'Ruta / Empresa' : 'Terminal / Agencia', ...configuracion.meses.flatMap(mes => etiquetas.map(etiqueta => `${etiqueta} · ${mes.codigo}`))];
    const columnas = [
        { data: 'terminal', render: (valor, tipo, fila) => {
            if (porRuta) {
                if (tipo === 'display') return `<div class="fw-semibold">${escapar(fila.nombre)}</div><small class="text-muted">${escapar(fila.empresa)} · ${Number(fila.cantidad_terminales)} terminales</small>${mostrarClasificacion(fila.clasificacion)}`;
                return `${fila.nombre} - ${fila.empresa}${fila.clasificacion ? ' | ' + fila.clasificacion.etiqueta : ''}`;
            }
            if (tipo === 'display') return `<div class="fw-semibold">${escapar(fila.nombre)}</div><small class="text-muted">Terminal: ${escapar(valor)}</small>${mostrarClasificacion(fila.clasificacion)}`;
            if (tipo === 'sort' || tipo === 'type') return valor;
            return `${valor} - ${fila.nombre}${fila.clasificacion ? ' | ' + fila.clasificacion.etiqueta : ''}`;
        } },
        ...configuracion.meses.flatMap((mes, indiceMes) => medidas.map((campo, indiceCampo) => ({
            data: `periodos.${indiceMes}.${campo}`,
            defaultContent: 0,
            className: `text-end fw-bold${indiceCampo === 0 ? ' inicio-mes' : ''}${campo === 'utilidad' ? ' devolucion-azul' : ''}`,
            render: (valor, tipo, fila) => {
                if (campo === 'premios_sacados') {
                    return mostrarPremios(valor, fila.periodos[indiceMes]?.porcentaje_dev_g, tipo);
                }
                if (tipo === 'display' && ['ventas', 'ingresos_calculados'].includes(campo)) {
                    return desgloseProductos(campo, fila.periodos[indiceMes] || {});
                }
                if (campo === 'ingresos_calculados' && valor == null) {
                    return tipo === 'sort' || tipo === 'type' ? null : '';
                }
                if (tipo !== 'display') return valor == null ? null : Number(valor);
                return moneda(valor);
            },
            createdCell: (celda, valor) => {
                if (campo === 'balance_general' && Number(valor) !== 0) celda.classList.add(Number(valor) > 0 ? 'text-success' : 'text-danger');
            },
        }))),
    ];
    let tablaDatos;
    const prepararExcel = datos => {
        const filas = tablaDatos.rows({ search: 'applied', order: 'applied', selected: null }).data().toArray();
        const etiquetasProductos = ['Tradicional', 'No tradicional', 'Recargas'];
        const columnasExcel = configuracion.meses.flatMap((mes, indiceMes) => medidas.flatMap((campo, indiceCampo) => {
            if (['ventas', 'ingresos_calculados'].includes(campo)) {
                const prefijo = campo === 'ventas' ? 'ventas_' : 'ingresos_';
                return productos.map((producto, indiceProducto) => ({
                    campo: prefijo + producto,
                    indiceMes,
                    titulo: `${etiquetas[indiceCampo]} - ${etiquetasProductos[indiceProducto]} - ${mes.codigo}`,
                }));
            }
            return [{ campo, indiceMes, titulo: `${etiquetas[indiceCampo]} - ${mes.codigo}` }];
        }));
        const importe = (fila, columna) => fila.periodos[columna.indiceMes]?.[columna.campo];
        datos.header = [encabezados[0], ...columnasExcel.map(columna => columna.titulo)];
        datos.body = filas.map(fila => [
            columnas[0].render(fila.terminal, 'export', fila),
            ...columnasExcel.map(columna => {
                const valor = importe(fila, columna);
                return valor == null ? '' : Number(valor);
            }),
        ]);
        datos.footer = ['Totales filtrados', ...columnasExcel.map(columna => {
            if (filas.some(fila => importe(fila, columna) == null)) return '';
            return Math.round(filas.reduce((total, fila) => total + Number(importe(fila, columna)), 0) * 100) / 100;
        })];
    };
    try {
        const tabla = document.getElementById('tablaRentabilidadV11');
        tabla.createTFoot().innerHTML = `<tr>${columnas.map((columna, indice) => `<td class="${indice === 0 ? '' : 'text-end'}">${indice === 0 ? 'Totales' : ''}</td>`).join('')}</tr>`;
        tablaDatos = $('#tablaRentabilidadV11').DataTable({
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
                ...(extend === 'excel' ? { text: 'Exportar Excel', footer: true } : {}),
                title: 'Rentabilidad mensual V11',
                exportOptions: {
                    columns: ':visible',
                    orthogonal: 'export',
                    modifier: { search: 'applied' },
                    format: { header: (contenido, indice) => encabezados[indice] },
                    ...(extend === 'excel' ? {
                        modifier: { search: 'applied', order: 'applied', selected: null },
                        customizeData: prepararExcel,
                    } : {}),
                },
            })),
            initComplete: function () {
                document.getElementById('cargandoV11').classList.add('d-none');
                document.getElementById('contenedorTablaV11').classList.remove('d-none');
                this.api().columns.adjust();
            },
            footerCallback: function () {
                const api = this.api();
                const filas = api.rows({ search: 'applied' }).data().toArray();
                api.column(0).footer().textContent = `Totales filtrados · ${filas.length} ${unidad}`;
                configuracion.meses.forEach((mes, indiceMes) => medidas.forEach((campo, indiceCampo) => {
                    const incompleto = filas.some(fila => fila.periodos[indiceMes]?.[campo] == null);
                    const total = incompleto ? null : Math.round(filas.reduce((suma, fila) => suma + Number(fila.periodos[indiceMes][campo]), 0) * 100) / 100;
                    const celda = api.column(1 + indiceMes * medidas.length + indiceCampo).footer();
                    if (campo === 'premios_sacados') {
                        const ventas = filas.reduce((suma, fila) => suma + Number(fila.periodos[indiceMes]?.ventas_tradicionales || 0), 0);
                        celda.innerHTML = mostrarPremios(total, total == null || ventas === 0 ? null : total / ventas * 100);
                        return;
                    }
                    if (['ventas', 'ingresos_calculados'].includes(campo)) {
                        const periodo = {};
                        const prefijo = campo === 'ventas' ? 'ventas_' : 'ingresos_';
                        productos.forEach(clave => {
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
                    if (campo === 'balance_general' && total != null && total !== 0) celda.classList.add(total > 0 ? 'text-success' : 'text-danger');
                }));
            },
            language: { search: porRuta ? 'Buscar ruta o empresa:' : 'Buscar agencia o terminal:', info: `Mostrando _START_ a _END_ de _TOTAL_ ${unidad}`, infoEmpty: `Sin ${unidad}`, zeroRecords: 'Sin coincidencias', emptyTable: 'Sin datos', paginate: { next: 'Siguiente', previous: 'Anterior' } },
        });
    } catch (error) {
        mostrarErrorCarga(error);
    }
});
