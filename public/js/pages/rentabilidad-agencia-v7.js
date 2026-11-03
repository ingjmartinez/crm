(function () {
    function escapar(valor) {
        const elemento = document.createElement('span');
        elemento.textContent = String(valor ?? '');
        return elemento.innerHTML;
    }

    const formato = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const moneda = valor => 'RD$ ' + formato.format(Number(valor || 0));
    const colorBalance = valor => Number(valor) > 0 ? 'text-success' : Number(valor) < 0 ? 'text-danger' : '';
    const badge = clasificacion => {
        const colores = ['success', 'primary', 'info', 'warning', 'danger', 'secondary'];
        const color = colores.includes(clasificacion.color) ? clasificacion.color : 'secondary';
        const deterioro = clasificacion.en_deterioro ? ' <span class="badge bg-warning text-dark">En deterioro</span>' : '';
        return `<span class="badge bg-${color}${['warning', 'info'].includes(color) ? ' text-dark' : ''}">${escapar(clasificacion.nombre)}</span>${deterioro}`;
    };
    const motivoClasificacion = clasificacion => {
        const porcentaje = clasificacion.porcentaje === null ? '' : ` (${formato.format(clasificacion.porcentaje)}%)`;
        const partes = [`${clasificacion.meses_positivos}/${clasificacion.meses_con_ventas} meses positivos${porcentaje}`];
        if (clasificacion.meses_recientes > 0) {
            const ventana = clasificacion.meses_recientes === 1 ? 'último mes con ventas' : `últimos ${clasificacion.meses_recientes} meses con ventas`;
            partes.push(`${ventana}: ${Number(clasificacion.balance_reciente) > 0 ? '+' : ''}${moneda(clasificacion.balance_reciente)}`);
        }
        if (clasificacion.balance_acumulado !== undefined) partes.push(`acumulado: ${moneda(clasificacion.balance_acumulado)}`);
        if (clasificacion.motivo) partes.push(clasificacion.motivo);
        return partes.map(escapar).join(' · ');
    };
    const celdaBalance = valor => `<td class="text-end ${colorBalance(valor)}">${moneda(valor)}</td>`;

    document.addEventListener('DOMContentLoaded', function () {
        const configuracion = window.rentabilidadV7;
        const modalImpresion = document.getElementById('modalImpresionV7');
        if (modalImpresion) document.body.appendChild(modalImpresion);
        const formularioImpresion = document.getElementById('formImpresionV7');
        if (formularioImpresion) {
            let generando = false;
            formularioImpresion.addEventListener('submit', async event => {
                event.preventDefault();
                if (generando) return;
                const salida = event.submitter?.value || 'pdf';
                const estado = document.getElementById('estadoImpresionV7');
                const botones = formularioImpresion.querySelectorAll('button[type="submit"]');
                const controlador = new AbortController();
                let ventana;
                let tiempo;
                generando = true;
                botones.forEach(boton => { boton.disabled = true; });
                estado.className = 'alert alert-info mt-3';
                estado.textContent = 'Generando documento... Si incluyes todas las rutas, puede tardar más de un minuto.';
                try {
                    if (salida === 'imprimir') {
                        ventana = window.open('', '_blank');
                        if (!ventana) throw new Error('El navegador bloqueó la ventana de impresión. Permite las ventanas emergentes e intenta de nuevo.');
                        ventana.opener = null;
                        ventana.document.body.textContent = 'Preparando impresión...';
                    }
                    const parametros = new URLSearchParams(new FormData(formularioImpresion));
                    parametros.set('salida', salida);
                    const url = new URL(formularioImpresion.action, window.location.href);
                    url.search = parametros.toString();
                    tiempo = setTimeout(() => controlador.abort(), 180000);
                    const respuesta = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: controlador.signal });
                    if (!respuesta.ok || respuesta.redirected) {
                        let mensaje = 'No se pudo generar el documento. Revisa la sesión e intenta de nuevo.';
                        if (respuesta.headers.get('Content-Type')?.includes('application/json')) {
                            const error = await respuesta.json();
                            mensaje = Object.values(error.errors || {}).flat()[0] || error.message || mensaje;
                        }
                        throw new Error(mensaje);
                    }
                    if (salida === 'imprimir') {
                        if (!respuesta.headers.get('Content-Type')?.includes('text/html')) throw new Error('No se recibió el documento para imprimir.');
                        const html = await respuesta.text();
                        if (ventana.closed) throw new Error('La ventana de impresión fue cerrada. Intenta de nuevo.');
                        ventana.document.open();
                        ventana.document.write(html);
                        ventana.document.close();
                    } else {
                        if (!respuesta.headers.get('Content-Type')?.includes('application/pdf')) throw new Error('No se recibió un PDF válido. Vuelve a consultar el reporte e intenta de nuevo.');
                        const archivo = await respuesta.blob();
                        const enlace = document.createElement('a');
                        const urlArchivo = URL.createObjectURL(archivo);
                        enlace.href = urlArchivo;
                        enlace.download = 'rentabilidad_agencia_v7.pdf';
                        document.body.appendChild(enlace);
                        enlace.click();
                        enlace.remove();
                        setTimeout(() => URL.revokeObjectURL(urlArchivo), 60000);
                    }
                    estado.className = 'alert alert-success mt-3';
                    estado.textContent = salida === 'pdf' ? 'PDF generado. Revisa las descargas de tu navegador.' : 'Documento preparado en la ventana de impresión.';
                } catch (error) {
                    ventana?.close();
                    estado.className = 'alert alert-danger mt-3';
                    estado.textContent = error.name === 'AbortError' ? 'La generación superó el tiempo de espera. Intenta con una sola ruta.' : error.message;
                } finally {
                    clearTimeout(tiempo);
                    botones.forEach(boton => { boton.disabled = false; });
                    generando = false;
                }
            });
        }
        const empresa = document.getElementById('empresaV7');
        const ruta = document.getElementById('rutaV7');
        const actualizarRutas = () => {
            const seleccionada = ruta.value;
            const rutas = [...new Set((configuracion.rutasDisponibles ?? [])
                .filter(fila => !empresa.value || fila.empresa === empresa.value)
                .map(fila => fila.ruta))];
            ruta.replaceChildren(new Option('Todas las rutas', ''), ...rutas.map(nombre => new Option(nombre, nombre)));
            ruta.value = rutas.includes(seleccionada) ? seleccionada : '';
        };
        empresa.addEventListener('change', actualizarRutas);
        actualizarRutas();
        const completo = document.getElementById('anioCompletoV7');
        const alcance = document.getElementById('alcanceV7');
        const terminal = document.getElementById('terminalV7');
        const actualizarAnio = () => {
            if (completo.checked) {
                document.getElementById('mes_inicioV7').value = '1';
                document.getElementById('mes_finV7').value = '12';
            }
        };
        completo.addEventListener('change', actualizarAnio);
        ['mes_inicioV7', 'mes_finV7'].forEach(id => document.getElementById(id).addEventListener('change', () => { completo.checked = false; }));
        const actualizarTerminal = () => { terminal.disabled = alcance.value !== 'terminal'; terminal.required = alcance.value === 'terminal'; };
        alcance.addEventListener('change', actualizarTerminal);
        actualizarAnio();
        actualizarTerminal();
        const formulario = document.getElementById('filtrosRentabilidadV7');
        let consultando = false;
        formulario.addEventListener('submit', function (event) {
            if (window.Swal) {
                event.preventDefault();
                if (consultando) return;
                consultando = true;
                window.Swal.fire({
                    title: 'Consultando datos...',
                    text: 'Estamos agrupando ventas, premios y gastos del rango seleccionado.',
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
        window.addEventListener('pageshow', function (event) {
            if (event.persisted && consultando) {
                consultando = false;
                window.Swal?.close();
            }
        });
        if (!configuracion.datos.length || !document.getElementById('tablaRentabilidadV7')) return;

        const camposImporte = ['ventas', 'premios_sacados', 'utilidad', 'gastos', 'balance_general'];
        const tabla = $('#tablaRentabilidadV7').DataTable({
            data: configuracion.datos,
            deferRender: true,
            pageLength: 25,
            scrollX: true,
            order: [[0, 'asc']],
            dom: 'Bfrtip',
            buttons: ['copy', 'csv', 'excel', 'pdf', 'print'].map(extend => ({
                extend,
                exportOptions: { orthogonal: 'export', columns: [0, 1, 2, 3, 4, 5, 6, 7], modifier: { search: 'applied' } },
            })),
            columns: [
                { data: 'terminal', render: $.fn.dataTable.render.text() },
                { data: 'nombre', render: $.fn.dataTable.render.text() },
                ...camposImporte.map(campo => ({
                    data: campo, className: 'text-end', render: (valor, tipo) => tipo === 'display' ? moneda(valor) : Number(valor),
                    createdCell: (celda, valor) => { if (campo.startsWith('balance') && colorBalance(valor)) celda.classList.add(colorBalance(valor)); },
                })),
                { data: 'clasificacion', render: (valor, tipo) => {
                    if (tipo === 'display') return badge(valor);
                    if (tipo === 'export') return valor.nombre + (valor.en_deterioro ? ' · En deterioro' : '');
                    return valor.nombre;
                } },
                { data: null, orderable: false, searchable: false, render: () => '<div class="d-flex gap-1"><button type="button" class="btn btn-sm btn-outline-primary" data-accion="ver">Ver</button><button type="button" class="btn btn-sm btn-outline-secondary" data-accion="cuentas">Cuentas</button><button type="button" class="btn btn-sm btn-outline-success" data-accion="resumen">Resumen</button></div>' },
            ],
            initComplete: function () {
                document.getElementById('cargandoV7').classList.add('d-none');
                document.getElementById('contenedorTablaV7').classList.remove('d-none');
                this.api().columns.adjust();
            },
            footerCallback: function () {
                const api = this.api();
                const filas = api.rows({ search: 'applied' }).data().toArray();
                api.column(0).footer().textContent = 'Totales filtrados';
                api.column(1).footer().textContent = `${filas.length} terminales`;
                camposImporte.forEach((campo, indice) => {
                    const total = Math.round(filas.reduce((suma, fila) => suma + Number(fila[campo] || 0), 0) * 100) / 100;
                    const celda = api.column(indice + 2).footer();
                    celda.textContent = moneda(total);
                    celda.classList.remove('text-success', 'text-danger');
                    if (campo.startsWith('balance') && colorBalance(total)) celda.classList.add(colorBalance(total));
                });
            },
            language: { search: 'Buscar:', info: 'Mostrando _START_ a _END_ de _TOTAL_ terminales', infoEmpty: 'Sin terminales', zeroRecords: 'Sin coincidencias', paginate: { next: 'Siguiente', previous: 'Anterior' } },
        });

        const categoriaTabla = document.getElementById('categoriaTablaV7');
        const filtrarClasificacion = () => {
            const filtro = categoriaTabla.value ? '^' + $.fn.dataTable.util.escapeRegex(categoriaTabla.value) + '$' : '';
            tabla.column(7).search(filtro, true, false).draw();
        };
        categoriaTabla.addEventListener('change', filtrarClasificacion);

        const elementoModal = document.getElementById('modalRentabilidadV7');
        document.body.appendChild(elementoModal);
        const modal = new bootstrap.Modal(elementoModal);
        const contenido = document.getElementById('contenidoModalV7');
        let consulta;
        elementoModal.addEventListener('hidden.bs.modal', () => consulta?.abort());
        document.getElementById('tablaRentabilidadV7').addEventListener('click', async function (event) {
            const boton = event.target.closest('[data-accion]');
            if (!boton) return;
            const fila = tabla.row(boton.closest('tr')).data();
            if (!fila) return;
            consulta?.abort();
            const controlador = new AbortController();
            consulta = controlador;
            const tiempo = setTimeout(() => controlador.abort(), 60000);
            document.getElementById('tituloModalV7').textContent = `${boton.textContent} · ${fila.nombre} · Terminal ${fila.terminal}`;
            contenido.textContent = 'Cargando...';
            modal.show();
            const url = new URL(configuracion.urls[boton.dataset.accion]);
            Object.entries({ ...configuracion.filtros, terminal: fila.terminal, empresa: fila.empresa, alcance: 'terminal' }).forEach(([campo, valor]) => {
                if (Array.isArray(valor)) valor.forEach(item => url.searchParams.append(campo + '[]', item));
                else if (valor !== null) url.searchParams.set(campo, valor);
            });
            try {
                const respuesta = await fetch(url, { headers: { Accept: boton.dataset.accion === 'ver' ? 'text/html' : 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: controlador.signal });
                if (!respuesta.ok || respuesta.redirected) throw new Error('No se pudo cargar el detalle. Revisa la sesión e intenta de nuevo.');
                if (boton.dataset.accion === 'ver') {
                    const pagina = new DOMParser().parseFromString(await respuesta.text(), 'text/html');
                    const detalle = pagina.getElementById('detalleTerminalRentabilidadV7');
                    if (!detalle) throw new Error('No se recibió el detalle de esta terminal.');
                    if (consulta === controlador) contenido.replaceChildren(detalle);
                } else {
                    const datos = await respuesta.json();
                    if (consulta !== controlador) return;
                    if (boton.dataset.accion === 'resumen') {
                        const clasificacion = datos.clasificacion;
                        contenido.innerHTML = `<div class="mb-3"><h6>Clasificación por balance general</h6>${badge(clasificacion)}<p class="text-muted small mt-2 mb-0">${motivoClasificacion(clasificacion)}</p></div>
                            <div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Mes</th><th>Ventas</th><th>Premios sacados</th><th>Utilidad</th><th>Gastos</th><th>Balance general</th></tr></thead>
                            <tbody>${datos.meses.map(mes => `<tr><td>${escapar(mes.mes)}</td>${['ventas', 'premios_sacados', 'utilidad', 'gastos'].map(campo => `<td class="text-end">${moneda(mes[campo])}</td>`).join('')}${celdaBalance(mes.balance_general)}</tr>`).join('')}</tbody>
                            <tfoot class="fw-semibold"><tr><td>Totales</td>${['ventas', 'premios_sacados', 'utilidad', 'gastos'].map(campo => `<td class="text-end">${moneda(datos[campo])}</td>`).join('')}${celdaBalance(datos.balance_general)}</tr></tfoot></table></div>`;
                    } else {
                        contenido.innerHTML = `<div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Cuenta</th><th>Descripción</th><th>Tipo</th><th>Clasificación</th><th>Importe</th></tr></thead><tbody>${datos.movimientos.map(movimiento => `<tr>${[movimiento.cuenta, movimiento.descripcion, movimiento.tipo, movimiento.clasificacion].map(valor => `<td>${escapar(valor)}</td>`).join('')}<td class="text-end">${moneda(movimiento.importe)}</td></tr>`).join('')}</tbody><tfoot><tr><th colspan="4">Total costos + gastos + R20</th><th class="text-end">${moneda(datos.total)}</th></tr></tfoot></table></div>`;
                    }
                }
            } catch (error) {
                if (consulta === controlador && elementoModal.classList.contains('show')) contenido.textContent = error.name === 'AbortError' ? 'La consulta tardó demasiado. Intenta de nuevo.' : error.message;
            } finally {
                clearTimeout(tiempo);
            }
        });
    });
})();
