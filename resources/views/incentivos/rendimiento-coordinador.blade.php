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
                                        <span class="badge bg-danger">
                                            ALCANZÓ ${Number(row.avance_pct || 0).toFixed(2)}% | Faltan RD$${detailMoney(row.faltante_regla)}
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
            @endif
        });
    </script>
@endsection
