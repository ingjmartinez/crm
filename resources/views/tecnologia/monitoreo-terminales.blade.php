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
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <h5 class="card-title mb-0">Generar monitoreo de asistencia</h5>
                        <button type="button" id="agenciasPlazaButton" class="btn btn-soft-primary">
                            <i class="ri-map-pin-user-line align-bottom me-1"></i>
                            Agencias en plaza
                            <span id="agenciasPlazaCount" class="badge bg-primary ms-1">{{ $agenciasPlazaCount }}</span>
                        </button>
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
                                <div class="dropdown">
                                    <button type="button" id="filtroEstadoAsistencia"
                                        class="btn btn-outline-secondary dropdown-toggle w-100 text-start d-flex align-items-center justify-content-between"
                                        data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                        <span id="filtroEstadoAsistenciaTexto">Todos</span>
                                    </button>
                                    <div class="dropdown-menu w-100 p-2" aria-labelledby="filtroEstadoAsistencia">
                                        <button type="button" id="mostrarTodosEstadosButton" class="dropdown-item rounded mb-1">
                                            Todos
                                        </button>
                                        <div class="dropdown-divider"></div>
                                        @foreach ([
                                            'CUMPLE' => 'Cumple',
                                            'AVISO' => 'Aviso',
                                            'FALTA' => 'Falta',
                                            'SIN AGENTE DE VENTA' => 'Sin agente de venta',
                                        ] as $estado => $etiqueta)
                                            <label class="dropdown-item rounded d-flex align-items-center gap-2 mb-0">
                                                <input type="checkbox" class="form-check-input mt-0 filtro-estado-asistencia-opcion"
                                                    value="{{ $estado }}" data-label="{{ $etiqueta }}">
                                                <span>{{ $etiqueta }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <input type="hidden" id="horaMonitoreo">
                                <input type="hidden" id="tipoHorarioMonitoreo">
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
                        <button type="button" class="card mb-0 w-100 text-start border-0 detalle-estado-card" data-estado="SIN AGENTE DE VENTA" title="Ver terminales sin agente de venta">
                            <span class="card-body py-3">
                                <span class="text-muted">Sin agente de venta</span>
                                <span class="d-flex align-items-center justify-content-between">
                                    <span id="resumenSinAgente" class="h4 mb-0 text-danger">0</span>
                                    <i class="ri-user-unfollow-line text-danger fs-5"></i>
                                </span>
                            </span>
                        </button>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header d-flex align-items-center gap-2">
                        <i class="ri-information-line text-primary fs-5"></i>
                        <h5 class="card-title mb-0">Leyenda de estados</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Los minutos se calculan desde la hora de apertura del turno AM o PM seleccionado.
                        </p>
                        <div class="row g-3">
                            <div class="col-md-6 col-xl">
                                <div class="h-100 border rounded p-3">
                                    <span class="badge bg-success-subtle text-success fs-6 mb-2">CUMPLE</span>
                                    <p class="mb-0 small">Ponche registrado hasta 5 minutos después de la apertura.</p>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl">
                                <div class="h-100 border rounded p-3">
                                    <span class="badge bg-warning-subtle text-warning fs-6 mb-2">AVISO</span>
                                    <p class="mb-0 small">Ponche registrado entre 6 y 10 minutos después de la apertura.</p>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl">
                                <div class="h-100 border rounded p-3">
                                    <span class="badge bg-danger-subtle text-danger fs-6 mb-2">FALTA</span>
                                    <p class="mb-0 small">Ponche registrado más de 10 minutos después de la apertura.</p>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl">
                                <div class="h-100 border rounded p-3">
                                    <span class="badge bg-danger text-white fs-6 mb-2">SIN AGENTE DE VENTA</span>
                                    <p class="mb-0 small">No existe ningún ponche registrado para esa terminal en la fecha consultada.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <h5 class="card-title mb-0">Resultado del monitoreo</h5>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" id="exportarMonitoreoExcelButton" class="btn btn-success btn-sm" disabled>
                                        <i class="ri-file-excel-2-line align-bottom me-1"></i>Excel
                                    </button>
                                    <button type="button" id="exportarMonitoreoPdfButton" class="btn btn-danger btn-sm" disabled>
                                        <i class="ri-file-pdf-2-line align-bottom me-1"></i>PDF
                                    </button>
                                    <button type="button" id="compartirMonitoreoPdfButton" class="btn btn-info btn-sm" disabled>
                                        <i class="ri-share-forward-line align-bottom me-1"></i>Compartir
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row g-3 align-items-end mb-4">
                                    <div class="col-md-6 col-xl">
                                        <label for="filtroEmpresaMonitoreo" class="form-label">Empresa</label>
                                        <select id="filtroEmpresaMonitoreo" class="form-select">
                                            <option value="">Todas las empresas</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 col-xl">
                                        <label for="filtroCiudadMonitoreo" class="form-label">Ciudad</label>
                                        <select id="filtroCiudadMonitoreo" class="form-select">
                                            <option value="">Todas las ciudades</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 col-xl">
                                        <label for="filtroRutaMonitoreo" class="form-label">Ruta</label>
                                        <select id="filtroRutaMonitoreo" class="form-select">
                                            <option value="">Todas las rutas</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 col-xl">
                                        <label for="filtroCoordinadorMonitoreo" class="form-label">Coordinador</label>
                                        <select id="filtroCoordinadorMonitoreo" class="form-select">
                                            <option value="">Todos los coordinadores</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-xl-auto">
                                        <div class="d-flex gap-2">
                                            <button type="button" id="aplicarFiltrosMonitoreoButton" class="btn btn-primary flex-fill">
                                                <i class="ri-filter-3-line align-bottom me-1"></i>Aplicar filtro
                                            </button>
                                            <button type="button" id="limpiarFiltrosMonitoreoButton" class="btn btn-soft-secondary flex-fill">
                                                <i class="ri-filter-off-line align-bottom me-1"></i>Limpiar filtros
                                            </button>
                                        </div>
                                    </div>
                                </div>
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

    <div class="modal fade" id="agenciasPlazaModal" tabindex="-1" aria-labelledby="agenciasPlazaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="agenciasPlazaModalLabel">Agencias en plaza</h5>
                        <div class="small text-muted">Seleccione las agencias que participarán en el monitoreo.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="agenciasPlazaArchivo" class="form-label">Importar terminales</label>
                        <input type="file" id="agenciasPlazaArchivo" class="form-control" accept=".xlsx,.xls,.csv">
                        <div class="form-text">El archivo debe contener una columna llamada Terminal.</div>
                    </div>

                    <div class="mb-3">
                        <label for="agenciasPlazaManual" class="form-label">Terminales manuales</label>
                        <textarea id="agenciasPlazaManual" class="form-control" rows="3"
                            placeholder="Escriba o pegue terminales, una por línea o separadas por coma"></textarea>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" id="reconocerAgenciasPlazaButton" class="btn btn-outline-info btn-sm">
                            <i class="ri-search-eye-line me-1"></i>Reconocer terminales
                        </button>
                        <a href="{{ route('tecnologia.monitoreo-terminales.agencias-plaza.plantilla') }}"
                            class="btn btn-outline-primary btn-sm">
                            <i class="ri-download-line me-1"></i>Descargar plantilla
                        </a>
                        <button type="button" id="limpiarAgenciasPlazaButton" class="btn btn-outline-danger btn-sm ms-auto">
                            Limpiar selección
                        </button>
                    </div>

                    <div id="agenciasPlazaLista" class="border rounded overflow-auto" style="max-height: 300px"></div>
                    <div id="agenciasPlazaEstado" class="small mt-2" aria-live="polite"></div>

                    <div class="alert alert-info mt-3 mb-0">
                        Si la selección queda vacía, el monitoreo conservará el comportamiento actual y analizará todas las agencias Lotobet.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="guardarAgenciasPlazaButton" class="btn btn-primary">
                        <i class="ri-save-line align-bottom me-1"></i>Guardar agencias
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
            const exportMonitoringExcelButton = document.getElementById('exportarMonitoreoExcelButton');
            const exportMonitoringPdfButton = document.getElementById('exportarMonitoreoPdfButton');
            const shareMonitoringPdfButton = document.getElementById('compartirMonitoreoPdfButton');
            const plazaModalElement = document.getElementById('agenciasPlazaModal');
            const plazaModal = new bootstrap.Modal(plazaModalElement);
            const plazaButton = document.getElementById('agenciasPlazaButton');
            const plazaCount = document.getElementById('agenciasPlazaCount');
            const plazaFileInput = document.getElementById('agenciasPlazaArchivo');
            const plazaManualInput = document.getElementById('agenciasPlazaManual');
            const plazaRecognizeButton = document.getElementById('reconocerAgenciasPlazaButton');
            const plazaClearButton = document.getElementById('limpiarAgenciasPlazaButton');
            const plazaSaveButton = document.getElementById('guardarAgenciasPlazaButton');
            const plazaList = document.getElementById('agenciasPlazaLista');
            const plazaStatus = document.getElementById('agenciasPlazaEstado');
            const generateForm = document.getElementById('formGenerarMonitoreo');
            const generateButton = document.getElementById('generarMonitoreoButton');
            const generateStatus = document.getElementById('generarMonitoreoEstado');
            const startDateInput = document.getElementById('fechaInicio');
            const endDateInput = document.getElementById('fechaFin');
            const monitoringTimeInput = document.getElementById('horaMonitoreo');
            const monitoringScheduleTypeInput = document.getElementById('tipoHorarioMonitoreo');
            const monitoringTimeText = document.getElementById('horaMonitoreoTexto');
            const configureTimeButton = document.getElementById('configurarHoraButton');
            const attendanceFilterText = document.getElementById('filtroEstadoAsistenciaTexto');
            const attendanceOptions = [...document.querySelectorAll('.filtro-estado-asistencia-opcion')];
            const showAllAttendanceStatesButton = document.getElementById('mostrarTodosEstadosButton');
            const companyFilter = document.getElementById('filtroEmpresaMonitoreo');
            const cityFilter = document.getElementById('filtroCiudadMonitoreo');
            const routeFilter = document.getElementById('filtroRutaMonitoreo');
            const coordinatorFilter = document.getElementById('filtroCoordinadorMonitoreo');
            const applyTableFiltersButton = document.getElementById('aplicarFiltrosMonitoreoButton');
            const clearTableFiltersButton = document.getElementById('limpiarFiltrosMonitoreoButton');
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
            const storeMonitoringTimeUrl = @json(route('tecnologia.monitoreo-terminales.horarios.store'));
            const deleteMonitoringTimeUrl = @json(route('tecnologia.monitoreo-terminales.horarios.destroy'));
            const plazaIndexUrl = @json(route('tecnologia.monitoreo-terminales.agencias-plaza.index'));
            const plazaUpdateUrl = @json(route('tecnologia.monitoreo-terminales.agencias-plaza.update'));
            const plazaRecognizeUrl = @json(route('tecnologia.monitoreo-terminales.agencias-plaza.reconocer'));
            let monitoringTimes = @json($horariosMonitoreo);
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            let activeRow = null;
            let activeDetailState = null;
            let plazaAgencies = new Map();
            const appliedTableFilters = {
                company: '',
                city: '',
                route: '',
                coordinator: ''
            };

            function escapeHtml(value) {
                const element = document.createElement('div');
                element.textContent = value ?? '';

                return element.innerHTML;
            }

            function setPlazaStatus(message, className = 'text-muted') {
                plazaStatus.textContent = message;
                plazaStatus.className = `small mt-2 ${className}`;
            }

            function updatePlazaCount(count) {
                plazaCount.textContent = Number(count || 0);
            }

            function renderPlazaAgencies() {
                const agencies = [...plazaAgencies.values()].sort((firstAgency, secondAgency) => {
                    return String(firstAgency.terminal).localeCompare(
                        String(secondAgency.terminal),
                        undefined,
                        { numeric: true }
                    );
                });

                if (agencies.length === 0) {
                    plazaList.innerHTML = `
                        <div class="text-muted text-center p-3">
                            No hay agencias seleccionadas. Se analizarán todas las agencias Lotobet.
                        </div>
                    `;

                    return;
                }

                plazaList.innerHTML = agencies.map(agency => `
                    <label class="d-flex align-items-center gap-2 px-3 py-2 border-bottom mb-0">
                        <input type="checkbox" class="form-check-input mt-0" data-plaza-agency-id="${Number(agency.id)}" checked>
                        <span>
                            <strong>Terminal ${escapeHtml(agency.terminal)}</strong>
                            <span class="d-block small text-muted">${escapeHtml(agency.agencia)}</span>
                        </span>
                    </label>
                `).join('');
            }

            async function parsePlazaResponse(response, fallbackMessage) {
                const data = await response.json();

                if (!response.ok) {
                    const validationMessage = data.errors ? Object.values(data.errors).flat()[0] : null;
                    throw new Error(validationMessage || data.message || fallbackMessage);
                }

                return data;
            }

            async function loadPlazaAgencies() {
                const response = await fetch(plazaIndexUrl, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await parsePlazaResponse(response, 'No se pudieron cargar las agencias en plaza.');
                plazaAgencies = new Map((data.data || []).map(agency => [Number(agency.id), agency]));
                updatePlazaCount(data.count);
                renderPlazaAgencies();
            }

            async function savePlazaAgencies(agencyIds) {
                const response = await fetch(plazaUpdateUrl, {
                    method: 'PUT',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ agencias: agencyIds })
                });
                const data = await parsePlazaResponse(response, 'No se pudieron guardar las agencias en plaza.');
                plazaAgencies = new Map((data.data || []).map(agency => [Number(agency.id), agency]));
                updatePlazaCount(data.count);
                renderPlazaAgencies();

                return data;
            }

            plazaButton.addEventListener('click', async function () {
                plazaFileInput.value = '';
                plazaManualInput.value = '';
                plazaList.innerHTML = '<div class="text-muted text-center p-3">Cargando agencias...</div>';
                setPlazaStatus('');
                plazaModal.show();

                try {
                    await loadPlazaAgencies();
                } catch (error) {
                    plazaList.innerHTML = '<div class="text-danger text-center p-3">No se pudo cargar la selección.</div>';
                    setPlazaStatus(error.message, 'text-danger');
                }
            });

            plazaRecognizeButton.addEventListener('click', async function () {
                const formData = new FormData();

                if (plazaFileInput.files[0]) {
                    formData.append('archivo', plazaFileInput.files[0]);
                }

                formData.append('terminales_manual', plazaManualInput.value);
                this.disabled = true;
                setPlazaStatus('Reconociendo terminales...');

                try {
                    const response = await fetch(plazaRecognizeUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: formData
                    });
                    const data = await parsePlazaResponse(response, 'No se pudieron reconocer las terminales.');

                    (data.data || []).forEach(agency => {
                        plazaAgencies.set(Number(agency.id), agency);
                    });
                    renderPlazaAgencies();

                    const missing = data.no_encontradas || [];
                    const message = missing.length > 0
                        ? `${data.encontradas} agencia(s) reconocida(s). No encontradas: ${missing.join(', ')}.`
                        : `${data.encontradas} agencia(s) reconocida(s) correctamente.`;
                    setPlazaStatus(message, missing.length > 0 ? 'text-warning' : 'text-success');
                } catch (error) {
                    setPlazaStatus(error.message, 'text-danger');
                } finally {
                    this.disabled = false;
                }
            });

            plazaSaveButton.addEventListener('click', async function () {
                const agencyIds = [...plazaList.querySelectorAll('[data-plaza-agency-id]:checked')]
                    .map(input => Number(input.dataset.plazaAgencyId));
                this.disabled = true;
                setPlazaStatus('Guardando agencias...');

                try {
                    const data = await savePlazaAgencies(agencyIds);
                    setPlazaStatus(data.message, 'text-success');
                } catch (error) {
                    setPlazaStatus(error.message, 'text-danger');
                } finally {
                    this.disabled = false;
                }
            });

            plazaClearButton.addEventListener('click', async function () {
                const confirmation = await Swal.fire({
                    title: '¿Limpiar agencias en plaza?',
                    text: 'Al quedar vacía la selección se analizarán todas las agencias Lotobet.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, limpiar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#d33'
                });

                if (!confirmation.isConfirmed) {
                    return;
                }

                this.disabled = true;
                setPlazaStatus('Limpiando selección...');

                try {
                    const data = await savePlazaAgencies([]);
                    setPlazaStatus(data.message, 'text-success');
                } catch (error) {
                    setPlazaStatus(error.message, 'text-danger');
                } finally {
                    this.disabled = false;
                }
            });

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
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return `${data} ${row.tipo_horario || ''}`.trim();
                            }

                            return escapeHtml(`${data} - Horario ${row.tipo_horario}`);
                        }
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
                                'SIN AGENTE DE VENTA': 'bg-danger text-white'
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

            function populateTableFilter(select, values, placeholder) {
                const selectedValue = select.value;
                const uniqueValues = [...new Set(values.filter(Boolean))]
                    .sort((firstValue, secondValue) => firstValue.localeCompare(
                        secondValue,
                        'es',
                        { numeric: true, sensitivity: 'base' }
                    ));

                select.replaceChildren(new Option(placeholder, ''));
                uniqueValues.forEach(value => select.add(new Option(value, value)));
                select.value = uniqueValues.includes(selectedValue) ? selectedValue : '';
            }

            function refreshTableFilterOptions() {
                const rows = table.rows().data().toArray();

                populateTableFilter(companyFilter, rows.map(row => row.empresa), 'Todas las empresas');
                const companyRows = rows.filter(row => matchesTableFilter(companyFilter.value, row.empresa));

                populateTableFilter(cityFilter, companyRows.map(row => row.ciudad), 'Todas las ciudades');
                const cityRows = companyRows.filter(row => matchesTableFilter(cityFilter.value, row.ciudad));

                populateTableFilter(routeFilter, cityRows.map(row => row.ruta), 'Todas las rutas');
                const routeRows = cityRows.filter(row => matchesTableFilter(routeFilter.value, row.ruta));

                populateTableFilter(
                    coordinatorFilter,
                    routeRows.map(row => row.coordinador),
                    'Todos los coordinadores'
                );
            }

            function matchesTableFilter(selectedValue, rowValue) {
                return selectedValue === '' || selectedValue === (rowValue || '');
            }

            $.fn.dataTable.ext.search.push(function (settings, searchData, dataIndex) {
                if (settings.nTable.id !== 'tablaMonitoreoTerminales') {
                    return true;
                }

                const row = table.row(dataIndex).data();

                return matchesTableFilter(appliedTableFilters.company, row.empresa)
                    && matchesTableFilter(appliedTableFilters.city, row.ciudad)
                    && matchesTableFilter(appliedTableFilters.route, row.ruta)
                    && matchesTableFilter(appliedTableFilters.coordinator, row.coordinador);
            });

            function applySelectedTableFilters() {
                appliedTableFilters.company = companyFilter.value;
                appliedTableFilters.city = cityFilter.value;
                appliedTableFilters.route = routeFilter.value;
                appliedTableFilters.coordinator = coordinatorFilter.value;
                table.draw();
            }

            applyTableFiltersButton.addEventListener('click', applySelectedTableFilters);

            [companyFilter, cityFilter, routeFilter].forEach(filter => {
                filter.addEventListener('change', refreshTableFilterOptions);
            });

            clearTableFiltersButton.addEventListener('click', function () {
                [companyFilter, cityFilter, routeFilter, coordinatorFilter].forEach(filter => {
                    filter.value = '';
                });
                attendanceOptions.forEach(option => {
                    option.checked = false;
                });
                refreshTableFilterOptions();
                appliedTableFilters.company = '';
                appliedTableFilters.city = '';
                appliedTableFilters.route = '';
                appliedTableFilters.coordinator = '';
                applyAttendanceFilter();
            });

            refreshTableFilterOptions();

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
                document.getElementById('resumenSinAgente').textContent = data.sin_agente ?? 0;
            }

            function filteredRows() {
                return table.rows({ search: 'applied' }).data().toArray();
            }

            function updateFilteredSummary() {
                const rows = filteredRows();

                updateSummary({
                    total: rows.length,
                    faltas: rows.filter(row => row.estado === 'FALTA').length,
                    cumplen: rows.filter(row => row.estado === 'CUMPLE').length,
                    avisos: rows.filter(row => row.estado === 'AVISO').length,
                    sin_agente: rows.filter(row => row.estado === 'SIN AGENTE DE VENTA').length
                });
            }

            function detailRows() {
                return filteredRows().filter(row => row.estado === activeDetailState);
            }

            function reportRows() {
                return filteredRows();
            }

            function exportableRows(rows) {
                return rows.map(row => ({
                    agencia: row.agencia,
                    terminal: String(row.terminal || ''),
                    coordinador: row.coordinador,
                    comentario: row.comentario || null,
                    fecha: row.fecha,
                    hora_apertura: row.hora_apertura,
                    hora_ponche: row.hora_ponche,
                    hora_monitoreo: row.hora_monitoreo,
                    tipo_horario: row.tipo_horario,
                    minutos_tardanza: row.minutos_tardanza,
                    estado: row.estado
                }));
            }

            function updateReportActionState() {
                const disabled = reportRows().length === 0;
                exportMonitoringExcelButton.disabled = disabled;
                exportMonitoringPdfButton.disabled = disabled;
                shareMonitoringPdfButton.disabled = disabled;
            }

            function renderDetailModal(state) {
                activeDetailState = state;
                const rows = detailRows();
                const withoutSalesAgent = state === 'SIN AGENTE DE VENTA';
                detailModalTitle.textContent = withoutSalesAgent ? 'Terminales sin agente de venta' : 'Terminales con aviso';
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
                        <td><span class="badge ${withoutSalesAgent ? 'bg-danger' : 'bg-warning-subtle text-warning'}">${escapeHtml(row.estado)}</span></td>
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

            async function requestExport(format, state, rows) {
                const response = await fetch(exportUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/octet-stream, application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        estado: state,
                        formato: format,
                        registros: exportableRows(rows)
                    })
                });

                if (!response.ok) {
                    const contentType = response.headers.get('content-type') || '';
                    const error = contentType.includes('application/json') ? await response.json() : null;
                    const validationMessage = error?.errors ? Object.values(error.errors).flat()[0] : null;
                    throw new Error(validationMessage || error?.message || 'No se pudo generar el archivo.');
                }

                const disposition = response.headers.get('content-disposition') || '';
                const fileNameMatch = disposition.match(/filename="?([^";]+)"?/i);

                return {
                    blob: await response.blob(),
                    fileName: fileNameMatch?.[1] || `monitoreo.${format === 'excel' ? 'xlsx' : 'pdf'}`
                };
            }

            function downloadBlob(blob, fileName) {
                const objectUrl = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = objectUrl;
                link.download = fileName;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(objectUrl);
            }

            async function downloadDetail(format, button) {
                const rows = detailRows();

                if (rows.length === 0) {
                    return;
                }

                button.disabled = true;

                try {
                    const file = await requestExport(format, activeDetailState, rows);
                    downloadBlob(file.blob, file.fileName);
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

            async function downloadReport(format, button) {
                const rows = reportRows();

                if (rows.length === 0) {
                    return;
                }

                button.disabled = true;

                try {
                    const file = await requestExport(format, 'TODOS', rows);
                    downloadBlob(file.blob, file.fileName);
                } catch (error) {
                    await Swal.fire('Error', error.message || 'No se pudo generar el informe.', 'error');
                } finally {
                    updateReportActionState();
                }
            }

            exportMonitoringExcelButton.addEventListener('click', function () {
                downloadReport('excel', this);
            });

            exportMonitoringPdfButton.addEventListener('click', function () {
                downloadReport('pdf', this);
            });

            async function validateShareFilters() {
                const requiredFilters = [
                    { label: 'Empresa', selected: companyFilter.value, applied: appliedTableFilters.company },
                    { label: 'Ciudad', selected: cityFilter.value, applied: appliedTableFilters.city },
                    { label: 'Ruta', selected: routeFilter.value, applied: appliedTableFilters.route },
                    { label: 'Coordinador', selected: coordinatorFilter.value, applied: appliedTableFilters.coordinator }
                ];
                const missingFilters = requiredFilters
                    .filter(filter => filter.selected === '')
                    .map(filter => filter.label);

                if (missingFilters.length > 0) {
                    await Swal.fire({
                        title: 'Filtros requeridos',
                        html: `Antes de compartir debe seleccionar: <strong>${missingFilters.join(', ')}</strong>.`,
                        icon: 'warning',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#405189'
                    });

                    return false;
                }

                const hasPendingChanges = requiredFilters.some(filter => filter.selected !== filter.applied);

                if (hasPendingChanges) {
                    await Swal.fire({
                        title: 'Debe aplicar los filtros',
                        text: 'Presione “Aplicar filtro” antes de compartir el informe.',
                        icon: 'warning',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#405189'
                    });

                    return false;
                }

                return true;
            }

            shareMonitoringPdfButton.addEventListener('click', async function () {
                if (!await validateShareFilters()) {
                    return;
                }

                const rows = reportRows();

                if (rows.length === 0) {
                    return;
                }

                this.disabled = true;

                try {
                    const generated = await requestExport('pdf', 'TODOS', rows);
                    const file = new File([generated.blob], generated.fileName, { type: 'application/pdf' });

                    if (navigator.share && (!navigator.canShare || navigator.canShare({ files: [file] }))) {
                        try {
                            await navigator.share({
                                title: 'Informe de monitoreo de terminales',
                                text: 'Informe de monitoreo de terminales.',
                                files: [file]
                            });
                        } catch (error) {
                            if (error.name === 'AbortError') {
                                return;
                            }

                            downloadBlob(generated.blob, generated.fileName);
                            await Swal.fire(
                                'PDF descargado',
                                'El sistema no pudo abrir el menú para compartir. Puedes enviar el archivo descargado desde la aplicación que prefieras.',
                                'info'
                            );
                        }
                    } else {
                        downloadBlob(generated.blob, generated.fileName);
                        await Swal.fire(
                            'PDF descargado',
                            'Este navegador no permite compartir archivos directamente. Puedes enviarlo desde la aplicación que prefieras.',
                            'info'
                        );
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        await Swal.fire('Error', error.message || 'No se pudo compartir el informe.', 'error');
                    }
                } finally {
                    updateReportActionState();
                }
            });

            function applyAttendanceFilter() {
                const selectedOptions = attendanceOptions.filter(option => option.checked);
                const selectedStates = selectedOptions.map(option => {
                    return $.fn.dataTable.util.escapeRegex(option.value);
                });
                const labels = selectedOptions.map(option => option.dataset.label);
                const searchPattern = selectedStates.length > 0 ? `^(${selectedStates.join('|')})$` : '';

                attendanceFilterText.textContent = labels.length === 0
                    ? 'Todos'
                    : labels.length === 1 ? labels[0] : `${labels.length} estados seleccionados`;
                table.column(5).search(searchPattern, true, false).draw();
            }

            attendanceOptions.forEach(option => {
                option.addEventListener('change', applyAttendanceFilter);
            });

            showAllAttendanceStatesButton.addEventListener('click', function () {
                attendanceOptions.forEach(option => {
                    option.checked = false;
                });
                applyAttendanceFilter();
            });

            table.on('draw', function () {
                updateReportActionState();
                updateFilteredSummary();
            });
            updateReportActionState();
            updateFilteredSummary();

            function selectedMonitoringTimeKey() {
                if (!monitoringTimeInput.value || !monitoringScheduleTypeInput.value) {
                    return '';
                }

                return `${monitoringTimeInput.value}|${monitoringScheduleTypeInput.value}`;
            }

            async function updateMonitoringTime(method, value, scheduleType) {
                const response = await fetch(
                    method === 'POST' ? storeMonitoringTimeUrl : deleteMonitoringTimeUrl,
                    {
                        method,
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            hora: value,
                            tipo_horario: scheduleType
                        })
                    }
                );
                const data = await response.json();

                if (!response.ok) {
                    const validationMessage = data.errors ? Object.values(data.errors).flat()[0] : null;
                    throw new Error(validationMessage || data.message || 'No se pudo actualizar el horario.');
                }

                monitoringTimes = data.data;

                return data.message;
            }

            async function configureMonitoringTime() {
                const result = await Swal.fire({
                    title: 'Configurar hora evaluada',
                    html: `
                        <p class="text-muted mb-3">Seleccione, agregue o elimine los horarios disponibles para el monitoreo.</p>
                        <select id="swalHorarioSeleccionado" class="form-select mb-3" aria-label="Horario evaluado"></select>
                        <div class="input-group mb-3">
                            <input type="time" id="swalNuevoHorario" class="form-control" aria-label="Nuevo horario">
                            <select id="swalNuevoTipoHorario" class="form-select" aria-label="Tipo de horario">
                                <option value="AM">Horario AM</option>
                                <option value="PM">Horario PM</option>
                            </select>
                            <button type="button" id="swalAgregarHorario" class="btn btn-primary">
                                <i class="ri-add-line align-bottom me-1"></i>Agregar
                            </button>
                        </div>
                        <div id="swalHorariosLista" class="border rounded text-start overflow-auto" style="max-height: 220px"></div>
                        <div id="swalHorarioEstado" class="small text-start mt-2" aria-live="polite"></div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Aplicar hora',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#405189',
                    didOpen: () => {
                        const selectedInput = document.getElementById('swalHorarioSeleccionado');
                        const newTimeInput = document.getElementById('swalNuevoHorario');
                        const newScheduleTypeInput = document.getElementById('swalNuevoTipoHorario');
                        const addButton = document.getElementById('swalAgregarHorario');
                        const list = document.getElementById('swalHorariosLista');
                        const status = document.getElementById('swalHorarioEstado');

                        const renderOptions = preferredValue => {
                            const selectedValue = preferredValue || selectedInput.value || selectedMonitoringTimeKey();
                            const entries = Object.entries(monitoringTimes);
                            selectedInput.innerHTML = '<option value="">Seleccione un horario</option>';
                            list.innerHTML = '';

                            entries.forEach(([value, label]) => {
                                const option = document.createElement('option');
                                option.value = value;
                                option.textContent = label;
                                option.selected = value === selectedValue;
                                selectedInput.appendChild(option);

                                const row = document.createElement('div');
                                row.className = 'd-flex align-items-center justify-content-between gap-2 px-3 py-2 border-bottom';
                                row.innerHTML = `
                                    <span>${escapeHtml(label)}</span>
                                    <button type="button" class="btn btn-sm btn-soft-danger" data-delete-key="${escapeHtml(value)}" title="Eliminar horario">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                `;
                                list.appendChild(row);
                            });

                            if (entries.length === 0) {
                                list.innerHTML = '<div class="text-muted text-center p-3">No hay horarios configurados.</div>';
                            }

                            list.querySelectorAll('[data-delete-key]').forEach(button => {
                                button.addEventListener('click', async function () {
                                    const key = this.dataset.deleteKey;
                                    const [value, scheduleType] = key.split('|');
                                    this.disabled = true;
                                    status.textContent = 'Eliminando horario...';
                                    status.className = 'small text-start mt-2 text-muted';

                                    try {
                                        const message = await updateMonitoringTime('DELETE', value, scheduleType);

                                        if (selectedMonitoringTimeKey() === key) {
                                            monitoringTimeInput.value = '';
                                            monitoringScheduleTypeInput.value = '';
                                            monitoringTimeText.textContent = 'Configurar hora';
                                            configureTimeButton.classList.remove('btn-soft-success');
                                            configureTimeButton.classList.add('btn-soft-info');
                                        }

                                        renderOptions();
                                        status.textContent = message;
                                        status.className = 'small text-start mt-2 text-success';
                                    } catch (error) {
                                        this.disabled = false;
                                        status.textContent = error.message;
                                        status.className = 'small text-start mt-2 text-danger';
                                    }
                                });
                            });
                        };

                        addButton.addEventListener('click', async function () {
                            if (!newTimeInput.value) {
                                status.textContent = 'Seleccione la hora que desea agregar.';
                                status.className = 'small text-start mt-2 text-danger';

                                return;
                            }

                            this.disabled = true;
                            status.textContent = 'Agregando horario...';
                            status.className = 'small text-start mt-2 text-muted';

                            try {
                                const value = newTimeInput.value;
                                const scheduleType = newScheduleTypeInput.value;
                                const key = `${value}|${scheduleType}`;
                                const message = await updateMonitoringTime('POST', value, scheduleType);
                                newTimeInput.value = '';
                                renderOptions(key);
                                status.textContent = message;
                                status.className = 'small text-start mt-2 text-success';
                            } catch (error) {
                                status.textContent = error.message;
                                status.className = 'small text-start mt-2 text-danger';
                            } finally {
                                this.disabled = false;
                            }
                        });

                        renderOptions(selectedMonitoringTimeKey());
                    },
                    preConfirm: () => {
                        const value = document.getElementById('swalHorarioSeleccionado').value;

                        if (!value) {
                            Swal.showValidationMessage('Debe seleccionar un horario.');

                            return false;
                        }

                        return value;
                    }
                });

                if (!result.isConfirmed) {
                    return false;
                }

                const [monitoringTime, scheduleType] = result.value.split('|');
                monitoringTimeInput.value = monitoringTime;
                monitoringScheduleTypeInput.value = scheduleType;
                monitoringTimeText.textContent = monitoringTimes[result.value];
                configureTimeButton.classList.remove('btn-soft-info');
                configureTimeButton.classList.add('btn-soft-success');

                return true;
            }

            configureTimeButton.addEventListener('click', configureMonitoringTime);

            function showGeneratingMonitoringAlert() {
                Swal.fire({
                    title: 'Generando información',
                    html: `
                        <p class="mb-1">Estamos consultando las asistencias y preparando el monitoreo.</p>
                        <small class="text-muted">Este proceso puede tardar unos segundos.</small>
                    `,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });
            }

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

            async function selectAgencyScope() {
                const configuredAgencies = Number(plazaCount.textContent || 0);
                const result = await Swal.fire({
                    title: '¿Qué agencias deseas evaluar?',
                    html: `
                        <div class="d-grid gap-2 text-start">
                            <label class="border rounded p-3 d-flex align-items-center gap-2 mb-0">
                                <input type="radio" name="swalAlcanceAgencias" value="todas" class="form-check-input mt-0" checked>
                                <span>
                                    <strong>Todas las agencias</strong>
                                    <small class="d-block text-muted">Genera el monitoreo completo.</small>
                                </span>
                            </label>
                            <label class="border rounded p-3 d-flex align-items-center gap-2 mb-0 ${configuredAgencies === 0 ? 'opacity-50' : ''}">
                                <input type="radio" name="swalAlcanceAgencias" value="plaza" class="form-check-input mt-0" ${configuredAgencies === 0 ? 'disabled' : ''}>
                                <span>
                                    <strong>Solo agencias en plaza (${configuredAgencies})</strong>
                                    <small class="d-block text-muted">
                                        ${configuredAgencies === 0 ? 'Primero agregue agencias en plaza.' : 'Utiliza únicamente la selección configurada.'}
                                    </small>
                                </span>
                            </label>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Continuar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#405189',
                    preConfirm: () => {
                        return document.querySelector('input[name="swalAlcanceAgencias"]:checked')?.value || 'todas';
                    }
                });

                return result.isConfirmed ? result.value : null;
            }

            async function generateMonitoring(tokenRetryAttempted = false, agencyScope = 'todas') {
                const query = new URLSearchParams({
                    fecha_inicio: startDateInput.value,
                    fecha_fin: endDateInput.value,
                    hora_monitoreo: monitoringTimeInput.value,
                    tipo_horario: monitoringScheduleTypeInput.value,
                    alcance_agencias: agencyScope
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
                    showGeneratingMonitoringAlert();

                    return generateMonitoring(true, agencyScope);
                }

                if (!response.ok) {
                    const validationMessage = data.errors ? Object.values(data.errors).flat()[0] : null;
                    throw new Error(validationMessage || data.message || 'No se pudo generar el monitoreo.');
                }

                table.clear().rows.add(data.data || []).draw();
                refreshTableFilterOptions();
                applySelectedTableFilters();
                setGenerateStatus(
                    `Monitoreo generado: ${data.total ?? 0} evaluaciones. Alcance: ${data.alcance_label}.`,
                    'text-success'
                );
            }

            generateForm.addEventListener('submit', async function (event) {
                event.preventDefault();

                if ((!monitoringTimeInput.value || !monitoringScheduleTypeInput.value) && !await configureMonitoringTime()) {
                    setGenerateStatus('Debe configurar la hora que desea evaluar.', 'text-warning');

                    return;
                }

                const agencyScope = await selectAgencyScope();

                if (agencyScope === null) {
                    setGenerateStatus('Generación cancelada.', 'text-warning');

                    return;
                }

                generateButton.disabled = true;
                setGenerateStatus('Consultando asistencias...', 'text-muted');
                showGeneratingMonitoringAlert();

                try {
                    await generateMonitoring(false, agencyScope);
                    Swal.close();
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
