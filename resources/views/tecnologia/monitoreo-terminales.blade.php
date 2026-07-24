@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Monitoreo de Terminales</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('tecnologia.index') }}">Tecnología</a></li>
                                    <li class="breadcrumb-item active">Monitoreo de Terminales</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Generar monitoreo de asistencia</h5>
                    </div>
                    <div class="card-body">
                        <form id="formGenerarMonitoreo" class="row g-3 align-items-end">
                            <div class="col-sm-6 col-xl-2">
                                <label for="fechaInicio" class="form-label">Desde</label>
                                <input type="date" id="fechaInicio" class="form-control" value="{{ $fechaActual }}" max="{{ $fechaActual }}" required>
                            </div>
                            <div class="col-sm-6 col-xl-2">
                                <label for="fechaFin" class="form-label">Hasta</label>
                                <input type="date" id="fechaFin" class="form-control" value="{{ $fechaActual }}" max="{{ $fechaActual }}" required>
                            </div>
                            <div class="col-sm-6 col-xl-2">
                                <label for="filtroEstadoAsistencia" class="form-label">Estado de asistencia</label>
                                <select id="filtroEstadoAsistencia" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="FALTA">Falta</option>
                                    <option value="CUMPLE">Cumple</option>
                                    <option value="AVISO">Aviso</option>
                                    <option value="REQUIERE LLAMADA">Requiere llamada</option>
                                    <option value="PENDIENTE">Pendiente</option>
                                </select>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <input type="hidden" id="horaMonitoreo">
                                <label class="form-label">Hora evaluada</label>
                                <button type="button" id="configurarHoraButton" class="btn btn-soft-info w-100">
                                    <i class="ri-time-line align-bottom me-1"></i>
                                    <span id="horaMonitoreoTexto">Configurar hora</span>
                                </button>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <button type="submit" id="generarMonitoreoButton" class="btn btn-primary w-100">
                                    <i class="ri-search-line align-bottom me-1"></i>Generar
                                </button>
                            </div>
                        </form>
                        <div id="generarMonitoreoEstado" class="small mt-3" aria-live="polite"></div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6 col-xl">
                        <div class="card mb-0"><div class="card-body py-3"><span class="text-muted">Evaluaciones</span><h4 id="resumenTotal" class="mb-0">0</h4></div></div>
                    </div>
                    <div class="col-6 col-xl">
                        <div class="card mb-0"><div class="card-body py-3"><span class="text-muted">Faltas</span><h4 id="resumenFaltas" class="mb-0 text-danger">0</h4></div></div>
                    </div>
                    <div class="col-6 col-xl">
                        <div class="card mb-0"><div class="card-body py-3"><span class="text-muted">Cumplen</span><h4 id="resumenCumplen" class="mb-0 text-success">0</h4></div></div>
                    </div>
                    <div class="col-6 col-xl">
                        <button type="button" class="card mb-0 w-100 text-start border-0 detalle-estado-card" data-estado="AVISO" title="Ver terminales con aviso">
                            <span class="card-body py-3">
                                <span class="text-muted">Avisos</span>
                                <span class="d-flex align-items-center justify-content-between">
                                    <span id="resumenAvisos" class="h4 mb-0 text-warning">0</span>
                                    <i class="ri-eye-line text-warning fs-5"></i>
                                </span>
                            </span>
                        </button>
                    </div>
                    <div class="col-6 col-xl">
                        <button type="button" class="card mb-0 w-100 text-start border-0 detalle-estado-card" data-estado="REQUIERE LLAMADA" title="Ver terminales que requieren llamada">
                            <span class="card-body py-3">
                                <span class="text-muted">Requieren llamada</span>
                                <span class="d-flex align-items-center justify-content-between">
                                    <span id="resumenLlamadas" class="h4 mb-0 text-danger">0</span>
                                    <i class="ri-phone-line text-danger fs-5"></i>
                                </span>
                            </span>
                        </button>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header"><h5 class="card-title mb-0">Resultado del monitoreo</h5></div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="tablaMonitoreoTerminales" class="table table-bordered table-striped align-middle w-100">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Agencia</th>
                                                <th>Coordinador</th>
                                                <th>Comentario</th>
                                                <th>Fecha</th>
                                                <th>Hora evaluada</th>
                                                <th>Asistencia</th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="comentarioTerminalModal" tabindex="-1" aria-labelledby="comentarioTerminalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form id="formComentarioTerminal">
                    <div class="modal-header">
                        <h5 class="modal-title" id="comentarioTerminalModalLabel">Comentario de monitoreo</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="comentarioAgenciaId">
                        <input type="hidden" id="comentarioFechaIso">

                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Agencia</label>
                                <input type="text" id="comentarioAgenciaNombre" class="form-control bg-light" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Fecha evaluada</label>
                                <input type="text" id="comentarioFechaVisible" class="form-control bg-light" readonly>
                            </div>
                        </div>

                        <div>
                            <label class="form-label" for="comentarioDetalle">Detalle</label>
                            <textarea id="comentarioDetalle" class="form-control" rows="6" maxlength="2000"
                                placeholder="Escriba el detalle del monitoreo..."></textarea>
                            <div class="form-text">Máximo 2,000 caracteres.</div>
                            <div id="comentarioEstado" class="small mt-2" aria-live="polite"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" id="guardarComentarioButton" class="btn btn-primary">
                            <i class="ri-save-line align-bottom me-1"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="detalleEstadoTerminalesModal" tabindex="-1" aria-labelledby="detalleEstadoTerminalesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="detalleEstadoTerminalesModalLabel">Detalle de terminales</h5>
                        <div id="detalleEstadoTerminalesResumen" class="small text-muted"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Terminal</th>
                                    <th>Agencia</th>
                                    <th>Coordinador</th>
                                    <th>Fecha</th>
                                    <th>Apertura</th>
                                    <th>Ponche</th>
                                    <th>Tardanza</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody id="detalleEstadoTerminalesBody"></tbody>
                        </table>
                    </div>
                    <div id="detalleEstadoTerminalesVacio" class="alert alert-light text-center mb-0 d-none">
                        No hay terminales en este estado para el monitoreo actual.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-danger btn-exportar-estado" data-formato="pdf">
                        <i class="ri-file-pdf-2-line me-1"></i>Descargar PDF
                    </button>
                    <button type="button" class="btn btn-success btn-exportar-estado" data-formato="excel">
                        <i class="ri-file-excel-2-line me-1"></i>Descargar Excel
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalElement = document.getElementById('comentarioTerminalModal');
            const modal = new bootstrap.Modal(modalElement);
            const detailModalElement = document.getElementById('detalleEstadoTerminalesModal');
            const detailModal = new bootstrap.Modal(detailModalElement);
            const detailModalTitle = document.getElementById('detalleEstadoTerminalesModalLabel');
            const detailModalSummary = document.getElementById('detalleEstadoTerminalesResumen');
            const detailModalBody = document.getElementById('detalleEstadoTerminalesBody');
            const detailModalEmpty = document.getElementById('detalleEstadoTerminalesVacio');
            const generateForm = document.getElementById('formGenerarMonitoreo');
            const generateButton = document.getElementById('generarMonitoreoButton');
            const generateStatus = document.getElementById('generarMonitoreoEstado');
            const startDateInput = document.getElementById('fechaInicio');
            const endDateInput = document.getElementById('fechaFin');
            const monitoringTimeInput = document.getElementById('horaMonitoreo');
            const monitoringTimeText = document.getElementById('horaMonitoreoTexto');
            const configureTimeButton = document.getElementById('configurarHoraButton');
            const attendanceFilter = document.getElementById('filtroEstadoAsistencia');
            const commentForm = document.getElementById('formComentarioTerminal');
            const agencyIdInput = document.getElementById('comentarioAgenciaId');
            const agencyNameInput = document.getElementById('comentarioAgenciaNombre');
            const commentDateInput = document.getElementById('comentarioFechaIso');
            const commentVisibleDateInput = document.getElementById('comentarioFechaVisible');
            const commentInput = document.getElementById('comentarioDetalle');
            const commentStatus = document.getElementById('comentarioEstado');
            const saveButton = document.getElementById('guardarComentarioButton');
            const generateUrl = @json(route('tecnologia.monitoreo-terminales.generar'));
            const generateTokenUrl = @json(url('/generar-token'));
            const saveUrl = @json(route('tecnologia.monitoreo-terminales.comentario'));
            const exportUrl = @json(route('tecnologia.monitoreo-terminales.exportar'));
            const monitoringTimes = @json($horariosMonitoreo);
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            let activeRow = null;
            let activeDetailState = null;

            function escapeHtml(value) {
                const element = document.createElement('div');
                element.textContent = value ?? '';

                return element.innerHTML;
            }

            const table = $('#tablaMonitoreoTerminales').DataTable({
                data: @json($registros),
                pageLength: 25,
                order: [[3, 'desc'], [0, 'asc']],
                responsive: false,
                scrollX: true,
                autoWidth: false,
                columns: [
                    { data: 'agencia', render: data => escapeHtml(data) },
                    { data: 'coordinador', render: data => escapeHtml(data) },
                    {
                        data: 'comentario',
                        orderable: false,
                        className: 'text-center',
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return data || '';
                            }

                            const hasComment = Boolean(data);
                            const buttonClass = hasComment ? 'btn-soft-success' : 'btn-soft-primary';
                            const icon = hasComment ? 'ri-edit-2-line' : 'ri-chat-new-line';
                            const label = hasComment ? 'Ver / editar' : 'Agregar comentario';

                            return `<button type="button" class="btn ${buttonClass} btn-sm btn-comentario-monitor" data-agencia-id="${Number(row.agencia_id)}" data-fecha="${escapeHtml(row.fecha_iso)}"><i class="${icon} align-bottom me-1"></i><span>${label}</span></button>`;
                        }
                    },
                    {
                        data: 'fecha',
                        render: function (data, type, row) {
                            return type === 'sort' || type === 'type' ? row.fecha_iso : escapeHtml(data);
                        }
                    },
                    {
                        data: 'hora_monitoreo',
                        className: 'text-center',
                        render: data => escapeHtml(data)
                    },
                    {
                        data: 'estado',
                        className: 'text-center',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data;
                            }

                            const classes = {
                                CUMPLE: 'bg-success-subtle text-success',
                                AVISO: 'bg-warning-subtle text-warning',
                                FALTA: 'bg-danger-subtle text-danger',
                                'REQUIERE LLAMADA': 'bg-danger text-white',
                                PENDIENTE: 'bg-secondary-subtle text-secondary'
                            }[data] || 'bg-secondary-subtle text-secondary';

                            return `<span class="badge ${classes} fs-6 fw-bold px-3 py-2">${escapeHtml(data)}</span>`;
                        }
                    }
                ],
                columnDefs: [
                    { width: '24%', targets: 0 },
                    { width: '20%', targets: 1 },
                    { width: '20%', targets: 2 },
                    { width: '12%', targets: 3 },
                    { width: '12%', targets: 4 },
                    { width: '12%', targets: 5 }
                ],
                language: { url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json' }
            });

            function setGenerateStatus(message, className = 'text-muted') {
                generateStatus.textContent = message;
                generateStatus.className = `small mt-3 ${className}`;
            }

            function setCommentStatus(message, className = 'text-muted') {
                commentStatus.textContent = message;
                commentStatus.className = `small mt-2 ${className}`;
            }

            function updateSummary(data) {
                document.getElementById('resumenTotal').textContent = data.total ?? 0;
                document.getElementById('resumenFaltas').textContent = data.faltas ?? 0;
                document.getElementById('resumenCumplen').textContent = data.cumplen ?? 0;
                document.getElementById('resumenAvisos').textContent = data.avisos ?? 0;
                document.getElementById('resumenLlamadas').textContent = data.llamadas ?? 0;
            }

            function detailRows() {
                return table.rows().data().toArray().filter(row => row.estado === activeDetailState);
            }

            function renderDetailModal(state) {
                activeDetailState = state;
                const rows = detailRows();
                const requiresCall = state === 'REQUIERE LLAMADA';
                detailModalTitle.textContent = requiresCall ? 'Terminales que requieren llamada' : 'Terminales con aviso';
                detailModalSummary.textContent = `${rows.length} terminal${rows.length === 1 ? '' : 'es'} en el resultado actual.`;
                detailModalBody.innerHTML = '';
                detailModalEmpty.classList.toggle('d-none', rows.length > 0);

                rows.forEach(row => {
                    const delay = row.minutos_tardanza === null || row.minutos_tardanza === undefined
                        ? '-'
                        : `${Number(row.minutos_tardanza)} min`;
                    const punch = row.hora_ponche || 'Sin ponche';
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${escapeHtml(row.terminal)}</td>
                        <td>${escapeHtml(row.agencia)}</td>
                        <td>${escapeHtml(row.coordinador)}</td>
                        <td>${escapeHtml(row.fecha)}</td>
                        <td class="text-center">${escapeHtml(row.hora_apertura)}</td>
                        <td class="text-center">${escapeHtml(punch)}</td>
                        <td class="text-center">${escapeHtml(delay)}</td>
                        <td><span class="badge ${requiresCall ? 'bg-danger' : 'bg-warning-subtle text-warning'}">${escapeHtml(row.estado)}</span></td>
                    `;
                    detailModalBody.appendChild(tr);
                });

                document.querySelectorAll('.btn-exportar-estado').forEach(button => {
                    button.disabled = rows.length === 0;
                });
            }

            document.querySelectorAll('.detalle-estado-card').forEach(card => {
                card.addEventListener('click', function () {
                    renderDetailModal(this.dataset.estado);
                    detailModal.show();
                });
            });

            async function downloadDetail(format, button) {
                const rows = detailRows();

                if (rows.length === 0) {
                    return;
                }

                button.disabled = true;

                try {
                    const response = await fetch(exportUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/octet-stream, application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            estado: activeDetailState,
                            formato: format,
                            registros: rows.map(row => ({
                                agencia: row.agencia,
                                terminal: String(row.terminal || ''),
                                coordinador: row.coordinador,
                                fecha: row.fecha,
                                hora_apertura: row.hora_apertura,
                                hora_ponche: row.hora_ponche,
                                minutos_tardanza: row.minutos_tardanza,
                                estado: row.estado
                            }))
                        })
                    });

                    if (!response.ok) {
                        const contentType = response.headers.get('content-type') || '';
                        const error = contentType.includes('application/json') ? await response.json() : null;
                        const validationMessage = error?.errors ? Object.values(error.errors).flat()[0] : null;
                        throw new Error(validationMessage || error?.message || 'No se pudo generar la descarga.');
                    }

                    const blob = await response.blob();
                    const disposition = response.headers.get('content-disposition') || '';
                    const fileNameMatch = disposition.match(/filename="?([^";]+)"?/i);
                    const extension = format === 'excel' ? 'xlsx' : 'pdf';
                    const fallbackName = `monitoreo_${activeDetailState.toLowerCase().replaceAll(' ', '_')}.${extension}`;
                    const objectUrl = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = objectUrl;
                    link.download = fileNameMatch?.[1] || fallbackName;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    URL.revokeObjectURL(objectUrl);
                } catch (error) {
                    await Swal.fire('Error', error.message || 'No se pudo generar la descarga.', 'error');
                } finally {
                    button.disabled = false;
                }
            }

            document.querySelectorAll('.btn-exportar-estado').forEach(button => {
                button.addEventListener('click', function () {
                    downloadDetail(this.dataset.formato, this);
                });
            });

            attendanceFilter.addEventListener('change', function () {
                const value = $.fn.dataTable.util.escapeRegex(this.value);
                table.column(5).search(value ? `^${value}$` : '', true, false).draw();
            });

            function formatMonitoringTime(value) {
                const [hours, minutes] = value.split(':').map(Number);
                const period = hours >= 12 ? 'PM' : 'AM';
                const displayHours = hours % 12 || 12;

                return `${displayHours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')} ${period}`;
            }

            async function configureMonitoringTime() {
                if (Object.keys(monitoringTimes).length === 0) {
                    await Swal.fire(
                        'Sin horarios configurados',
                        'No hay horarios disponibles en las agencias de Lotobet.',
                        'warning'
                    );

                    return false;
                }

                const result = await Swal.fire({
                    title: 'Configurar hora evaluada',
                    text: 'Seleccione una de las horas configuradas en las agencias.',
                    input: 'select',
                    inputOptions: monitoringTimes,
                    inputPlaceholder: 'Seleccione un horario',
                    inputValue: monitoringTimeInput.value,
                    showCancelButton: true,
                    confirmButtonText: 'Aplicar hora',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#405189',
                    inputValidator: value => !value ? 'Debe seleccionar un horario.' : undefined
                });

                if (!result.isConfirmed) {
                    return false;
                }

                monitoringTimeInput.value = result.value;
                monitoringTimeText.textContent = formatMonitoringTime(result.value);
                configureTimeButton.classList.remove('btn-soft-info');
                configureTimeButton.classList.add('btn-soft-success');

                return true;
            }

            configureTimeButton.addEventListener('click', configureMonitoringTime);

            async function generateLotobetToken() {
                Swal.fire({
                    title: 'Generando token Lotobet...',
                    text: 'Por favor espera.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => Swal.showLoading()
                });

                const response = await fetch(generateTokenUrl, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || 'No se pudo generar el token de Lotobet.');
                }

                Swal.close();
            }

            async function generateMonitoring(tokenRetryAttempted = false) {
                const query = new URLSearchParams({
                    fecha_inicio: startDateInput.value,
                    fecha_fin: endDateInput.value,
                    hora_monitoreo: monitoringTimeInput.value
                });
                const response = await fetch(`${generateUrl}?${query.toString()}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await response.json();

                if (response.status === 409 && data.code === 'LOTOBET_TOKEN_REQUIRED' && !tokenRetryAttempted) {
                    const confirmation = await Swal.fire({
                        title: 'Token de Lotobet requerido',
                        text: `${data.message} ¿Deseas generarlo ahora?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Generar token',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#405189'
                    });

                    if (!confirmation.isConfirmed) {
                        setGenerateStatus('Generación cancelada: se requiere un token válido de Lotobet.', 'text-warning');

                        return;
                    }

                    await generateLotobetToken();
                    setGenerateStatus('Token generado. Consultando asistencias...', 'text-muted');

                    return generateMonitoring(true);
                }

                if (!response.ok) {
                    const validationMessage = data.errors ? Object.values(data.errors).flat()[0] : null;
                    throw new Error(validationMessage || data.message || 'No se pudo generar el monitoreo.');
                }

                table.clear().rows.add(data.data || []).draw();
                updateSummary(data);
                setGenerateStatus(`Monitoreo generado: ${data.total ?? 0} evaluaciones.`, 'text-success');
            }

            generateForm.addEventListener('submit', async function (event) {
                event.preventDefault();

                if (!monitoringTimeInput.value && !await configureMonitoringTime()) {
                    setGenerateStatus('Debe configurar la hora que desea evaluar.', 'text-warning');

                    return;
                }

                generateButton.disabled = true;
                setGenerateStatus('Consultando asistencias...', 'text-muted');

                try {
                    await generateMonitoring();
                } catch (error) {
                    Swal.close();
                    setGenerateStatus(error.message || 'No se pudo generar el monitoreo.', 'text-danger');
                    await Swal.fire('Error', error.message || 'No se pudo generar el monitoreo.', 'error');
                } finally {
                    generateButton.disabled = false;
                }
            });

            $('#tablaMonitoreoTerminales tbody').on('click', '.btn-comentario-monitor', function () {
                activeRow = table.row($(this).closest('tr'));
                const row = activeRow.data();
                agencyIdInput.value = row.agencia_id;
                agencyNameInput.value = row.agencia || 'Sin identificar';
                commentDateInput.value = row.fecha_iso;
                commentVisibleDateInput.value = row.fecha;
                commentInput.value = row.comentario || '';
                setCommentStatus('');
                saveButton.disabled = false;
                modal.show();
            });

            modalElement.addEventListener('shown.bs.modal', function () {
                commentInput.focus();
            });

            commentForm.addEventListener('submit', async function (event) {
                event.preventDefault();
                saveButton.disabled = true;
                setCommentStatus('Guardando...', 'text-muted');

                try {
                    const response = await fetch(saveUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            agencia_id: Number(agencyIdInput.value),
                            fecha: commentDateInput.value,
                            comentario: commentInput.value
                        })
                    });
                    const data = await response.json();

                    if (!response.ok) {
                        const validationMessage = data.errors ? Object.values(data.errors).flat()[0] : null;
                        throw new Error(validationMessage || data.message || 'No se pudo guardar el comentario.');
                    }

                    const row = activeRow.data();
                    row.comentario = data.data.comentario || '';
                    activeRow.data(row).invalidate().draw(false);
                    setCommentStatus('Comentario guardado correctamente.', 'text-success');
                    setTimeout(() => modal.hide(), 450);
                } catch (error) {
                    setCommentStatus(error.message || 'No se pudo guardar el comentario.', 'text-danger');
                } finally {
                    saveButton.disabled = false;
                }
            });
        });
    </script>
@endsection
