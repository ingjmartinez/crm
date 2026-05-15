@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Novedades_de_Horario</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('recursos-humanos.index') }}">Recursos Humanos</a></li>
                                    <li class="breadcrumb-item active">Novedades de Horario</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Filtros</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-2">
                                        <label for="sistema" class="form-label">Empresa</label>
                                        <select id="sistema" class="form-control">
                                            <option value="todos">Todos</option>
                                            <option value="lotobet">Lotobet</option>
                                            <option value="lotonet">Lotonet</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                                        <input type="date" id="fecha_inicio" class="form-control" value="{{ date('Y-m-01') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="fecha_fin" class="form-label">Fecha Fin</label>
                                        <input type="date" id="fecha_fin" class="form-control" value="{{ date('Y-m-d') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="detalle_filtro" class="form-label">Detalle</label>
                                        <select id="detalle_filtro" class="form-control">
                                            <option value="todos">Todos</option>
                                            <option value="cumple">Cumple</option>
                                            <option value="tiene_falta">Tiene falta</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Horario</label>
                                        <button type="button" class="btn btn-info w-100" id="btnConfigurarHorario">
                                            <i class="ri-settings-3-line"></i> Configurar
                                        </button>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="button" class="btn btn-primary w-100" id="btnBuscar">
                                            <i class="ri-search-line"></i> Consultar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <p class="text-uppercase fw-medium text-muted mb-1">Total Registros</p>
                                <h4 class="mb-0" id="totalRegistros">0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <p class="text-uppercase fw-medium text-muted mb-1">Terminales</p>
                                <h4 class="mb-0" id="totalTerminales">0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <p class="text-uppercase fw-medium text-muted mb-1">Agencias</p>
                                <h4 class="mb-0" id="totalAgencias">0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <p class="text-uppercase fw-medium text-muted mb-1">Horas Acumuladas</p>
                                <h4 class="mb-0" id="totalHorasAcumuladas">0.00</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h5 class="card-title mb-0">Listado de Novedades</h5>
                                <button type="button" class="btn btn-success btn-sm" id="btnExportarExcel">
                                    <i class="ri-file-excel-2-line"></i> Exportar Excel
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="tableNovedadesHorario" class="table table-bordered table-striped align-middle" style="width: 100%;">
                                        <thead>
                                            <tr>
                                                <th>Terminal</th>
                                                <th>Nombre de Agencia</th>
                                                <th>Ruta</th>
                                                <th>Nombre de Empleado</th>
                                                <th>Cedula</th>
                                                <th>Fecha</th>
                                                <th>Primer Login</th>
                                                <th>Ultimo Login</th>
                                                <th class="text-end">Horas_Acumuladas</th>
                                                <th>Detalle</th>
                                                <th>Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="detalleFaltantesHorarioModal" tabindex="-1" aria-labelledby="detalleFaltantesHorarioModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detalleFaltantesHorarioModalLabel">Novedades de Horarios</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted mb-1">Nombre</label>
                            <div class="fw-semibold" id="detalleHorarioNombre">Sin especificar</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted mb-1">Cedula</label>
                            <div class="fw-semibold" id="detalleHorarioCedula">-</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted mb-1">Terminal</label>
                            <div class="fw-semibold" id="detalleHorarioAgencia">-</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted mb-1">Total de Faltantes</label>
                            <div class="fw-semibold" id="detalleHorarioTotalFaltantes">0.00 horas</div>
                            <small class="text-muted">Calculado por dia</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted mb-1">Monto Total</label>
                            <div class="fw-semibold" id="detalleHorarioMontoTotal">$0.00</div>
                            <small class="text-muted">Suma de los montos diarios</small>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th class="text-end">Horas Faltantes</th>
                                    <th class="text-end">Monto del Dia</th>
                                </tr>
                            </thead>
                            <tbody id="detalleHorarioFechasFaltantes"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        let tableNovedadesHorario;
        let horasRequeridasReporte = '';
        let valorHoraReporte = '';

        function formatearNumero(valor) {
            return Number(valor || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function formatearMonto(valor) {
            return `$${formatearNumero(valor)}`;
        }

        function formatearHorasMinutos(valor) {
            const numero = Number(valor || 0);
            const signo = numero < 0 ? '-' : '';
            const valorAbsoluto = Math.abs(numero);
            const horas = Math.floor(valorAbsoluto);
            const minutos = Math.round((valorAbsoluto - horas) * 100);
            const partes = [];

            if (horas > 0) {
                partes.push(`${horas} ${horas === 1 ? 'hora' : 'horas'}`);
            }

            if (minutos > 0 || partes.length === 0) {
                partes.push(`${minutos} ${minutos === 1 ? 'minuto' : 'minutos'}`);
            }

            return `${signo}${partes.join(' ')}`;
        }

        function escaparHtml(valor) {
            return String(valor ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function renderizarDetalle(row) {
            if (!horasRequeridasReporte) {
                return '<span class="text-muted">Sin configurar</span>';
            }

            const horasAcumuladas = Number(row.horas_acumuladas || 0);
            const horasRequeridas = Number(horasRequeridasReporte || 0);

            if (horasAcumuladas >= horasRequeridas) {
                return '<span class="badge bg-success">Cumple</span>';
            }

            const faltante = Math.max(horasRequeridas - horasAcumuladas, 0);
            const montoFalta = faltante * Number(valorHoraReporte || 0);

            return `
                <span class="badge bg-danger">Tiene falta</span>
                <div class="small text-muted mt-1">Faltan ${formatearNumero(faltante)} horas</div>
                <div class="small text-muted">Monto: ${formatearNumero(montoFalta)}</div>
            `;
        }

        function obtenerTextoDetalle(row) {
            const horasAcumuladas = Number(row.horas_acumuladas || 0);
            const horasRequeridas = Number(horasRequeridasReporte || 0);

            if (horasAcumuladas >= horasRequeridas) {
                return 'Cumple';
            }

            const faltante = Math.max(horasRequeridas - horasAcumuladas, 0);
            const montoFalta = faltante * Number(valorHoraReporte || 0);

            return `Tiene falta - Faltan ${formatearNumero(faltante)} horas - Monto: ${formatearMonto(montoFalta)}`;
        }

        async function configurarHoraReporte() {
            const resultado = await Swal.fire({
                title: 'Configurar horario',
                html: `
                    <div class="text-start">
                        <label for="horas_requeridas" class="form-label">Horas requeridas</label>
                        <input type="number" id="horas_requeridas" class="form-control" min="1" step="1" placeholder="Ej: 8" value="${escaparHtml(horasRequeridasReporte)}">
                        <label for="valor_hora" class="form-label mt-3">Valor de una hora</label>
                        <input type="number" id="valor_hora" class="form-control" min="0.01" step="0.01" placeholder="Ej: 150" value="${escaparHtml(valorHoraReporte)}">
                        <small class="text-muted d-block mt-2">Ejemplo: 8 representa 8 horas. El valor de una hora se usara para calcular el monto de la falta.</small>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Guardar',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const horasRequeridas = document.getElementById('horas_requeridas').value;
                    const valorHora = document.getElementById('valor_hora').value;
                    const horasEnteras = Number(horasRequeridas);
                    const valorHoraNumero = Number(valorHora);

                    if (!Number.isInteger(horasEnteras) || horasEnteras <= 0) {
                        Swal.showValidationMessage('Debe capturar un numero entero mayor que cero.');
                        return false;
                    }

                    if (!Number.isFinite(valorHoraNumero) || valorHoraNumero <= 0) {
                        Swal.showValidationMessage('Debe capturar un valor de hora valido.');
                        return false;
                    }

                    return {
                        horas_requeridas: String(horasEnteras),
                        valor_hora: String(valorHoraNumero)
                    };
                }
            });

            if (!resultado.isConfirmed) {
                return;
            }

            horasRequeridasReporte = resultado.value.horas_requeridas;
            valorHoraReporte = resultado.value.valor_hora;

            if (tableNovedadesHorario) {
                tableNovedadesHorario.ajax.reload();
            }
        }

        function actualizarResumen(resumen) {
            document.getElementById('totalRegistros').textContent = Number(resumen.total || 0).toLocaleString('en-US');
            document.getElementById('totalTerminales').textContent = Number(resumen.terminales || 0).toLocaleString('en-US');
            document.getElementById('totalAgencias').textContent = Number(resumen.agencias || 0).toLocaleString('en-US');
            document.getElementById('totalHorasAcumuladas').textContent = formatearNumero(resumen.horas_acumuladas);
        }

        function mostrarModalDetalleHorario(data) {
            const fechasBody = document.getElementById('detalleHorarioFechasFaltantes');
            const detalle = data.detalle || [];

            document.getElementById('detalleHorarioNombre').textContent = data.nombre || 'Sin especificar';
            document.getElementById('detalleHorarioCedula').textContent = data.cedula || '-';
            document.getElementById('detalleHorarioAgencia').textContent = data.terminal || '-';
            document.getElementById('detalleHorarioTotalFaltantes').textContent = formatearHorasMinutos(data.total_faltantes);
            document.getElementById('detalleHorarioMontoTotal').textContent = formatearMonto(data.monto_total);

            fechasBody.innerHTML = detalle.length
                ? detalle.map(item => `
                    <tr>
                        <td>${escaparHtml(item.fecha)}</td>
                        <td class="text-end">${formatearHorasMinutos(item.horas_faltantes)}</td>
                        <td class="text-end">${formatearMonto(item.monto_dia)}</td>
                    </tr>
                `).join('')
                : '<tr><td colspan="3" class="text-muted">Sin faltas en el rango seleccionado</td></tr>';

            const modal = new bootstrap.Modal(document.getElementById('detalleFaltantesHorarioModal'));
            modal.show();
        }

        function verDetalleHorario(row) {
            const params = new URLSearchParams({
                sistema: document.getElementById('sistema').value,
                fecha_inicio: document.getElementById('fecha_inicio').value,
                fecha_fin: document.getElementById('fecha_fin').value,
                horas_requeridas: horasRequeridasReporte,
                valor_hora: valorHoraReporte || '0',
                cedula: row.cedula || '',
                terminal: row.terminal || '',
                empresa: row.empresa || ''
            });

            Swal.fire({
                title: 'Consultando detalle',
                text: 'Cargando faltantes por dia...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch(`/recursos-humanos/novedades-horario/detalle?${params}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('No se pudo cargar el detalle.');
                    }

                    return response.json();
                })
                .then(data => {
                    Swal.close();
                    mostrarModalDetalleHorario(data);
                })
                .catch(() => {
                    Swal.fire('Error', 'No se pudo cargar el detalle de faltantes.', 'error');
                });
        }

        function obtenerParametrosReporte(extra = {}) {
            return new URLSearchParams({
                sistema: document.getElementById('sistema').value,
                fecha_inicio: document.getElementById('fecha_inicio').value,
                fecha_fin: document.getElementById('fecha_fin').value,
                horas_requeridas: horasRequeridasReporte,
                detalle: document.getElementById('detalle_filtro').value,
                ...extra
            });
        }

        function descargarExcelHtml(nombreArchivo, encabezados, filas) {
            const tabla = `
                <table border="1">
                    <thead>
                        <tr>${encabezados.map(encabezado => `<th>${escaparHtml(encabezado)}</th>`).join('')}</tr>
                    </thead>
                    <tbody>
                        ${filas.map(fila => `
                            <tr>${fila.map(valor => `<td>${escaparHtml(valor)}</td>`).join('')}</tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
            const blob = new Blob([`\ufeff${tabla}`], { type: 'application/vnd.ms-excel;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');

            link.href = url;
            link.download = nombreArchivo;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }

        function exportarExcel() {
            if (!horasRequeridasReporte) {
                Swal.fire('Horario requerido', 'Primero debe configurar las horas requeridas para exportar.', 'warning');
                return;
            }

            Swal.fire({
                title: 'Exportando',
                text: 'Preparando archivo Excel...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const searchValue = tableNovedadesHorario ? tableNovedadesHorario.search() : '';
            const params = obtenerParametrosReporte({
                draw: 1,
                start: 0,
                length: 100000,
                export: 1,
                'search[value]': searchValue
            });

            fetch(`/recursos-humanos/novedades-horario/list?${params}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('No se pudo exportar.');
                    }

                    return response.json();
                })
                .then(json => {
                    const filas = (json.data || []).map(row => [
                        row.terminal,
                        row.nombre_agencia,
                        row.ruta,
                        row.nombre_empleado,
                        row.cedula,
                        row.fecha,
                        row.primer_login,
                        row.ultimo_login,
                        formatearNumero(row.horas_acumuladas),
                        obtenerTextoDetalle(row)
                    ]);

                    descargarExcelHtml(
                        `novedades_horario_${document.getElementById('fecha_inicio').value}_${document.getElementById('fecha_fin').value}.xls`,
                        ['Terminal', 'Nombre de Agencia', 'Ruta', 'Nombre de Empleado', 'Cedula', 'Fecha', 'Primer Login', 'Ultimo Login', 'Horas_Acumuladas', 'Detalle'],
                        filas
                    );
                    Swal.close();
                })
                .catch(() => {
                    Swal.fire('Error', 'No se pudo exportar el archivo Excel.', 'error');
                });
        }

        function cargarNovedadesHorario() {
            const sistema = document.getElementById('sistema').value;
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            const detalleFiltro = document.getElementById('detalle_filtro').value;

            if (!fechaInicio || !fechaFin) {
                Swal.fire('Error', 'Seleccione la fecha de inicio y fin.', 'error');
                return;
            }

            if (fechaInicio && fechaFin && fechaInicio > fechaFin) {
                Swal.fire('Error', 'La fecha de inicio no puede ser mayor que la fecha fin.', 'error');
                return;
            }

            if (!horasRequeridasReporte) {
                Swal.fire('Horario requerido', 'Primero debe configurar las horas requeridas para realizar el calculo.', 'warning');
                return;
            }

            if (tableNovedadesHorario) {
                tableNovedadesHorario.destroy();
            }

            Swal.fire({
                title: 'Consultando',
                text: 'Cargando novedades de horario...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            tableNovedadesHorario = $('#tableNovedadesHorario').DataTable({
                processing: true,
                serverSide: true,
                searchDelay: 450,
                ajax: {
                    url: '/recursos-humanos/novedades-horario/list',
                    type: 'GET',
                    data: {
                        sistema: sistema,
                        fecha_inicio: fechaInicio,
                        fecha_fin: fechaFin,
                        horas_requeridas: horasRequeridasReporte,
                        detalle: detalleFiltro
                    },
                    dataSrc: function (json) {
                        actualizarResumen(json.resumen || {});
                        Swal.close();
                        return json.data || [];
                    },
                    error: function () {
                        Swal.fire('Error', 'No se pudo cargar el reporte.', 'error');
                    }
                },
                columns: [
                    { data: 'terminal' },
                    { data: 'nombre_agencia' },
                    { data: 'ruta' },
                    { data: 'nombre_empleado' },
                    { data: 'cedula' },
                    { data: 'fecha' },
                    { data: 'primer_login' },
                    { data: 'ultimo_login' },
                    {
                        data: 'horas_acumuladas',
                        className: 'text-end',
                        render: function (data) {
                            return formatearNumero(data);
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                const horasAcumuladas = Number(row.horas_acumuladas || 0);
                                const horasRequeridas = Number(horasRequeridasReporte || 0);
                                return horasAcumuladas >= horasRequeridas ? 'Cumple' : 'Tiene falta';
                            }

                            return renderizarDetalle(row);
                        }
                    },
                    {
                        data: null,
                        className: 'text-center',
                        defaultContent: '',
                        orderable: false,
                        searchable: false,
                        render: function (data, type) {
                            if (type !== 'display') {
                                return '';
                            }

                            return '<button type="button" class="btn btn-sm btn-info btn-ver-detalle-horario"><i class="ri-eye-line"></i> Ver</button>';
                        }
                    }
                ],
                autoWidth: false,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                },
                order: [[0, 'desc']],
                pageLength: 25,
                lengthMenu: [25, 50, 100, 200]
            });
        }

        document.getElementById('btnConfigurarHorario').addEventListener('click', configurarHoraReporte);
        document.getElementById('btnBuscar').addEventListener('click', cargarNovedadesHorario);
        document.getElementById('btnExportarExcel').addEventListener('click', exportarExcel);

        $('#tableNovedadesHorario').on('click', '.btn-ver-detalle-horario', function () {
            const row = tableNovedadesHorario.row($(this).closest('tr')).data();

            if (!row) {
                Swal.fire('Error', 'No se pudo leer la fila seleccionada.', 'error');
                return;
            }

            verDetalleHorario(row);
        });
    </script>
@endsection
