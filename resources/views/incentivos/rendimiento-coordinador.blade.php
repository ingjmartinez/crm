@extends('app')

@section('content')
    <style>
        .rc-summary-card {
            border: 0;
            box-shadow: 0 4px 18px rgba(15, 23, 42, .06);
        }

        .rc-summary-value {
            font-size: 1.65rem;
            font-weight: 700;
            line-height: 1;
        }

        .rc-table th,
        .rc-table td {
            vertical-align: middle;
            white-space: nowrap;
        }

        .rc-table thead th {
            font-size: .78rem;
            line-height: 1.2;
        }

        .rc-detail-number {
            font-size: inherit;
            font-weight: 700;
            padding: 0;
            text-decoration: underline;
            text-decoration-style: dotted;
            text-underline-offset: .2rem;
        }

        .rc-rule-badge {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            display: inline-block;
            font-size: .86rem;
            font-weight: 700;
            line-height: 1.35;
            padding: .42rem .65rem;
            white-space: normal;
        }

        .rc-dashboard-modal .modal-dialog {
            max-width: min(96vw, 1500px);
        }

        .rc-dashboard-kpi {
            border: 1px solid #e2e8f0;
            border-radius: .75rem;
            height: 100%;
            padding: 1rem;
        }

        .rc-dashboard-kpi small {
            color: #64748b;
            display: block;
            font-weight: 600;
            text-transform: uppercase;
        }

        .rc-dashboard-kpi strong {
            display: block;
            font-size: 1.35rem;
            margin-top: .35rem;
        }

        .rc-trend-chart {
            min-height: 280px;
            overflow-x: auto;
        }

        .rc-trend-chart svg {
            min-width: 760px;
            width: 100%;
        }
    </style>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <div>
                                <h4 class="mb-sm-0">Rendimiento de Coordinador</h4>
                                <p class="text-muted mb-0 mt-1">Cumplimiento de agencias y usuarios vendedores por coordinador.</p>
                            </div>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('incentivos.index') }}">Incentivos</a></li>
                                    <li class="breadcrumb-item active">Rendimiento de Coordinador</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <form id="formRendimientoCoordinador" method="GET" action="{{ route('incentivos.rendimiento-coordinador.index') }}" class="row g-3 align-items-end">
                            <input type="hidden" name="aplicar" value="1">

                            <div class="col-xl-3 col-md-6">
                                <label for="rc_fecha_inicio" class="form-label">Fecha inicio</label>
                                <input id="rc_fecha_inicio" name="fecha_inicio" type="date" class="form-control"
                                    value="{{ $filtros['fecha_inicio'] }}" required>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <label for="rc_fecha_fin" class="form-label">Fecha fin</label>
                                <input id="rc_fecha_fin" name="fecha_fin" type="date" class="form-control"
                                    value="{{ $filtros['fecha_fin'] }}" required>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <label for="rc_sistema" class="form-label">Sistema</label>
                                <select id="rc_sistema" name="sistema" class="form-select">
                                    @foreach (['Todos', 'Lotobet', 'Lotonet'] as $sistema)
                                        <option value="{{ $sistema }}" @selected($filtros['sistema'] === $sistema)>{{ $sistema }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-xl-3 col-md-6 d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ri-search-line me-1"></i> Generar reporte
                                </button>
                            </div>
                        </form>

                        <div class="alert alert-info border-0 mt-3 mb-0">
                            <strong>Regla:</strong> un usuario cumple cuando alcanza la meta mínima de RD$100,001 y al menos un día de venta.
                            Una agencia cumple cuando al menos uno de los usuarios que vendió en ella cumple la regla.
                            Las agencias asignadas sin ventas se consideran no cumplidas.
                        </div>
                    </div>
                </div>

                @if (!$filtrosAplicados)
                    <div class="alert alert-light border text-center py-4">
                        Selecciona el período y presiona <strong>Generar reporte</strong> para consultar el rendimiento.
                    </div>
                @else
                    <div class="row g-3 mb-4">
                        <div class="col-xl col-md-4 col-6">
                            <div class="card rc-summary-card h-100 mb-0">
                                <div class="card-body">
                                    <p class="text-muted text-uppercase fw-medium mb-2">Coordinadores</p>
                                    <div class="rc-summary-value">{{ number_format($resumen['coordinadores'] ?? 0) }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl col-md-4 col-6">
                            <div class="card rc-summary-card h-100 mb-0">
                                <div class="card-body">
                                    <p class="text-muted text-uppercase fw-medium mb-2">Agencias asignadas</p>
                                    <div class="rc-summary-value">{{ number_format($resumen['agencias_asignadas'] ?? 0) }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl col-md-4 col-6">
                            <div class="card rc-summary-card h-100 mb-0">
                                <div class="card-body">
                                    <p class="text-muted text-uppercase fw-medium mb-2">Agencias cumplieron</p>
                                    <div class="rc-summary-value text-success">{{ number_format($resumen['agencias_cumplieron'] ?? 0) }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl col-md-4 col-6">
                            <div class="card rc-summary-card h-100 mb-0">
                                <div class="card-body">
                                    <p class="text-muted text-uppercase fw-medium mb-2">Agencias no cumplieron</p>
                                    <div class="rc-summary-value text-danger">{{ number_format($resumen['agencias_no_cumplieron'] ?? 0) }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl col-md-4 col-12">
                            <div class="card rc-summary-card h-100 mb-0 border border-warning-subtle">
                                <div class="card-body">
                                    <p class="text-muted text-uppercase fw-medium mb-2">Sin coordinador</p>
                                    <div class="rc-summary-value text-warning">{{ number_format($resumen['agencias_sin_coordinador'] ?? 0) }}</div>
                                    <small class="text-muted">Con ventas: {{ number_format($resumen['agencias_sin_coordinador_con_ventas'] ?? 0) }}</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header d-flex align-items-center justify-content-between gap-3 flex-wrap">
                            <div>
                                <h5 class="card-title mb-1">Resumen por coordinador</h5>
                                <p class="text-muted mb-0">Los usuarios se cuentan una vez por coordinador, aunque vendan en varias de sus agencias.</p>
                            </div>
                            <span class="badge bg-primary-subtle text-primary fs-6">{{ $coordinadores->count() }} coordinadores</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tableRendimientoCoordinador" class="table table-bordered table-striped rc-table w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Coordinador</th>
                                            <th class="text-center">Agencias asignadas</th>
                                            <th class="text-center">Agencias cumplieron</th>
                                            <th class="text-center">Agencias no cumplieron</th>
                                            <th class="text-center">Usuarios cumplieron</th>
                                            <th class="text-center">Usuarios no cumplieron</th>
                                            <th class="text-center">Reporte integral</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($coordinadores as $row)
                                            <tr>
                                                <td class="fw-semibold">{{ $row['coordinador'] }}</td>
                                                <td class="text-center">{{ number_format($row['agencias_asignadas']) }}</td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-link text-success rc-detail-number rc-detail-trigger"
                                                        data-coordinador-id="{{ $row['coordinador_id'] }}" data-detail-type="agencias_cumplieron">
                                                        {{ number_format($row['agencias_cumplieron']) }}
                                                    </button>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-link text-danger rc-detail-number rc-detail-trigger"
                                                        data-coordinador-id="{{ $row['coordinador_id'] }}" data-detail-type="agencias_no_cumplieron">
                                                        {{ number_format($row['agencias_no_cumplieron']) }}
                                                    </button>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-link text-success rc-detail-number rc-detail-trigger"
                                                        data-coordinador-id="{{ $row['coordinador_id'] }}" data-detail-type="usuarios_cumplieron">
                                                        {{ number_format($row['usuarios_cumplieron']) }}
                                                    </button>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-link text-danger rc-detail-number rc-detail-trigger"
                                                        data-coordinador-id="{{ $row['coordinador_id'] }}" data-detail-type="usuarios_no_cumplieron">
                                                        {{ number_format($row['usuarios_no_cumplieron']) }}
                                                    </button>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-primary rc-dashboard-trigger"
                                                        data-coordinador-id="{{ $row['coordinador_id'] }}">
                                                        <i class="ri-dashboard-line me-1"></i> Ver reporte
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card border border-warning-subtle">
                        <div class="card-header d-flex align-items-center justify-content-between gap-3 flex-wrap">
                            <div>
                                <h5 class="card-title mb-1">Agencias sin coordinador asignado</h5>
                                <p class="text-muted mb-0">Incluye agencias con y sin ventas dentro del período seleccionado.</p>
                            </div>
                            <span class="badge bg-warning-subtle text-warning fs-6">{{ $agenciasSinCoordinador->count() }} detectadas</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tableAgenciasSinCoordinador" class="table table-bordered table-striped rc-table w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Terminal</th>
                                            <th>Agencia</th>
                                            <th>Empresa</th>
                                            <th>Sistema</th>
                                            <th class="text-center">Estado</th>
                                            <th class="text-center">Usuarios vendedores</th>
                                            <th class="text-end">Total vendido</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($agenciasSinCoordinador as $row)
                                            <tr @class(['table-warning' => $row['total_vendido'] > 0])>
                                                <td class="fw-semibold">{{ $row['terminal'] ?: 'Sin terminal' }}</td>
                                                <td>{{ $row['agencia'] }}</td>
                                                <td>{{ $row['empresa'] }}</td>
                                                <td>{{ $row['sistema'] }}</td>
                                                <td class="text-center">
                                                    @if ($row['estatus'] === 1)
                                                        <span class="badge bg-success-subtle text-success">Activa</span>
                                                    @else
                                                        <span class="badge bg-secondary-subtle text-secondary">Inactiva</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">{{ number_format($row['usuarios_vendedores']) }}</td>
                                                <td class="text-end fw-semibold" data-order="{{ $row['total_vendido'] }}">
                                                    {{ number_format($row['total_vendido'], 2) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($filtrosAplicados)
        <div id="modalDetalleRendimientoCoordinador" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="rcDetailModalTitle">Detalle de rendimiento</h5>
                            <small class="text-muted" id="rcDetailModalSubtitle"></small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="rcDetailUserNote" class="alert alert-info py-2 d-none">
                            El monto vendido corresponde a esa terminal. El incentivo es el total ganado por el usuario
                            y puede repetirse si vendió en más de una agencia del coordinador.
                        </div>
                        <div class="table-responsive">
                            <table id="tableDetalleRendimientoCoordinador" class="table table-bordered table-striped rc-table w-100">
                                <thead class="table-light" id="rcDetailTableHead"></thead>
                                <tbody id="rcDetailTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="modalReporteIntegralCoordinador" class="modal fade rc-dashboard-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="rcDashboardTitle">Reporte integral</h5>
                            <small class="text-muted" id="rcDashboardSubtitle">Cargando información...</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="rcDashboardLoading" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="text-muted mt-3 mb-0">Calculando rendimiento de la zona...</p>
                        </div>
                        <div id="rcDashboardError" class="alert alert-danger d-none"></div>
                        <div id="rcDashboardContent" class="d-none">
                            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                                <div id="rcDashboardComparison" class="alert mb-0 py-2"></div>
                                <div class="d-flex gap-2">
                                    <a id="rcDashboardPdf" class="btn btn-danger btn-sm" href="#">
                                        <i class="ri-file-pdf-line me-1"></i> PDF ejecutivo
                                    </a>
                                    <a id="rcDashboardExcel" class="btn btn-success btn-sm" href="#">
                                        <i class="ri-file-excel-2-line me-1"></i> Excel detallado
                                    </a>
                                </div>
                            </div>

                            <div id="rcDashboardKpis" class="row g-3 mb-4"></div>

                            <div class="alert alert-info py-2">
                                <strong>Lectura de la cascada:</strong> la meta de RD$100,001 se evalúa por usuario dentro de la zona.
                                La venta de agencia es la suma de sus usuarios y se presenta como indicador comercial independiente.
                            </div>

                            <ul class="nav nav-tabs mb-3" role="tablist">
                                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#rcTabAgencias" type="button">Ranking de agencias</button></li>
                                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#rcTabUsuarios" type="button">Ranking de usuarios</button></li>
                                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#rcTabRescate" type="button">Plan de rescate</button></li>
                                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#rcTabTendencia" type="button">Tendencia y comparación</button></li>
                            </ul>

                            <div class="tab-content">
                                <div id="rcTabAgencias" class="tab-pane fade show active">
                                    <div class="table-responsive">
                                        <table id="rcDashboardAgencias" class="table table-bordered table-striped rc-table w-100">
                                            <thead class="table-light"><tr><th>#</th><th>Terminal</th><th>Agencia</th><th>Estado</th><th class="text-end">Venta total</th><th class="text-center">Usuarios</th><th class="text-center">En meta</th><th class="text-end">Promedio</th><th>Mejor usuario</th></tr></thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                                <div id="rcTabUsuarios" class="tab-pane fade">
                                    <div class="table-responsive">
                                        <table id="rcDashboardUsuarios" class="table table-bordered table-striped rc-table w-100">
                                            <thead class="table-light"><tr><th>#</th><th>Cédula</th><th>Usuario</th><th>Agencia principal</th><th class="text-end">Venta total</th><th class="text-end">Avance</th><th class="text-end">Faltante</th><th>Clasificación</th><th class="text-end">Incentivo</th></tr></thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                                <div id="rcTabRescate" class="tab-pane fade">
                                    <div id="rcRescateSummary" class="row g-3 mb-3"></div>
                                    <h6 class="text-primary">Agencias que requieren intervención</h6>
                                    <p class="text-muted">Ordenadas por urgencia y oportunidad de recuperación.</p>
                                    <div class="table-responsive mb-4">
                                        <table id="rcDashboardRescateAgencias" class="table table-bordered table-striped rc-table w-100">
                                            <thead class="table-light"><tr><th>Prioridad</th><th>Terminal</th><th>Agencia</th><th class="text-end">Venta</th><th class="text-center">Usuarios</th><th>Usuario a trabajar</th><th class="text-end">Avance</th><th>Acción sugerida</th></tr></thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                    <h6 class="text-primary">Usuarios que requieren seguimiento</h6>
                                    <div class="table-responsive">
                                        <table id="rcDashboardRescateUsuarios" class="table table-bordered table-striped rc-table w-100">
                                            <thead class="table-light"><tr><th>Prioridad</th><th>Cédula</th><th>Usuario</th><th>Agencia principal</th><th class="text-end">Venta</th><th class="text-end">Avance</th><th class="text-end">Faltante</th><th>Acción sugerida</th></tr></thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                                <div id="rcTabTendencia" class="tab-pane fade">
                                    <p class="text-muted">Periodo actual frente al periodo inmediatamente anterior con la misma cantidad de días.</p>
                                    <div id="rcDashboardTrend" class="rc-trend-chart border rounded p-3"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('formRendimientoCoordinador');

            form?.addEventListener('submit', function () {
                if (!form.checkValidity()) {
                    return;
                }

                Swal.fire({
                    title: 'Generando datos',
                    text: 'Estamos procesando el rendimiento de los coordinadores.',
                    icon: 'info',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });
            });

            @if ($filtrosAplicados)
                const coordinatorDetails = @json($coordinadores->keyBy('coordinador_id'));
                let detailDataTable = null;
                const commonOptions = {
                    responsive: true,
                    dom: 'Bfrtip',
                    buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
                    pageLength: 25,
                    scrollX: true,
                    language: {
                        search: 'Buscar:',
                        lengthMenu: 'Mostrar _MENU_ registros',
                        info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                        infoEmpty: 'No hay registros disponibles',
                        infoFiltered: '(filtrado de _MAX_ registros)',
                        zeroRecords: 'No se encontraron resultados',
                        paginate: {
                            first: 'Primero',
                            last: 'Último',
                            next: 'Siguiente',
                            previous: 'Anterior'
                        }
                    }
                };

                $('#tableRendimientoCoordinador').DataTable({
                    ...commonOptions,
                    order: [[1, 'desc']]
                });

                $('#tableAgenciasSinCoordinador').DataTable({
                    ...commonOptions,
                    order: [[6, 'desc']]
                });

                const detailTypes = {
                    agencias_cumplieron: {
                        title: 'Agencias que cumplieron',
                        key: 'detalle_agencias_cumplieron',
                        users: false,
                    },
                    agencias_no_cumplieron: {
                        title: 'Agencias que no cumplieron',
                        key: 'detalle_agencias_no_cumplieron',
                        users: false,
                        showAgencyRule: true,
                    },
                    usuarios_cumplieron: {
                        title: 'Usuarios que cumplieron',
                        key: 'detalle_usuarios_cumplieron',
                        users: true,
                        showIncentive: true,
                    },
                    usuarios_no_cumplieron: {
                        title: 'Usuarios que no cumplieron',
                        key: 'detalle_usuarios_no_cumplieron',
                        users: true,
                        showRule: true,
                    },
                };

                const escapeDetailHtml = (value) => String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
                const detailMoney = (value) => new Intl.NumberFormat('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }).format(Number(value || 0));

                document.addEventListener('click', function (event) {
                    const trigger = event.target.closest('.rc-detail-trigger');
                    if (!trigger) return;

                    const coordinator = coordinatorDetails[String(trigger.dataset.coordinadorId)];
                    const type = detailTypes[trigger.dataset.detailType];
                    if (!coordinator || !type) return;

                    const rows = Array.isArray(coordinator[type.key]) ? coordinator[type.key] : [];
                    const tableHead = document.getElementById('rcDetailTableHead');
                    const tableBody = document.getElementById('rcDetailTableBody');
                    const userNote = document.getElementById('rcDetailUserNote');

                    if (detailDataTable) {
                        detailDataTable.destroy();
                        detailDataTable = null;
                    }

                    document.getElementById('rcDetailModalTitle').textContent = type.title;
                    document.getElementById('rcDetailModalSubtitle').textContent = coordinator.coordinador;
                    userNote.classList.toggle('d-none', !type.users && !type.showAgencyRule);
                    if (type.showAgencyRule) {
                        userNote.textContent = 'El avance de la agencia toma como referencia al usuario con mayor venta relacionado con esa terminal, porque la agencia cumple cuando al menos un usuario alcanza la meta de RD$100,001.';
                    } else if (type.users) {
                        userNote.textContent = type.showRule
                            ? 'El monto vendido corresponde a esa terminal. El porcentaje faltante se calcula sobre la venta total del usuario en el período.'
                            : 'El monto vendido corresponde a esa terminal. El incentivo es el total ganado por el usuario y puede repetirse si vendió en más de una agencia del coordinador.';
                    }

                    if (type.users) {
                        tableHead.innerHTML = `
                            <tr>
                                <th>Terminal</th>
                                <th>Agencia</th>
                                <th>Cédula</th>
                                <th>Nombre</th>
                                <th class="text-end">Monto vendido</th>
                                ${type.showIncentive ? '<th class="text-end">Incentivo ganado</th>' : ''}
                                ${type.showRule ? '<th>Cumple regla</th>' : ''}
                            </tr>`;
                        tableBody.innerHTML = rows.map((row) => `
                            <tr>
                                <td class="fw-semibold">${escapeDetailHtml(row.terminal || 'Sin terminal')}</td>
                                <td>${escapeDetailHtml(row.agencia)}</td>
                                <td>${escapeDetailHtml(row.cedula)}</td>
                                <td>${escapeDetailHtml(row.nombre)}</td>
                                <td class="text-end" data-order="${Number(row.monto_vendido || 0)}">${detailMoney(row.monto_vendido)}</td>
                                ${type.showIncentive ? `<td class="text-end fw-semibold" data-order="${Number(row.incentivo_ganado || 0)}">${detailMoney(row.incentivo_ganado)}</td>` : ''}
                                ${type.showRule ? `
                                    <td data-order="${Number(row.faltante_pct || 0)}">
                                        <span class="badge rc-rule-badge">
                                            NO CUMPLE | Faltan RD$${detailMoney(row.faltante_regla)} (${Number(row.faltante_pct || 0).toFixed(2)}%)
                                        </span>
                                    </td>` : ''}
                            </tr>`).join('');
                    } else {
                        tableHead.innerHTML = `
                            <tr>
                                <th>Terminal</th>
                                <th>Nombre de la agencia</th>
                                ${type.showAgencyRule ? '<th class="text-end">Mejor venta de usuario</th><th>Avance a la meta</th>' : ''}
                            </tr>`;
                        tableBody.innerHTML = rows.map((row) => `
                            <tr>
                                <td class="fw-semibold">${escapeDetailHtml(row.terminal || 'Sin terminal')}</td>
                                <td>${escapeDetailHtml(row.agencia)}</td>
                                ${type.showAgencyRule ? `
                                    <td class="text-end" data-order="${Number(row.mejor_venta_usuario || 0)}">${detailMoney(row.mejor_venta_usuario)}</td>
                                    <td data-order="${Number(row.avance_pct || 0)}">
                                        <span class="badge rc-rule-badge">
                                            NO CUMPLE | Faltan RD$${detailMoney(row.faltante_regla)} | Alcanzó ${Number(row.avance_pct || 0).toFixed(2)}%
                                        </span>
                                    </td>` : ''}
                            </tr>`).join('');
                    }

                    const modalElement = document.getElementById('modalDetalleRendimientoCoordinador');
                    bootstrap.Modal.getOrCreateInstance(modalElement).show();

                    setTimeout(() => {
                        detailDataTable = $('#tableDetalleRendimientoCoordinador').DataTable({
                            responsive: true,
                            dom: 'Bfrtip',
                            buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
                            pageLength: 25,
                            order: [[0, 'asc']],
                            language: commonOptions.language,
                        });
                    }, 180);
                });

                let dashboardAgencyTable = null;
                let dashboardUserTable = null;
                let dashboardRescueAgencyTable = null;
                let dashboardRescueUserTable = null;
                const dashboardBaseUrl = @json(url('/incentivos/rendimiento-coordinador'));
                const dashboardFilters = new URLSearchParams({
                    fecha_inicio: @json($filtros['fecha_inicio']),
                    fecha_fin: @json($filtros['fecha_fin']),
                    sistema: @json($filtros['sistema']),
                });

                const dashboardBadge = (classification) => {
                    const classes = {
                        'Excelente': 'bg-primary',
                        'Cumple': 'bg-success',
                        'Cerca': 'bg-warning text-dark',
                        'En seguimiento': 'bg-info text-dark',
                        'Crítico': 'bg-danger',
                    };
                    return `<span class="badge ${classes[classification] || 'bg-secondary'}">${escapeDetailHtml(classification)}</span>`;
                };

                const rescueBadge = (priority) => {
                    const classes = {
                        'Crítica': 'bg-danger',
                        'Crítico': 'bg-danger',
                        'Rescate rápido': 'bg-warning text-dark',
                        'Alta': 'bg-info text-dark',
                        'Seguimiento': 'bg-primary',
                    };
                    return `<span class="badge ${classes[priority] || 'bg-secondary'}">${escapeDetailHtml(priority)}</span>`;
                };

                const renderTrendChart = (rows) => {
                    const target = document.getElementById('rcDashboardTrend');
                    if (!Array.isArray(rows) || rows.length === 0) {
                        target.innerHTML = '<div class="text-muted text-center py-5">No hay ventas para construir la tendencia.</div>';
                        return;
                    }

                    const width = Math.max(760, rows.length * 34);
                    const height = 260;
                    const padding = { left: 55, right: 20, top: 25, bottom: 42 };
                    const plotWidth = width - padding.left - padding.right;
                    const plotHeight = height - padding.top - padding.bottom;
                    const maxValue = Math.max(...rows.flatMap(row => [Number(row.venta_actual || 0), Number(row.venta_anterior || 0)]), 1);
                    const x = index => padding.left + (rows.length === 1 ? plotWidth / 2 : (index / (rows.length - 1)) * plotWidth);
                    const y = value => padding.top + plotHeight - (Number(value || 0) / maxValue) * plotHeight;
                    const actual = rows.map((row, index) => `${x(index)},${y(row.venta_actual)}`).join(' ');
                    const previous = rows.map((row, index) => `${x(index)},${y(row.venta_anterior)}`).join(' ');
                    const labelEvery = Math.max(1, Math.ceil(rows.length / 12));
                    const labels = rows.map((row, index) => index % labelEvery === 0
                        ? `<text x="${x(index)}" y="${height - 14}" text-anchor="middle" font-size="10" fill="#64748b">${escapeDetailHtml(row.etiqueta)}</text>`
                        : '').join('');
                    const grid = [0, .25, .5, .75, 1].map(step => {
                        const gridY = padding.top + plotHeight - step * plotHeight;
                        const value = detailMoney(maxValue * step);
                        return `<line x1="${padding.left}" y1="${gridY}" x2="${width - padding.right}" y2="${gridY}" stroke="#e2e8f0"/><text x="${padding.left - 8}" y="${gridY + 3}" text-anchor="end" font-size="9" fill="#64748b">${value}</text>`;
                    }).join('');

                    target.innerHTML = `
                        <div class="d-flex gap-3 mb-2"><span><i class="d-inline-block bg-success rounded-circle me-1" style="width:10px;height:10px"></i>Periodo actual</span><span><i class="d-inline-block bg-secondary rounded-circle me-1" style="width:10px;height:10px"></i>Periodo anterior</span></div>
                        <svg viewBox="0 0 ${width} ${height}" role="img" aria-label="Tendencia comparativa de ventas">
                            ${grid}${labels}
                            <polyline points="${previous}" fill="none" stroke="#94a3b8" stroke-width="2" stroke-dasharray="5 4"/>
                            <polyline points="${actual}" fill="none" stroke="#0ab39c" stroke-width="3"/>
                        </svg>`;
                };

                const renderDashboard = (data, coordinatorId) => {
                    const summary = data.resumen;
                    const comparison = data.comparacion;
                    document.getElementById('rcDashboardTitle').textContent = data.meta.coordinador;
                    document.getElementById('rcDashboardSubtitle').textContent = `${data.meta.periodo} | ${data.meta.sistema} | Meta individual RD$${detailMoney(data.meta.meta_usuario)}`;

                    const variationPositive = Number(comparison.variacion || 0) >= 0;
                    const comparisonBox = document.getElementById('rcDashboardComparison');
                    comparisonBox.className = `alert mb-0 py-2 ${variationPositive ? 'alert-success' : 'alert-danger'}`;
                    comparisonBox.innerHTML = `<strong>${variationPositive ? '▲' : '▼'} ${comparison.variacion_pct === null ? 'Sin base comparable' : Math.abs(Number(comparison.variacion_pct)).toFixed(2) + '%'}</strong> vs. ${escapeDetailHtml(data.meta.periodo_anterior)} · Anterior RD$${detailMoney(comparison.venta_anterior)}`;

                    const kpis = [
                        ['Venta total de la zona', `RD$${detailMoney(summary.venta_total)}`, 'text-primary'],
                        ['Agencias con ventas', `${summary.agencias_con_ventas} / ${summary.agencias_asignadas}`, 'text-success'],
                        ['Agencias sin ventas', summary.agencias_sin_ventas, summary.agencias_sin_ventas > 0 ? 'text-danger' : 'text-success'],
                        ['Usuarios en meta', `${summary.usuarios_cumplieron} / ${summary.usuarios_vendedores}`, 'text-success'],
                        ['Cumplimiento usuarios', `${Number(summary.cumplimiento_usuarios_pct).toFixed(2)}%`, 'text-primary'],
                        ['Agencias con usuario meta', `${summary.agencias_con_usuario_meta} / ${summary.agencias_asignadas}`, 'text-warning'],
                        ['Promedio por agencia', `RD$${detailMoney(summary.promedio_agencia)}`, ''],
                        ['Incentivo estimado', `RD$${detailMoney(summary.incentivo_total)}`, 'text-success'],
                    ];
                    document.getElementById('rcDashboardKpis').innerHTML = kpis.map(kpi => `
                        <div class="col-xl-3 col-md-6"><div class="rc-dashboard-kpi"><small>${escapeDetailHtml(kpi[0])}</small><strong class="${kpi[2]}">${kpi[1]}</strong></div></div>
                    `).join('');

                    document.querySelector('#rcDashboardAgencias tbody').innerHTML = data.agencias.map(row => `
                        <tr class="${Number(row.venta_total) <= 0 ? 'table-danger' : ''}">
                            <td>${row.ranking}</td><td>${escapeDetailHtml(row.terminal)}</td><td>${escapeDetailHtml(row.agencia)}</td>
                            <td>${row.activa ? '<span class="badge bg-success">Activa</span>' : '<span class="badge bg-secondary">Inactiva</span>'}</td>
                            <td class="text-end" data-order="${Number(row.venta_total)}">${detailMoney(row.venta_total)}</td>
                            <td class="text-center">${row.usuarios}</td><td class="text-center">${row.usuarios_cumplieron}</td>
                            <td class="text-end">${detailMoney(row.promedio_usuario)}</td><td>${escapeDetailHtml(row.mejor_usuario)}</td>
                        </tr>`).join('');

                    document.querySelector('#rcDashboardUsuarios tbody').innerHTML = data.usuarios.map(row => `
                        <tr>
                            <td>${row.ranking}</td><td>${escapeDetailHtml(row.cedula)}</td><td>${escapeDetailHtml(row.nombre)}</td><td>${escapeDetailHtml(row.agencia_principal)}</td>
                            <td class="text-end" data-order="${Number(row.venta_total)}">${detailMoney(row.venta_total)}</td>
                            <td class="text-end" data-order="${Number(row.avance_pct)}">${Number(row.avance_pct).toFixed(2)}%</td>
                            <td class="text-end">${detailMoney(row.faltante)}</td><td>${dashboardBadge(row.clasificacion)}</td>
                            <td class="text-end">${detailMoney(row.incentivo)}</td>
                        </tr>`).join('');

                    const rescue = data.rescate || { agencias: [], usuarios: [], resumen: {} };
                    const rescueSummary = rescue.resumen || {};
                    document.getElementById('rcRescateSummary').innerHTML = [
                        ['Agencias críticas', rescueSummary.agencias_criticas || 0, 'text-danger'],
                        ['Agencias de rescate rápido', rescueSummary.agencias_rescate_rapido || 0, 'text-warning'],
                        ['Usuarios próximos a meta', rescueSummary.usuarios_rescate_rapido || 0, 'text-warning'],
                        ['Usuarios críticos', rescueSummary.usuarios_criticos || 0, 'text-danger'],
                    ].map(item => `<div class="col-xl-3 col-md-6"><div class="rc-dashboard-kpi"><small>${item[0]}</small><strong class="${item[2]}">${item[1]}</strong></div></div>`).join('');

                    document.querySelector('#rcDashboardRescateAgencias tbody').innerHTML = rescue.agencias.map(row => `
                        <tr>
                            <td data-order="${Number(row.prioridad_orden)}">${rescueBadge(row.prioridad)}</td>
                            <td>${escapeDetailHtml(row.terminal)}</td><td>${escapeDetailHtml(row.agencia)}</td>
                            <td class="text-end" data-order="${Number(row.venta_total)}">${detailMoney(row.venta_total)}</td>
                            <td class="text-center">${row.usuarios}</td><td>${escapeDetailHtml(row.mejor_usuario)}</td>
                            <td class="text-end" data-order="${Number(row.mejor_usuario_avance_pct)}">${Number(row.mejor_usuario_avance_pct).toFixed(2)}%</td>
                            <td class="text-wrap">${escapeDetailHtml(row.accion_sugerida)}</td>
                        </tr>`).join('');

                    document.querySelector('#rcDashboardRescateUsuarios tbody').innerHTML = rescue.usuarios.map(row => `
                        <tr>
                            <td data-order="${Number(row.prioridad_orden)}">${rescueBadge(row.prioridad)}</td>
                            <td>${escapeDetailHtml(row.cedula)}</td><td>${escapeDetailHtml(row.nombre)}</td><td>${escapeDetailHtml(row.agencia_principal)}</td>
                            <td class="text-end" data-order="${Number(row.venta_total)}">${detailMoney(row.venta_total)}</td>
                            <td class="text-end" data-order="${Number(row.avance_pct)}">${Number(row.avance_pct).toFixed(2)}%</td>
                            <td class="text-end">${detailMoney(row.faltante)}</td><td class="text-wrap">${escapeDetailHtml(row.accion_sugerida)}</td>
                        </tr>`).join('');

                    if (dashboardAgencyTable) dashboardAgencyTable.destroy();
                    if (dashboardUserTable) dashboardUserTable.destroy();
                    if (dashboardRescueAgencyTable) dashboardRescueAgencyTable.destroy();
                    if (dashboardRescueUserTable) dashboardRescueUserTable.destroy();
                    dashboardAgencyTable = $('#rcDashboardAgencias').DataTable({
                        responsive: true, pageLength: 10, order: [[0, 'asc']], scrollX: true, language: commonOptions.language,
                    });
                    dashboardUserTable = $('#rcDashboardUsuarios').DataTable({
                        responsive: true, pageLength: 10, order: [[0, 'asc']], scrollX: true, language: commonOptions.language,
                    });
                    dashboardRescueAgencyTable = $('#rcDashboardRescateAgencias').DataTable({
                        responsive: true, pageLength: 10, order: [[0, 'asc'], [3, 'asc']], scrollX: true, language: commonOptions.language,
                    });
                    dashboardRescueUserTable = $('#rcDashboardRescateUsuarios').DataTable({
                        responsive: true, pageLength: 10, order: [[0, 'asc'], [5, 'desc']], scrollX: true, language: commonOptions.language,
                    });
                    renderTrendChart(data.tendencia);

                    const exportQuery = dashboardFilters.toString();
                    document.getElementById('rcDashboardPdf').href = `${dashboardBaseUrl}/${coordinatorId}/pdf?${exportQuery}`;
                    document.getElementById('rcDashboardExcel').href = `${dashboardBaseUrl}/${coordinatorId}/excel?${exportQuery}`;
                };

                document.addEventListener('click', async function (event) {
                    const trigger = event.target.closest('.rc-dashboard-trigger');
                    if (!trigger) return;

                    const coordinatorId = trigger.dataset.coordinadorId;
                    const modalElement = document.getElementById('modalReporteIntegralCoordinador');
                    const loading = document.getElementById('rcDashboardLoading');
                    const content = document.getElementById('rcDashboardContent');
                    const error = document.getElementById('rcDashboardError');
                    loading.classList.remove('d-none');
                    content.classList.add('d-none');
                    error.classList.add('d-none');
                    bootstrap.Modal.getOrCreateInstance(modalElement).show();

                    try {
                        const response = await fetch(`${dashboardBaseUrl}/${coordinatorId}/detalle?${dashboardFilters.toString()}`, {
                            headers: { Accept: 'application/json' },
                        });
                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || 'No fue posible generar el reporte integral.');
                        }
                        renderDashboard(data, coordinatorId);
                        content.classList.remove('d-none');
                    } catch (requestError) {
                        error.textContent = requestError.message || 'Ocurrió un error al consultar el reporte.';
                        error.classList.remove('d-none');
                    } finally {
                        loading.classList.add('d-none');
                    }
                });

                document.querySelectorAll('#modalReporteIntegralCoordinador [data-bs-toggle="tab"]').forEach(tab => {
                    tab.addEventListener('shown.bs.tab', () => {
                        dashboardAgencyTable?.columns.adjust();
                        dashboardUserTable?.columns.adjust();
                        dashboardRescueAgencyTable?.columns.adjust();
                        dashboardRescueUserTable?.columns.adjust();
                    });
                });
            @endif
        });
    </script>
@endsection
