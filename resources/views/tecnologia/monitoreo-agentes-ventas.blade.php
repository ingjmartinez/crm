@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Monitoreo de agentes de ventas</h4>
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('tecnologia.index') }}">Tecnología</a></li>
                                <li class="breadcrumb-item active">Monitoreo de agentes de ventas</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <h5 class="card-title mb-1">Ponches de entrada y salida</h5>
                            <p class="text-muted mb-0 small">Primera entrada y última salida por agente, terminal, sistema y día.</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" id="agenciasPlazaButton" class="btn btn-soft-primary fw-semibold">
                                <i class="ri-map-pin-user-line me-1"></i>Agencias en plaza
                                <span id="agenciasPlazaCount" class="badge bg-primary ms-1">{{ $agenciasPlazaCount }}</span>
                            </button>
                            <button type="button" class="btn btn-warning fw-semibold" id="generarTokenLotobetButton">
                                <i class="ri-key-2-line me-1"></i>Token Lotobet
                            </button>
                            <button type="button" class="btn btn-info text-white fw-semibold" id="generarTokenLotonetButton">
                                <i class="ri-shield-keyhole-line me-1"></i>Sesión Lotonet
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="formMonitoreoAgentes" class="row g-3 align-items-end">
                            <div class="col-sm-6 col-xl-3">
                                <label for="fechaInicio" class="form-label">Desde</label>
                                <input type="date" class="form-control" id="fechaInicio" value="{{ $fechaActual }}" max="{{ $fechaActual }}" required>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <label for="fechaFin" class="form-label">Hasta</label>
                                <input type="date" class="form-control" id="fechaFin" value="{{ $fechaActual }}" max="{{ $fechaActual }}" required>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <label for="sistemaConsulta" class="form-label">Sistema</label>
                                <select class="form-select" id="sistemaConsulta">
                                    <option value="todos">Todos</option>
                                    <option value="lotobet">Lotobet</option>
                                    <option value="lotonet">Lotonet</option>
                                </select>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <button type="submit" class="btn btn-primary w-100" id="generarReporteButton">
                                    <i class="ri-search-line me-1"></i>Generar reporte
                                </button>
                            </div>
                        </form>
                        <div id="estadoConsulta" class="small mt-3" aria-live="polite"></div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6 col-xl-3"><div class="card mb-0"><div class="card-body py-3"><span class="text-muted">Registros</span><h4 class="mb-0" id="resumenTotal">0</h4></div></div></div>
                    <div class="col-6 col-xl-3"><div class="card mb-0"><div class="card-body py-3"><span class="text-muted">Completos</span><h4 class="mb-0 text-success" id="resumenCompletos">0</h4></div></div></div>
                    <div class="col-6 col-xl-3"><div class="card mb-0"><div class="card-body py-3"><span class="text-muted">Sin entrada</span><h4 class="mb-0 text-warning" id="resumenSinEntrada">0</h4></div></div></div>
                    <div class="col-6 col-xl-3"><div class="card mb-0"><div class="card-body py-3"><span class="text-muted">Sin salida / validar</span><h4 class="mb-0 text-danger" id="resumenSinSalida">0</h4></div></div></div>
                </div>

                <div class="card">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <h5 class="card-title mb-0">Detalle por agente</h5>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="filtrarAgenciasPlazaButton"
                                aria-pressed="false" @disabled($agenciasPlazaCount === 0)>
                                <i class="ri-map-pin-line me-1"></i>Agencias en plaza
                                (<span id="filtroAgenciasPlazaCount">{{ $agenciasPlazaCount }}</span>)
                            </button>
                            <button type="button" class="btn btn-success btn-sm" id="exportarExcelButton" disabled><i class="ri-file-excel-2-line me-1"></i>Excel</button>
                            <button type="button" class="btn btn-danger btn-sm" id="exportarPdfButton" disabled><i class="ri-file-pdf-2-line me-1"></i>PDF</button>
                        </div>
                    </div>
                    <div class="card-body border-bottom">
                        <div class="row g-2">
                            <div class="col-md-6 col-xl-3"><input type="search" class="form-control" id="filtroTexto" placeholder="Cédula, agente o terminal"></div>
                            <div class="col-md-6 col-xl-2"><select class="form-select" id="filtroSistema"><option value="">Todos los sistemas</option></select></div>
                            <div class="col-md-6 col-xl-2"><select class="form-select" id="filtroEmpresa"><option value="">Todas las empresas</option></select></div>
                            <div class="col-md-6 col-xl-3"><select class="form-select" id="filtroCoordinador"><option value="">Todos los coordinadores</option></select></div>
                            <div class="col-md-6 col-xl-2">
                                <select class="form-select" id="filtroEstado">
                                    <option value="">Todos los estados</option>
                                    <option value="COMPLETO">Completo</option>
                                    <option value="SIN ENTRADA">Sin entrada</option>
                                    <option value="SIN SALIDA">Sin salida</option>
                                    <option value="SIN ENTRADA Y SALIDA">Sin entrada y salida</option>
                                    <option value="REINICIO VALIDADO">Reinicio validado</option>
                                    <option value="SALIDA POR INACTIVIDAD">Salida por inactividad</option>
                                    <option value="PENDIENTE DE VALIDACIÓN">Pendiente de validación</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th><th>Sistema</th><th>Cédula</th><th>Agente</th><th>Entrada</th><th>Salida</th><th>Marca a validar</th><th>Última venta</th><th>Terminal</th><th>Agencia</th><th>Empresa</th><th>Coordinador</th><th>Estado</th><th>Observación</th>
                                </tr>
                            </thead>
                            <tbody id="tablaAgentesBody">
                                <tr><td colspan="14" class="text-center text-muted py-4">Genera el reporte para consultar los ponches.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <span class="small text-muted" id="resultadoVisible">0 registros visibles</span>
                        <nav id="paginacionAgentes" class="d-none" aria-label="Paginación del reporte de agentes">
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item" id="paginaAnteriorItem">
                                    <button type="button" class="page-link" id="paginaAnteriorButton">Anterior</button>
                                </li>
                                <li class="page-item disabled">
                                    <span class="page-link text-muted" id="paginaActual">Página 1 de 1</span>
                                </li>
                                <li class="page-item" id="paginaSiguienteItem">
                                    <button type="button" class="page-link" id="paginaSiguienteButton">Siguiente</button>
                                </li>
                            </ul>
                        </nav>
                    </div>
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
                        <div class="small text-muted">Esta es la misma selección utilizada en el monitoreo de terminales.</div>
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
                        Las agencias guardadas podrán mostrarse de forma exclusiva con el filtro del detalle. Si la selección queda vacía, el filtro se desactivará.
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
            const urls = {
                generar: @json(route('tecnologia.monitoreo-agentes-ventas.generar')),
                exportar: @json(route('tecnologia.monitoreo-agentes-ventas.exportar')),
                tokenLotobet: @json(route('token.generate')),
                tokenLotonet: @json(url('/iniciar-session')),
                plazaIndex: @json(route('tecnologia.monitoreo-terminales.agencias-plaza.index')),
                plazaUpdate: @json(route('tecnologia.monitoreo-terminales.agencias-plaza.update')),
                plazaRecognize: @json(route('tecnologia.monitoreo-terminales.agencias-plaza.reconocer')),
            };
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const form = document.getElementById('formMonitoreoAgentes');
            const status = document.getElementById('estadoConsulta');
            const body = document.getElementById('tablaAgentesBody');
            const plazaModal = new bootstrap.Modal(document.getElementById('agenciasPlazaModal'));
            const plazaButton = document.getElementById('agenciasPlazaButton');
            const plazaCount = document.getElementById('agenciasPlazaCount');
            const plazaFilterButton = document.getElementById('filtrarAgenciasPlazaButton');
            const plazaFilterCount = document.getElementById('filtroAgenciasPlazaCount');
            const plazaFileInput = document.getElementById('agenciasPlazaArchivo');
            const plazaManualInput = document.getElementById('agenciasPlazaManual');
            const plazaRecognizeButton = document.getElementById('reconocerAgenciasPlazaButton');
            const plazaClearButton = document.getElementById('limpiarAgenciasPlazaButton');
            const plazaSaveButton = document.getElementById('guardarAgenciasPlazaButton');
            const plazaList = document.getElementById('agenciasPlazaLista');
            const plazaStatus = document.getElementById('agenciasPlazaEstado');
            const pagination = document.getElementById('paginacionAgentes');
            const previousPageItem = document.getElementById('paginaAnteriorItem');
            const nextPageItem = document.getElementById('paginaSiguienteItem');
            const pageLabel = document.getElementById('paginaActual');
            const filters = {
                text: document.getElementById('filtroTexto'),
                system: document.getElementById('filtroSistema'),
                company: document.getElementById('filtroEmpresa'),
                coordinator: document.getElementById('filtroCoordinador'),
                state: document.getElementById('filtroEstado'),
            };
            const pageSize = 50;
            let rows = [];
            let currentPage = 1;
            let onlyPlazaAgencies = false;
            let plazaAgencies = new Map();
            let plazaConfiguredCount = Number(@json($agenciasPlazaCount));

            function escapeHtml(value) {
                const element = document.createElement('div');
                element.textContent = value ?? '';
                return element.innerHTML;
            }

            async function readJson(response, fallback) {
                const responseText = await response.text();

                try {
                    return responseText === '' ? {} : JSON.parse(responseText);
                } catch (error) {
                    return { message: `${fallback} El servidor respondió HTTP ${response.status} sin un JSON válido.` };
                }
            }

            function setPlazaStatus(message, className = 'text-muted') {
                plazaStatus.textContent = message;
                plazaStatus.className = `small mt-2 ${className}`;
            }

            function updatePlazaFilterButton(count = plazaConfiguredCount) {
                plazaConfiguredCount = Number(count || 0);
                count = plazaConfiguredCount;
                plazaCount.textContent = count.toLocaleString('es-DO');
                plazaFilterCount.textContent = count.toLocaleString('es-DO');
                plazaFilterButton.disabled = count === 0;

                if (count === 0) {
                    onlyPlazaAgencies = false;
                }

                plazaFilterButton.setAttribute('aria-pressed', onlyPlazaAgencies ? 'true' : 'false');
                plazaFilterButton.classList.toggle('btn-primary', onlyPlazaAgencies);
                plazaFilterButton.classList.toggle('btn-outline-primary', !onlyPlazaAgencies);
            }

            function synchronizeRowsWithPlazaAgencies() {
                rows = rows.map(row => ({
                    ...row,
                    es_agencia_plaza: row.agencia_id !== null
                        && plazaAgencies.has(Number(row.agencia_id)),
                }));
                updatePlazaFilterButton(plazaAgencies.size);
                resetPageAndRender();
            }

            function renderPlazaAgencies() {
                const agencies = [...plazaAgencies.values()].sort((firstAgency, secondAgency) => {
                    return String(firstAgency.terminal).localeCompare(String(secondAgency.terminal), undefined, { numeric: true });
                });

                plazaList.innerHTML = agencies.length ? agencies.map(agency => `
                    <label class="d-flex align-items-center gap-2 px-3 py-2 border-bottom mb-0">
                        <input type="checkbox" class="form-check-input mt-0" data-plaza-agency-id="${Number(agency.id)}" checked>
                        <span>
                            <strong>Terminal ${escapeHtml(agency.terminal)}</strong>
                            <span class="d-block small text-muted">${escapeHtml(agency.agencia)}</span>
                        </span>
                    </label>
                `).join('') : '<div class="text-muted text-center p-3">No hay agencias seleccionadas.</div>';
            }

            async function parsePlazaResponse(response, fallbackMessage) {
                const data = await readJson(response, fallbackMessage);

                if (!response.ok) {
                    const validationMessage = data.errors ? Object.values(data.errors).flat()[0] : null;
                    throw new Error(validationMessage || data.message || fallbackMessage);
                }

                return data;
            }

            async function loadPlazaAgencies() {
                const response = await fetch(urls.plazaIndex, { headers: { 'Accept': 'application/json' } });
                const data = await parsePlazaResponse(response, 'No se pudieron cargar las agencias en plaza.');
                plazaAgencies = new Map((data.data || []).map(agency => [Number(agency.id), agency]));
                renderPlazaAgencies();
                synchronizeRowsWithPlazaAgencies();
            }

            async function savePlazaAgencies(agencyIds) {
                const response = await fetch(urls.plazaUpdate, {
                    method: 'PUT',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ agencias: agencyIds }),
                });
                const data = await parsePlazaResponse(response, 'No se pudieron guardar las agencias en plaza.');
                plazaAgencies = new Map((data.data || []).map(agency => [Number(agency.id), agency]));
                renderPlazaAgencies();
                synchronizeRowsWithPlazaAgencies();

                return data;
            }

            function badgeStatus(value) {
                const classes = {
                    'COMPLETO': 'bg-success-subtle text-success',
                    'SIN ENTRADA': 'bg-warning-subtle text-warning',
                    'SIN SALIDA': 'bg-danger-subtle text-danger',
                    'SIN ENTRADA Y SALIDA': 'bg-dark-subtle text-dark',
                    'REINICIO VALIDADO': 'bg-info-subtle text-info',
                    'SALIDA POR INACTIVIDAD': 'bg-warning-subtle text-warning',
                    'PENDIENTE DE VALIDACIÓN': 'bg-secondary-subtle text-secondary',
                };
                return `<span class="badge ${classes[value] || 'bg-secondary-subtle text-secondary'}">${escapeHtml(value)}</span>`;
            }

            function visibleRows() {
                const text = filters.text.value.trim().toLowerCase();
                return rows.filter(row => {
                    const searchable = [row.cedula, row.agente, row.terminal, row.agencia].join(' ').toLowerCase();
                    return (!onlyPlazaAgencies || row.es_agencia_plaza === true)
                        && (!text || searchable.includes(text))
                        && (!filters.system.value || row.sistema === filters.system.value)
                        && (!filters.company.value || row.empresa === filters.company.value)
                        && (!filters.coordinator.value || row.coordinador === filters.coordinator.value)
                        && (!filters.state.value || row.estado === filters.state.value);
                });
            }

            function render() {
                const filtered = visibleRows();
                const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
                currentPage = Math.min(currentPage, totalPages);
                const firstRow = (currentPage - 1) * pageSize;
                const paginatedRows = filtered.slice(firstRow, firstRow + pageSize);

                body.innerHTML = paginatedRows.length ? paginatedRows.map(row => `
                    <tr>
                        <td>${escapeHtml(row.fecha)}</td><td><span class="badge bg-primary-subtle text-primary">${escapeHtml(row.sistema)}</span></td>
                        <td>${escapeHtml(row.cedula || 'Sin cédula')}</td><td class="fw-semibold">${escapeHtml(row.agente)}</td>
                        <td>${escapeHtml(row.entrada || 'Sin entrada')}</td><td>${escapeHtml(row.salida || 'Sin salida')}</td>
                        <td>${escapeHtml(row.marca_validar || '-')}</td><td>${escapeHtml(row.ultima_venta || '-')}</td>
                        <td>${escapeHtml(row.terminal)}</td><td>${escapeHtml(row.agencia)}</td><td>${escapeHtml(row.empresa)}</td>
                        <td>${escapeHtml(row.coordinador)}</td><td>${badgeStatus(row.estado)}</td><td>${escapeHtml(row.observacion || '-')}</td>
                    </tr>`).join('') : '<tr><td colspan="14" class="text-center text-muted py-4">No hay registros con los filtros seleccionados.</td></tr>';
                document.getElementById('resultadoVisible').textContent = filtered.length
                    ? `Mostrando ${(firstRow + 1).toLocaleString('es-DO')}-${Math.min(firstRow + pageSize, filtered.length).toLocaleString('es-DO')} de ${filtered.length.toLocaleString('es-DO')} registros`
                    : '0 registros visibles';
                pageLabel.textContent = `Página ${currentPage.toLocaleString('es-DO')} de ${totalPages.toLocaleString('es-DO')}`;
                previousPageItem.classList.toggle('disabled', currentPage === 1);
                nextPageItem.classList.toggle('disabled', currentPage === totalPages);
                pagination.classList.toggle('d-none', filtered.length <= pageSize);
                document.getElementById('exportarExcelButton').disabled = filtered.length === 0;
                document.getElementById('exportarPdfButton').disabled = filtered.length === 0;
            }

            function resetPageAndRender() {
                currentPage = 1;
                render();
            }

            function fillSelect(select, values, firstLabel) {
                select.innerHTML = `<option value="">${firstLabel}</option>` + [...new Set(values.filter(Boolean))]
                    .sort((a, b) => a.localeCompare(b, 'es', { sensitivity: 'base' }))
                    .map(value => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`).join('');
            }

            function updateFilters() {
                fillSelect(filters.system, rows.map(row => row.sistema), 'Todos los sistemas');
                fillSelect(filters.company, rows.map(row => row.empresa), 'Todas las empresas');
                fillSelect(filters.coordinator, rows.map(row => row.coordinador), 'Todos los coordinadores');
            }

            function showGeneratingReportAlert() {
                Swal.fire({
                    title: 'Generando información',
                    html: `
                        <p class="mb-1">Estamos preparando el acceso y consultando las asistencias.</p>
                        <small class="text-muted">Este proceso puede tardar unos segundos.</small>
                    `,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading(),
                });
            }

            async function generateSession(url, label) {
                status.textContent = `${label}...`;
                status.className = 'small mt-3 text-info';
                try {
                    const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const data = await readJson(response, `No se pudo completar: ${label}.`);
                    if (!response.ok) {
                        throw new Error(data.message || `No se pudo completar: ${label}.`);
                    }
                    status.textContent = data.success || `${label} completado.`;
                    status.className = 'small mt-3 text-success';
                } catch (error) {
                    status.textContent = error.message;
                    status.className = 'small mt-3 text-danger';
                }
            }

            async function generateLotobetToken() {
                Swal.fire({
                    title: 'Generando token Lotobet...',
                    text: 'Por favor espera.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => Swal.showLoading(),
                });
                const response = await fetch(urls.tokenLotobet, { headers: { 'Accept': 'application/json' } });
                const data = await readJson(response, 'No se pudo interpretar la respuesta al generar el token.');

                if (!response.ok) {
                    throw new Error(data.message || 'No se pudo generar el token de Lotobet.');
                }

                Swal.close();

                return data;
            }

            async function exportRows(format) {
                const selectedRows = visibleRows();
                const response = await fetch(urls.exportar, {
                    method: 'POST',
                    headers: { 'Accept': 'application/octet-stream', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ formato: format, registros: selectedRows }),
                });
                if (!response.ok) {
                    const data = await response.json();
                    throw new Error(data.message || 'No se pudo generar la descarga.');
                }
                const disposition = response.headers.get('Content-Disposition') || '';
                const match = disposition.match(/filename="?([^";]+)"?/i);
                const extension = format === 'excel' ? 'xlsx' : 'pdf';
                const filename = match?.[1] || `monitoreo_agentes_ventas.${extension}`;
                const link = document.createElement('a');
                link.href = URL.createObjectURL(await response.blob());
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(link.href);
            }

            async function generateReport(tokenRetryAttempted = false) {
                status.textContent = 'Preparando acceso y consultando asistencias...';
                status.className = 'small mt-3 text-info';
                const query = new URLSearchParams({
                    fecha_inicio: document.getElementById('fechaInicio').value,
                    fecha_fin: document.getElementById('fechaFin').value,
                    sistema: document.getElementById('sistemaConsulta').value,
                });
                const response = await fetch(`${urls.generar}?${query}`, { headers: { 'Accept': 'application/json' } });
                const data = await readJson(response, 'No se pudo interpretar la respuesta del reporte.');

                if (response.status === 409 && data.code === 'LOTOBET_TOKEN_REQUIRED' && !tokenRetryAttempted) {
                    const confirmation = await Swal.fire({
                        title: 'Token de Lotobet requerido',
                        text: `${data.message} ¿Deseas generarlo ahora?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Generar token',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#405189',
                    });

                    if (!confirmation.isConfirmed) {
                        status.textContent = 'Generación cancelada: se requiere un token válido de Lotobet.';
                        status.className = 'small mt-3 text-warning';

                        return;
                    }

                    await generateLotobetToken();
                    status.textContent = 'Token generado. Consultando asistencias...';
                    status.className = 'small mt-3 text-info';
                    showGeneratingReportAlert();

                    return generateReport(true);
                }

                if (!response.ok) {
                    const validation = data.errors ? Object.values(data.errors).flat()[0] : null;
                    throw new Error(validation || data.message || 'No se pudo generar el reporte.');
                }

                rows = data.data || [];
                currentPage = 1;
                updatePlazaFilterButton(data.agencias_plaza_count);
                document.getElementById('resumenTotal').textContent = Number(data.total || 0).toLocaleString('es-DO');
                document.getElementById('resumenCompletos').textContent = Number(data.completos || 0).toLocaleString('es-DO');
                document.getElementById('resumenSinEntrada').textContent = Number(data.sin_entrada || 0).toLocaleString('es-DO');
                document.getElementById('resumenSinSalida').textContent = Number(data.sin_salida || 0).toLocaleString('es-DO');
                updateFilters();
                render();
                status.textContent = `Reporte generado: ${rows.length.toLocaleString('es-DO')} registros.`;
                status.className = 'small mt-3 text-success';
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
                    const response = await fetch(urls.plazaRecognize, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: formData,
                    });
                    const data = await parsePlazaResponse(response, 'No se pudieron reconocer las terminales.');

                    (data.data || []).forEach(agency => plazaAgencies.set(Number(agency.id), agency));
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
                    text: 'El filtro quedará desactivado hasta que se seleccionen nuevas agencias.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, limpiar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#d33',
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

            plazaFilterButton.addEventListener('click', function () {
                onlyPlazaAgencies = !onlyPlazaAgencies;
                updatePlazaFilterButton();
                resetPageAndRender();
            });

            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                const button = document.getElementById('generarReporteButton');
                button.disabled = true;
                showGeneratingReportAlert();

                try {
                    await generateReport();
                    Swal.close();
                } catch (error) {
                    Swal.close();
                    status.textContent = error.message;
                    status.className = 'small mt-3 text-danger';
                    await Swal.fire('Error', error.message || 'No se pudo generar el reporte.', 'error');
                } finally {
                    button.disabled = false;
                }
            });

            Object.values(filters).forEach(filter => filter.addEventListener(filter === filters.text ? 'input' : 'change', resetPageAndRender));
            document.getElementById('paginaAnteriorButton').addEventListener('click', function () {
                if (currentPage > 1) {
                    currentPage--;
                    render();
                }
            });
            document.getElementById('paginaSiguienteButton').addEventListener('click', function () {
                if (currentPage < Math.ceil(visibleRows().length / pageSize)) {
                    currentPage++;
                    render();
                }
            });
            document.getElementById('generarTokenLotobetButton').addEventListener('click', () => generateSession(urls.tokenLotobet, 'Generando token Lotobet'));
            document.getElementById('generarTokenLotonetButton').addEventListener('click', () => generateSession(urls.tokenLotonet, 'Iniciando sesión Lotonet'));
            document.getElementById('exportarExcelButton').addEventListener('click', () => exportRows('excel').catch(error => Swal.fire('Error', error.message, 'error')));
            document.getElementById('exportarPdfButton').addEventListener('click', () => exportRows('pdf').catch(error => Swal.fire('Error', error.message, 'error')));
            updatePlazaFilterButton();
        });
    </script>
@endsection
