@extends('app')

@section('content')
    <style>
        .gestion-summary-card {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(15, 23, 42, 0.07);
            transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
            will-change: transform;
        }

        .gestion-summary-card::before {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            opacity: 0;
            transition: opacity .22s ease;
            background:
                linear-gradient(135deg, rgba(var(--gestion-card-rgb), .18), rgba(255, 255, 255, 0) 48%),
                linear-gradient(90deg, rgba(var(--gestion-card-rgb), .95), rgba(var(--gestion-card-rgb), .18));
            background-size: 100% 100%, 100% 3px;
            background-repeat: no-repeat;
            background-position: center, left top;
        }

        .gestion-summary-card:hover,
        .gestion-summary-card:focus-within {
            transform: translateY(-4px);
            border-color: rgba(var(--gestion-card-rgb), .42);
            box-shadow:
                0 16px 34px rgba(15, 23, 42, .12),
                0 10px 22px rgba(var(--gestion-card-rgb), .20);
        }

        .gestion-summary-card:hover::before,
        .gestion-summary-card:focus-within::before {
            opacity: 1;
        }

        .gestion-summary-card .card-body {
            position: relative;
            z-index: 1;
        }

        .gestion-card-slate { --gestion-card-rgb: 71, 85, 105; }
        .gestion-card-success { --gestion-card-rgb: 0, 180, 137; }
        .gestion-card-danger { --gestion-card-rgb: 255, 88, 72; }
        .gestion-card-indigo { --gestion-card-rgb: 67, 90, 197; }
        .gestion-card-warning { --gestion-card-rgb: 247, 184, 75; }
        .gestion-card-dark { --gestion-card-rgb: 33, 37, 41; }

        .gestion-server-time {
            display: inline-flex;
            align-items: center;
            align-self: center;
            gap: .5rem;
            min-height: 38px;
            padding: .45rem .75rem;
            border: 1px solid rgba(0, 45, 114, .35);
            border-top: 3px solid #ce1126;
            border-bottom: 3px solid #002d72;
            border-radius: .375rem;
            color: #002d72;
            background: #fff;
            box-shadow: none;
            white-space: nowrap;
        }

        .gestion-server-time i {
            color: #ce1126;
            font-size: 1rem;
        }

        .gestion-server-time-label {
            color: #64748b;
            font-size: .78rem;
            line-height: 1;
        }

        .gestion-server-time-value {
            color: #002d72;
            font-weight: 700;
            line-height: 1;
        }
    </style>
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Gestion de agencias</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('reportes.index') }}">Reportes</a></li>
                                    <li class="breadcrumb-item active">Gestion de agencias</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                @if (isset($errors) && $errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (!empty($errores))
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errores as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (!empty($resumen))
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <div>
                                        <h5 class="card-title mb-1">Consulta por agencia</h5>
                                        <p class="text-muted mb-0">Busca por terminal o nombre de agencia usando la data cargada.</p>
                                    </div>
                                    <div class="d-flex flex-wrap align-items-center gap-2 col-xl-6 col-lg-7 col-md-12">
                                        <button type="button" class="btn btn-outline-primary" id="btnConfigurarTiempoVentas">
                                            <i class="ri-time-line align-bottom me-1"></i>
                                            Configurar tiempo de ventas
                                        </button>
                                        <div class="gestion-server-time" title="Hora usada por el servidor para calcular los estatus">
                                            <i class="ri-time-line"></i>
                                            <div>
                                                <div class="gestion-server-time-label">Hora servidor</div>
                                                <div class="gestion-server-time-value" id="gestionHoraServidor">--:--:--</div>
                                            </div>
                                        </div>
                                        <div class="text-muted small w-100 text-md-end" id="gestionHoraCalculoTexto">
                                            Estatus calculados con la hora del servidor: --:--:--
                                        </div>
                                        <div class="flex-grow-1">
                                            <input type="search" class="form-control" id="consultaAgenciaInput" list="consultaAgenciaOptions" placeholder="Buscar agencia o terminal">
                                            <datalist id="consultaAgenciaOptions"></datalist>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                        <div>
                                            <p class="text-muted mb-1">Agencia seleccionada</p>
                                            <h5 class="mb-0" id="consultaAgenciaNombre">Selecciona una agencia</h5>
                                        </div>
                                        <span class="badge bg-light text-dark" id="consultaAgenciaTerminal">Terminal</span>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-xl-4 col-md-6">
                                            <div class="border rounded p-3 h-100">
                                                <p class="text-muted mb-1">Tradicionales</p>
                                                <h3 class="mb-1" id="consultaTradicionalTotal">0.00</h3>
                                                <small class="text-muted" id="consultaTradicionalUltima">Ult trans N/D</small>
                                            </div>
                                        </div>
                                        <div class="col-xl-4 col-md-6">
                                            <div class="border rounded p-3 h-100">
                                                <p class="text-muted mb-1">No tradicionales</p>
                                                <h3 class="mb-1" id="consultaNoTradicionalTotal">0.00</h3>
                                                <small class="text-muted" id="consultaNoTradicionalUltima">Ult trans N/D</small>
                                            </div>
                                        </div>
                                        <div class="col-xl-4 col-md-12">
                                            <div class="border rounded p-3 h-100">
                                                <p class="text-muted mb-1">Total</p>
                                                <h3 class="mb-1" id="consultaTotalAgencia">0.00</h3>
                                                <small class="text-muted">Tradicional + No tradicional</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div>
                                    <h5 class="card-title mb-1">Carga y limpieza de agencias</h5>
                                    <p class="text-muted mb-0">Se conservaran solo filas con estatus Validos y las columnas necesarias. Puedes cargar XLSX o CSV.</p>
                                </div>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('reportes.gestion-agencias.procesar') }}" enctype="multipart/form-data" class="row g-3 align-items-end" id="gestionAgenciasForm">
                                    @csrf
                                    <div class="col-xl-5 col-lg-6">
                                        <label for="tradicional" class="form-label">Tradicional</label>
                                        <input type="file" class="form-control" id="tradicional" name="tradicional" accept=".xlsx,.csv,.txt,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain" required>
                                    </div>
                                    <div class="col-xl-5 col-lg-6">
                                        <label for="no_tradicional" class="form-label">No Tradicional</label>
                                        <input type="file" class="form-control" id="no_tradicional" name="no_tradicional" accept=".xlsx,.csv,.txt,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain" required>
                                    </div>
                                    <div class="col-xl-2 col-lg-12">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="ri-filter-3-line align-bottom me-1"></i>
                                            Limpiar
                                        </button>
                                    </div>
                                </form>

                                @if (!empty($archivos))
                                    <div class="alert alert-info mt-3 mb-0">
                                        <div><strong>Tradicional:</strong> {{ $archivos['tradicional'] ?? '-' }}</div>
                                        <div><strong>No Tradicional:</strong> {{ $archivos['no_tradicional'] ?? '-' }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                @if (!empty($resumen))
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <div class="card h-100 mb-0 gestion-summary-card gestion-card-slate">
                                <div class="card-body">
                                    <p class="text-muted mb-1">Premio de venta por Hora</p>
                                    <h4 class="mb-0">{{ number_format($resumen['venta_por_hora'] ?? 0, 0) }}</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card h-100 mb-0 gestion-summary-card gestion-card-success">
                                <div class="card-body">
                                    <p class="text-muted mb-1">Agencias con ventas</p>
                                    <h4 class="mb-0 text-success">{{ number_format($resumen['total_validas'] ?? 0) }}</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card h-100 mb-0 cursor-pointer gestion-summary-card gestion-card-danger" id="cardAgenciasSinVentaGestion" role="button" tabindex="0" aria-label="Ver agencias sin ventas">
                                <div class="card-body">
                                    <p class="text-muted mb-1">Agencias sin ventas <i class="ri-search-eye-line align-middle ms-1"></i></p>
                                    <h4 class="mb-0 text-danger">{{ number_format($resumen['total_eliminadas'] ?? 0) }}</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card h-100 mb-0 gestion-summary-card gestion-card-indigo">
                                <div class="card-body">
                                    <p class="text-muted mb-1">Total vendido</p>
                                    <h4 class="mb-0">{{ number_format($resumen['total_apostado'] ?? 0, 2) }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-xl-3 col-md-6">
                            <div class="card h-100 mb-0 gestion-summary-card gestion-card-success">
                                <div class="card-body py-3 d-flex flex-column justify-content-between">
                                    <p class="text-muted mb-1">Al dia</p>
                                    <h4 class="mb-0 text-success" data-estatus-resumen="Al dia">{{ number_format($estatusResumen['Al dia'] ?? 0) }}</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="card h-100 mb-0 cursor-pointer gestion-summary-card gestion-card-warning" data-card-estatus="Aviso" role="button" tabindex="0" aria-label="Ver agencias en aviso">
                                <div class="card-body py-3 d-flex flex-column justify-content-between">
                                    <p class="text-muted mb-1">Aviso</p>
                                    <h4 class="mb-0 text-warning" data-estatus-resumen="Aviso">{{ number_format($estatusResumen['Aviso'] ?? 0) }}</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="card h-100 mb-0 cursor-pointer gestion-summary-card gestion-card-danger" data-card-estatus="En Alerta" role="button" tabindex="0" aria-label="Ver agencias en alerta">
                                <div class="card-body py-3 d-flex flex-column justify-content-between">
                                    <p class="text-muted mb-1">En Alerta</p>
                                    <h4 class="mb-0 text-danger" data-estatus-resumen="En Alerta">{{ number_format($estatusResumen['En Alerta'] ?? 0) }}</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="card h-100 mb-0 cursor-pointer gestion-summary-card gestion-card-dark" data-card-estatus="Requiere llamada" role="button" tabindex="0" aria-label="Ver agencias que requieren llamada">
                                <div class="card-body py-3 d-flex flex-column justify-content-between">
                                    <p class="text-muted mb-1">Requiere llamada</p>
                                    <h4 class="mb-0 text-dark" data-estatus-resumen="Requiere llamada">{{ number_format($estatusResumen['Requiere llamada'] ?? 0) }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <div>
                                        <h5 class="card-title mb-1">Tendencia de ventas por hora</h5>
                                        <p class="text-muted mb-0">
                                            Desde {{ $tendenciaVentasHora['primera_venta'] ?? 'N/D' }} hasta {{ $tendenciaVentasHora['ultima_venta'] ?? 'N/D' }}
                                        </p>
                                    </div>
                                    <span class="badge bg-primary-subtle text-primary">
                                        Acumulado RD$ {{ number_format((float) ($tendenciaVentasHora['total'] ?? 0), 2) }}
                                    </span>
                                </div>
                                <div class="card-body py-2">
                                    <div id="chart-gestion-ventas-hora" style="height: 240px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div>
                                    <h5 class="card-title mb-1">Datos limpios</h5>
                                    @if (!empty($resumen))
                                        <p class="text-muted mb-0">
                                            Tradicional: {{ number_format($resumen['tradicional_validas'] ?? 0) }} |
                                            No Tradicional: {{ number_format($resumen['no_tradicional_validas'] ?? 0) }}
                                        </p>
                                    @endif
                                </div>
                                @if (!empty($resumen))
                                    <div class="d-flex flex-wrap align-items-end justify-content-end gap-2 col-xl-5 col-lg-6 col-md-12">
                                        <button type="button" class="btn btn-outline-danger" id="btnPdfGestionAgencias">
                                            <i class="ri-file-pdf-2-line align-bottom me-1"></i>
                                            Generar PDF
                                        </button>
                                        <div class="flex-grow-1">
                                        <label for="filtroEstatusVentas" class="form-label mb-1">Filtrar por estatus</label>
                                        <select class="form-select" id="filtroEstatusVentas">
                                            <option value="">Todos</option>
                                            <option value="Al dia">Al dia</option>
                                            <option value="Aviso">Aviso</option>
                                            <option value="En Alerta">En Alerta</option>
                                            <option value="Requiere llamada">Requiere llamada</option>
                                        </select>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle w-100" id="table-gestion-agencias">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Tipo</th>
                                                <th>Terminal</th>
                                                <th>Ultima transaccion</th>
                                                <th>Estatus</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @if (empty($resumen))
                                                <tr>
                                                    <td colspan="4" class="text-center text-muted">Carga ambos archivos XLSX o CSV para ver la data limpia.</td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalAgenciasSinVentaGestion" tabindex="-1" aria-labelledby="modalAgenciasSinVentaGestionLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header d-flex justify-content-between align-items-center">
                    <h5 class="modal-title" id="modalAgenciasSinVentaGestionLabel">Agencias sin ventas</h5>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-success btn-sm" id="btnDescargarAgenciasSinVentaGestionExcel">
                            <i class="ri-file-excel-2-line me-1"></i>Descargar Excel
                        </button>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table id="tableModalAgenciasSinVentaGestion" class="table table-striped table-bordered w-100">
                            <thead>
                                <tr>
                                    <th>Agencia</th>
                                    <th>Terminal</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEstatusTerminalesGestion" tabindex="-1" aria-labelledby="modalEstatusTerminalesGestionLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header d-flex justify-content-between align-items-center">
                    <h5 class="modal-title" id="modalEstatusTerminalesGestionLabel">Agencias por estatus</h5>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-success btn-sm" id="btnDescargarEstatusTerminalesGestionExcel">
                            <i class="ri-file-excel-2-line me-1"></i>Descargar Excel
                        </button>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table id="tableModalEstatusTerminalesGestion" class="table table-striped table-bordered w-100">
                            <thead>
                                <tr>
                                    <th>Agencia</th>
                                    <th>Terminal</th>
                                    <th>Tipo</th>
                                    <th>Ultima transaccion</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="{{ asset('libs/apexcharts/apexcharts.min.js') }}"></script>
    <script>
        window.gestionAgenciasData = {
            agenciasSinVentas: @json($agenciasSinVentas ?? []),
            ventasPorAgencia: @json($ventasPorAgencia ?? []),
            tendenciaVentasHora: @json($tendenciaVentasHora ?? ['labels' => [], 'series' => []]),
            estatusResumen: @json($estatusResumen ?? []),
            estatusDetalle: @json($estatusDetalle ?? []),
            horaServidor: @json($horaServidor ?? now()->toIso8601String()),
            tieneResultado: @json(!empty($resumen)),
            dataUrl: @json(route('reportes.gestion-agencias.data')),
            pdfUrl: @json(route('reportes.gestion-agencias.pdf')),
        };
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const table = $('#table-gestion-agencias');
            const form = document.getElementById('gestionAgenciasForm');
            const filtroEstatusVentas = document.getElementById('filtroEstatusVentas');
            const btnConfigurarTiempoVentas = document.getElementById('btnConfigurarTiempoVentas');
            const btnPdfGestionAgencias = document.getElementById('btnPdfGestionAgencias');
            const gestionHoraServidor = document.getElementById('gestionHoraServidor');
            const gestionHoraCalculoTexto = document.getElementById('gestionHoraCalculoTexto');
            const serverClockState = {
                timerId: null,
                serverTimestamp: null,
                clientStartedAt: null,
            };
            const agenciasSinVentas = Array.isArray(window.gestionAgenciasData?.agenciasSinVentas)
                ? window.gestionAgenciasData.agenciasSinVentas
                : [];
            const ventasPorAgencia = Array.isArray(window.gestionAgenciasData?.ventasPorAgencia)
                ? window.gestionAgenciasData.ventasPorAgencia
                : [];
            const tendenciaVentasHora = window.gestionAgenciasData?.tendenciaVentasHora || {};
            let estatusDetalle = window.gestionAgenciasData?.estatusDetalle || {};
            let estatusModalActual = '';
            let dtGestionAgencias = null;
            let dtModalAgenciasSinVentaGestion = null;
            let dtModalEstatusTerminalesGestion = null;

            const escapeHtml = (value) => (value ?? '').toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
            const money = (value) => Number(value || 0).toLocaleString('es-DO', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
            const number = (value) => Number(value || 0).toLocaleString('es-DO');

            const renderTendenciaVentasHora = () => {
                const chartElement = document.querySelector('#chart-gestion-ventas-hora');
                const labels = Array.isArray(tendenciaVentasHora.labels) ? tendenciaVentasHora.labels : [];
                const series = Array.isArray(tendenciaVentasHora.series) ? tendenciaVentasHora.series : [];

                if (!chartElement || typeof ApexCharts === 'undefined') return;

                if (!labels.length || !series.length) {
                    chartElement.innerHTML = '<div class="text-muted text-center py-5">No hay ventas con hora valida para graficar.</div>';
                    return;
                }

                const chart = new ApexCharts(chartElement, {
                    series: [{
                        name: 'Ventas acumuladas',
                        data: series,
                    }],
                    chart: {
                        type: 'area',
                        height: 240,
                        toolbar: { show: false },
                        zoom: { enabled: false },
                    },
                    stroke: {
                        curve: 'smooth',
                        width: 3,
                    },
                    fill: {
                        type: 'gradient',
                        gradient: {
                            shadeIntensity: 1,
                            inverseColors: false,
                            opacityFrom: 0.35,
                            opacityTo: 0.05,
                            stops: [0, 90, 100],
                        },
                    },
                    dataLabels: { enabled: false },
                    xaxis: {
                        categories: labels,
                        labels: { style: { colors: '#64748b' } },
                    },
                    yaxis: {
                        labels: {
                            formatter: function (value) {
                                return 'RD$ ' + Number(value || 0).toLocaleString('en-US', {
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0,
                                });
                            },
                        },
                    },
                    colors: ['#0ab39c'],
                    grid: {
                        borderColor: '#e2e8f0',
                        strokeDashArray: 4,
                    },
                    tooltip: {
                        y: {
                            formatter: function (value) {
                                return 'RD$ ' + Number(value || 0).toLocaleString('en-US', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2,
                                });
                            },
                        },
                    },
                    legend: { show: false },
                });

                chart.render();
            };

            const formatoUltimaTransaccion = (ultima) => {
                if (!ultima) return 'Ult trans N/D';

                return `Ult trans ${ultima.hora || 'N/D'} por ${money(ultima.monto)}`;
            };

            const getUmbralesVentas = () => {
                try {
                    const saved = JSON.parse(localStorage.getItem('gestionAgenciasUmbralesVentas') || '{}');

                    return {
                        aviso: Number(saved.aviso || 20),
                        alerta: Number(saved.alerta || 30),
                        llamada: Number(saved.llamada || 60),
                    };
                } catch (error) {
                    return { aviso: 20, alerta: 30, llamada: 60 };
                }
            };

            const setUmbralesVentas = (umbrales) => {
                localStorage.setItem('gestionAgenciasUmbralesVentas', JSON.stringify(umbrales));
            };

            const formatterHoraServidor = new Intl.DateTimeFormat('es-DO', {
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true,
            });

            const renderHoraServidor = () => {
                if (!gestionHoraServidor) return;

                if (!Number.isFinite(serverClockState.serverTimestamp) || !Number.isFinite(serverClockState.clientStartedAt)) {
                    gestionHoraServidor.textContent = 'N/D';
                    return;
                }

                const fecha = new Date(serverClockState.serverTimestamp + (Date.now() - serverClockState.clientStartedAt));
                gestionHoraServidor.textContent = formatterHoraServidor.format(fecha);
            };

            const sincronizarHoraServidor = (horaServidor) => {
                if (!gestionHoraServidor) return;

                const timestamp = Date.parse(horaServidor || '');

                if (Number.isNaN(timestamp)) {
                    serverClockState.serverTimestamp = null;
                    serverClockState.clientStartedAt = null;
                    gestionHoraServidor.textContent = 'N/D';
                    if (gestionHoraCalculoTexto) {
                        gestionHoraCalculoTexto.textContent = 'Estatus calculados con la hora del servidor: N/D';
                    }
                    return;
                }

                serverClockState.serverTimestamp = timestamp;
                serverClockState.clientStartedAt = Date.now();
                if (gestionHoraCalculoTexto) {
                    gestionHoraCalculoTexto.textContent = `Estatus calculados con la hora del servidor: ${formatterHoraServidor.format(new Date(timestamp))}`;
                }
                renderHoraServidor();
            };

            const iniciarHoraServidor = () => {
                if (!gestionHoraServidor) return;
                sincronizarHoraServidor(window.gestionAgenciasData?.horaServidor || '');

                if (serverClockState.timerId !== null) {
                    clearInterval(serverClockState.timerId);
                }

                serverClockState.timerId = setInterval(renderHoraServidor, 1000);
            };

            const renderEstatusBadge = (estatus) => {
                const classes = {
                    'Al dia': 'bg-success',
                    'Aviso': 'bg-warning text-dark',
                    'En Alerta': 'bg-danger',
                    'Requiere llamada': 'bg-dark',
                };

                return `<span class="badge ${classes[estatus] || 'bg-secondary'}">${escapeHtml(estatus || 'N/D')}</span>`;
            };

            const actualizarTarjetasEstatus = (resumen) => {
                if (!resumen || typeof resumen !== 'object') return;

                document.querySelectorAll('[data-estatus-resumen]').forEach((element) => {
                    const estatus = element.dataset.estatusResumen;
                    element.textContent = number(resumen[estatus] || 0);
                });
            };

            const actualizarDetalleEstatus = (detalle) => {
                if (!detalle || typeof detalle !== 'object') return;

                estatusDetalle = detalle;
            };

            const bootConsultaAgencia = () => {
                const input = document.getElementById('consultaAgenciaInput');
                const options = document.getElementById('consultaAgenciaOptions');
                const nombre = document.getElementById('consultaAgenciaNombre');
                const terminal = document.getElementById('consultaAgenciaTerminal');
                const tradicionalTotal = document.getElementById('consultaTradicionalTotal');
                const tradicionalUltima = document.getElementById('consultaTradicionalUltima');
                const noTradicionalTotal = document.getElementById('consultaNoTradicionalTotal');
                const noTradicionalUltima = document.getElementById('consultaNoTradicionalUltima');
                const totalAgencia = document.getElementById('consultaTotalAgencia');

                if (!input || !options || !ventasPorAgencia.length) return;

                options.innerHTML = ventasPorAgencia
                    .slice(0, 5000)
                    .map((item) => `<option value="${escapeHtml(item.label)}"></option>`)
                    .join('');

                const render = (item) => {
                    if (!item) {
                        if (nombre) nombre.textContent = 'Agencia no encontrada';
                        if (terminal) terminal.textContent = 'Terminal';
                        if (tradicionalTotal) tradicionalTotal.textContent = '0.00';
                        if (tradicionalUltima) tradicionalUltima.textContent = 'Ult trans N/D';
                        if (noTradicionalTotal) noTradicionalTotal.textContent = '0.00';
                        if (noTradicionalUltima) noTradicionalUltima.textContent = 'Ult trans N/D';
                        if (totalAgencia) totalAgencia.textContent = '0.00';
                        return;
                    }

                    if (nombre) nombre.textContent = item.agencia || item.label || 'SIN AGENCIA';
                    if (terminal) terminal.textContent = item.terminal || 'SIN TERMINAL';
                    if (tradicionalTotal) tradicionalTotal.textContent = money(item.tradicional?.total);
                    if (tradicionalUltima) tradicionalUltima.textContent = formatoUltimaTransaccion(item.tradicional?.ultima);
                    if (noTradicionalTotal) noTradicionalTotal.textContent = money(item.no_tradicional?.total);
                    if (noTradicionalUltima) noTradicionalUltima.textContent = formatoUltimaTransaccion(item.no_tradicional?.ultima);
                    if (totalAgencia) totalAgencia.textContent = money(item.total);
                };

                const buscar = () => {
                    const query = input.value.trim().toLowerCase();
                    if (!query) {
                        render(ventasPorAgencia[0] || null);
                        return;
                    }

                    const exacta = ventasPorAgencia.find((item) => (item.label || '').toLowerCase() === query);
                    const parcial = exacta || ventasPorAgencia.find((item) => (item.busqueda || item.label || '').toLowerCase().includes(query));

                    render(parcial || null);
                };

                input.addEventListener('input', buscar);
                input.addEventListener('change', buscar);
                render(ventasPorAgencia[0] || null);
            };

            const limpiarCacheDataTable = () => {
                try {
                    if (table.length && typeof $ === 'function' && $.fn?.DataTable?.isDataTable(table)) {
                        table.DataTable().clear().destroy();
                    }
                } catch (error) {
                    console.warn('No se pudo destruir el DataTable anterior.', error);
                }

                try {
                    Object.keys(localStorage)
                        .filter((key) => key.toLowerCase().includes('table-gestion-agencias'))
                        .forEach((key) => localStorage.removeItem(key));
                    Object.keys(sessionStorage)
                        .filter((key) => key.toLowerCase().includes('table-gestion-agencias'))
                        .forEach((key) => sessionStorage.removeItem(key));
                } catch (error) {
                    console.warn('No se pudo limpiar el cache local del DataTable.', error);
                }
            };

            const limpiarVistaReporte = () => {
                limpiarCacheDataTable();

                const tbody = document.querySelector('#table-gestion-agencias tbody');
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Limpiando vista y preparando nueva carga...</td></tr>';
                }

                const input = document.getElementById('consultaAgenciaInput');
                const options = document.getElementById('consultaAgenciaOptions');
                if (input) input.value = '';
                if (options) options.innerHTML = '';

                const setText = (id, value) => {
                    const element = document.getElementById(id);
                    if (element) element.textContent = value;
                };

                setText('consultaAgenciaNombre', 'Procesando nueva carga');
                setText('consultaAgenciaTerminal', 'Terminal');
                setText('consultaTradicionalTotal', '0.00');
                setText('consultaTradicionalUltima', 'Ult trans N/D');
                setText('consultaNoTradicionalTotal', '0.00');
                setText('consultaNoTradicionalUltima', 'Ult trans N/D');
                setText('consultaTotalAgencia', '0.00');
            };

            const showCargaReporte = () => {
                if (typeof Swal === 'undefined' || typeof Swal.fire !== 'function') return;

                sessionStorage.setItem('gestionAgenciasProcesando', '1');
                let progress = 12;

                Swal.fire({
                    title: 'Procesando reporte',
                    html: `
                        <div class="progress" style="height: 10px;">
                            <div id="gestionAgenciasProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 12%"></div>
                        </div>
                        <div class="text-muted mt-2" id="gestionAgenciasProgressText">Limpiando vista y cache anterior...</div>
                    `,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        const bar = document.getElementById('gestionAgenciasProgressBar');
                        const text = document.getElementById('gestionAgenciasProgressText');
                        const interval = setInterval(() => {
                            progress = Math.min(progress + Math.floor(Math.random() * 7) + 2, 94);
                            if (bar) bar.style.width = `${progress}%`;
                            if (text) text.textContent = progress < 25
                                ? 'Limpiando vista y cache anterior...'
                                : progress < 45
                                    ? 'Subiendo documentos al servidor...'
                                    : progress < 70
                                        ? 'Leyendo y validando archivos...'
                                        : progress < 88
                                            ? 'Calculando agencias con ventas y sin ventas...'
                                            : 'Esperando respuesta para reconstruir DataTable...';
                        }, 700);

                        const popup = Swal.getPopup();
                        if (popup) popup.__gestionAgenciasInterval = interval;
                    },
                    willClose: () => {
                        const popup = Swal.getPopup();
                        if (popup?.__gestionAgenciasInterval) {
                            clearInterval(popup.__gestionAgenciasInterval);
                        }
                    }
                });
            };

            const abrirConfiguracionTiempoVentas = async () => {
                if (typeof Swal === 'undefined' || typeof Swal.fire !== 'function') return;

                const umbrales = getUmbralesVentas();
                const result = await Swal.fire({
                    title: 'Configurar tiempo de ventas',
                    html: `
                        <div class="text-start">
                            <label class="form-label">Aviso desde minutos</label>
                            <input type="number" min="1" class="form-control mb-3" id="swalUmbralAviso" value="${umbrales.aviso}">
                            <label class="form-label">En Alerta desde minutos</label>
                            <input type="number" min="1" class="form-control mb-3" id="swalUmbralAlerta" value="${umbrales.alerta}">
                            <label class="form-label">Requiere llamada desde minutos</label>
                            <input type="number" min="1" class="form-control" id="swalUmbralLlamada" value="${umbrales.llamada}">
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Guardar',
                    cancelButtonText: 'Cancelar',
                    preConfirm: () => {
                        const aviso = Number(document.getElementById('swalUmbralAviso')?.value || 0);
                        const alerta = Number(document.getElementById('swalUmbralAlerta')?.value || 0);
                        const llamada = Number(document.getElementById('swalUmbralLlamada')?.value || 0);

                        if (!aviso || !alerta || !llamada || aviso >= alerta || alerta >= llamada) {
                            Swal.showValidationMessage('Debe cumplirse: Aviso < En Alerta < Requiere llamada.');
                            return false;
                        }

                        return { aviso, alerta, llamada };
                    }
                });

                if (!result.isConfirmed || !result.value) return;

                    setUmbralesVentas(result.value);
                    actualizarTarjetasEstatus({
                        'Al dia': 0,
                        'Aviso': 0,
                        'En Alerta': 0,
                        'Requiere llamada': 0,
                    });
                    actualizarDetalleEstatus({
                        'Al dia': [],
                        'Aviso': [],
                        'En Alerta': [],
                        'Requiere llamada': [],
                    });

                    if (dtGestionAgencias) {
                        dtGestionAgencias.ajax.reload(null, true);
                }
            };

            const prepararEnvioReporte = (event) => {
                if (form?.dataset.submitting === '1') return;

                if (form && !form.checkValidity()) {
                    return;
                }

                event.preventDefault();
                form.dataset.submitting = '1';
                limpiarVistaReporte();
                showCargaReporte();

                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        HTMLFormElement.prototype.submit.call(form);
                    });
                });
            };

            const estaProcesandoReporte = () => sessionStorage.getItem('gestionAgenciasProcesando') === '1';

            const mostrarPreparandoDataTable = () => {
                if (!estaProcesandoReporte() || typeof Swal === 'undefined' || typeof Swal.fire !== 'function') return;

                Swal.fire({
                    title: 'Preparando DataTable',
                    html: `
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 96%"></div>
                        </div>
                        <div class="text-muted mt-2">Renderizando tabla en el navegador...</div>
                    `,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                });
            };

            const esperarPintadoNavegador = () => new Promise((resolve) => {
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        setTimeout(resolve, 250);
                    });
                });
            });

            const esperarDataTableVisible = () => new Promise((resolve) => {
                let intentos = 0;
                const revisar = () => {
                    const wrapperListo = document.querySelector('#table-gestion-agencias_wrapper');

                    if (wrapperListo || intentos >= 80) {
                        resolve();
                        return;
                    }

                    intentos++;
                    setTimeout(revisar, 150);
                };

                revisar();
            });

            const finalizarCargaReporte = () => {
                if (sessionStorage.getItem('gestionAgenciasProcesando') !== '1') return;
                sessionStorage.removeItem('gestionAgenciasProcesando');

                if (typeof Swal === 'undefined' || typeof Swal.fire !== 'function') return;

                Swal.fire({
                    title: 'Carga completada',
                    html: `
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 100%"></div>
                        </div>
                        <div class="text-muted mt-2">DataTable listo al 100%.</div>
                    `,
                    icon: 'success',
                    timer: 900,
                    showConfirmButton: false,
                });
            };

            if (form) {
                form.addEventListener('submit', prepararEnvioReporte);
            }

            if (window.gestionAgenciasData?.tieneResultado) {
                mostrarPreparandoDataTable();
            }

            let dataTableReady = Promise.resolve();

            if (window.gestionAgenciasData?.tieneResultado && table.length && !table.find('tbody tr td[colspan]').length) {
                dataTableReady = new Promise((resolve) => {
                    let resolved = false;
                    const resolveOnce = () => {
                        if (resolved) return;
                        resolved = true;
                        resolve();
                    };

                    setTimeout(resolveOnce, 60000);

                    dtGestionAgencias = table.DataTable({
                        processing: true,
                        serverSide: true,
                        ajax: {
                            url: window.gestionAgenciasData.dataUrl,
                            type: 'GET',
                            cache: false,
                            data: function (data) {
                                const umbrales = getUmbralesVentas();
                                data.estatus_filter = filtroEstatusVentas?.value || '';
                                data.umbral_aviso = umbrales.aviso;
                                data.umbral_alerta = umbrales.alerta;
                                data.umbral_llamada = umbrales.llamada;
                                data._ts = Date.now();
                            },
                            dataSrc: function (json) {
                                actualizarTarjetasEstatus(json?.estatusResumen);
                                actualizarDetalleEstatus(json?.estatusDetalle);
                                if (json?.horaServidor) {
                                    window.gestionAgenciasData.horaServidor = json.horaServidor;
                                    sincronizarHoraServidor(json.horaServidor);
                                }
                                return json?.data || [];
                            },
                        },
                        responsive: true,
                        deferRender: true,
                        pageLength: 25,
                        lengthMenu: [[25, 50, 100, 250, 500], [25, 50, 100, 250, 500]],
                        order: [[2, 'desc']],
                        columns: [
                            {
                                data: 'tipo',
                                render: function (data) {
                                    const badge = data === 'Tradicional' ? 'bg-primary' : 'bg-info';
                                    return `<span class="badge ${badge}">${escapeHtml(data)}</span>`;
                                }
                            },
                            { data: 'terminal' },
                            { data: 'fecha' },
                            {
                                data: 'estatus',
                                render: function (data) {
                                    return renderEstatusBadge(data);
                                }
                            },
                        ],
                        language: {
                            search: 'Buscar:',
                            lengthMenu: 'Mostrar _MENU_ registros',
                            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                            infoEmpty: 'Mostrando 0 a 0 de 0 registros',
                            emptyTable: 'No hay datos disponibles',
                            paginate: {
                                first: 'Primero',
                                last: 'Ultimo',
                                next: 'Siguiente',
                                previous: 'Anterior'
                            }
                        },
                        initComplete: () => {
                            resolveOnce();
                        }
                    });
                });
            }

            if (window.gestionAgenciasData?.tieneResultado) {
                dataTableReady
                    .then(esperarDataTableVisible)
                    .then(esperarPintadoNavegador)
                    .then(finalizarCargaReporte);
            }

            if (filtroEstatusVentas) {
                filtroEstatusVentas.addEventListener('change', () => {
                    if (dtGestionAgencias) {
                        dtGestionAgencias.ajax.reload(null, true);
                    }
                });
            }

            if (btnConfigurarTiempoVentas) {
                btnConfigurarTiempoVentas.addEventListener('click', abrirConfiguracionTiempoVentas);
            }

            if (btnPdfGestionAgencias) {
                btnPdfGestionAgencias.addEventListener('click', () => {
                    const umbrales = getUmbralesVentas();
                    const params = new URLSearchParams({
                        umbral_aviso: umbrales.aviso,
                        umbral_alerta: umbrales.alerta,
                        umbral_llamada: umbrales.llamada,
                    });

                    window.open(`${window.gestionAgenciasData.pdfUrl}?${params.toString()}`, '_blank');
                });
            }

            const descargarAgenciasSinVentaExcel = () => {
                if (!agenciasSinVentas.length) {
                    Swal.fire({ title: 'Sin datos', text: 'No hay agencias sin venta para descargar.', icon: 'info' });
                    return;
                }

                const filas = agenciasSinVentas.map((item) => {
                    const nombre = escapeHtml(item?.nombre_agencia ?? item?.agencia_id ?? 'SIN AGENCIA');
                    const terminal = escapeHtml(item?.terminal ?? item?.agencia_id ?? 'SIN TERMINAL');

                    return `<tr><td>${nombre}</td><td>${terminal}</td></tr>`;
                }).join('');

                const tablaHtml = `
                    <table>
                        <thead>
                            <tr>
                                <th>Agencia</th>
                                <th>Terminal</th>
                            </tr>
                        </thead>
                        <tbody>${filas}</tbody>
                    </table>
                `;

                const blob = new Blob(['\ufeff', tablaHtml], { type: 'application/vnd.ms-excel;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const enlace = document.createElement('a');
                enlace.href = url;
                enlace.download = 'agencias_sin_ventas_gestion.xls';
                document.body.appendChild(enlace);
                enlace.click();
                document.body.removeChild(enlace);
                URL.revokeObjectURL(url);
            };

            const abrirModalAgenciasSinVenta = () => {
                if (!agenciasSinVentas.length) {
                    Swal.fire({ title: 'Sin datos', text: 'No hay agencias sin venta para mostrar.', icon: 'info' });
                    return;
                }

                if (dtModalAgenciasSinVentaGestion) {
                    dtModalAgenciasSinVentaGestion.destroy();
                    dtModalAgenciasSinVentaGestion = null;
                }

                const tbody = document.querySelector('#tableModalAgenciasSinVentaGestion tbody');
                if (!tbody) return;

                tbody.innerHTML = '';

                agenciasSinVentas.forEach((item) => {
                    const nombre = (item?.nombre_agencia ?? item?.agencia_id ?? 'SIN AGENCIA').toString().trim() || 'SIN AGENCIA';
                    const terminal = (item?.terminal ?? item?.agencia_id ?? 'SIN TERMINAL').toString().trim() || 'SIN TERMINAL';
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${escapeHtml(nombre)}</td>
                        <td>${escapeHtml(terminal)}</td>
                    `;
                    tbody.appendChild(tr);
                });

                if (typeof $ === 'function' && $.fn?.DataTable) {
                    dtModalAgenciasSinVentaGestion = $('#tableModalAgenciasSinVentaGestion').DataTable({
                        destroy: true,
                        responsive: true,
                        language: {
                            url: '/json/es-DO.json',
                            search: 'Buscar:',
                            lengthMenu: 'Mostrar _MENU_ registros',
                            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                            paginate: { first: 'Primera', last: 'Ultima', next: 'Siguiente', previous: 'Anterior' }
                        },
                        order: [[0, 'asc']],
                    });
                }

                const modal = new bootstrap.Modal(document.getElementById('modalAgenciasSinVentaGestion'));
                modal.show();
            };

            const filasEstatusActual = () => {
                const filas = estatusDetalle?.[estatusModalActual];

                return Array.isArray(filas) ? filas : [];
            };

            const descargarEstatusTerminalesExcel = () => {
                const filas = filasEstatusActual();

                if (!filas.length) {
                    Swal.fire({ title: 'Sin datos', text: 'No hay agencias para descargar en este estatus.', icon: 'info' });
                    return;
                }

                const filasHtml = filas.map((item) => {
                    const agencia = escapeHtml(item?.agencia ?? 'SIN AGENCIA');
                    const terminal = escapeHtml(item?.terminal ?? 'SIN TERMINAL');
                    const tipo = escapeHtml(item?.tipo ?? 'N/D');
                    const fecha = escapeHtml(item?.fecha ?? 'N/D');

                    return `<tr><td>${agencia}</td><td>${terminal}</td><td>${tipo}</td><td>${fecha}</td></tr>`;
                }).join('');

                const tablaHtml = `
                    <table>
                        <thead>
                            <tr>
                                <th>Agencia</th>
                                <th>Terminal</th>
                                <th>Tipo</th>
                                <th>Ultima transaccion</th>
                            </tr>
                        </thead>
                        <tbody>${filasHtml}</tbody>
                    </table>
                `;

                const nombre = estatusModalActual.toLowerCase().replace(/\s+/g, '_');
                const blob = new Blob(['\ufeff', tablaHtml], { type: 'application/vnd.ms-excel;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const enlace = document.createElement('a');
                enlace.href = url;
                enlace.download = `agencias_${nombre}_gestion.xls`;
                document.body.appendChild(enlace);
                enlace.click();
                document.body.removeChild(enlace);
                URL.revokeObjectURL(url);
            };

            const abrirModalEstatusTerminales = (estatus) => {
                estatusModalActual = estatus;
                const filas = filasEstatusActual();

                if (!filas.length) {
                    Swal.fire({ title: 'Sin datos', text: `No hay agencias en ${estatus}.`, icon: 'info' });
                    return;
                }

                if (dtModalEstatusTerminalesGestion) {
                    dtModalEstatusTerminalesGestion.destroy();
                    dtModalEstatusTerminalesGestion = null;
                }

                const title = document.getElementById('modalEstatusTerminalesGestionLabel');
                const tbody = document.querySelector('#tableModalEstatusTerminalesGestion tbody');

                if (title) {
                    title.textContent = `Agencias en ${estatus}`;
                }

                if (!tbody) return;

                tbody.innerHTML = '';

                filas.forEach((item) => {
                    const agencia = (item?.agencia ?? 'SIN AGENCIA').toString().trim() || 'SIN AGENCIA';
                    const terminal = (item?.terminal ?? 'SIN TERMINAL').toString().trim() || 'SIN TERMINAL';
                    const tipo = (item?.tipo ?? 'N/D').toString().trim() || 'N/D';
                    const fecha = (item?.fecha ?? 'N/D').toString().trim() || 'N/D';
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${escapeHtml(agencia)}</td>
                        <td>${escapeHtml(terminal)}</td>
                        <td>${escapeHtml(tipo)}</td>
                        <td>${escapeHtml(fecha)}</td>
                    `;
                    tbody.appendChild(tr);
                });

                if (typeof $ === 'function' && $.fn?.DataTable) {
                    dtModalEstatusTerminalesGestion = $('#tableModalEstatusTerminalesGestion').DataTable({
                        destroy: true,
                        responsive: true,
                        language: {
                            url: '/json/es-DO.json',
                            search: 'Buscar:',
                            lengthMenu: 'Mostrar _MENU_ registros',
                            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                            paginate: { first: 'Primera', last: 'Ultima', next: 'Siguiente', previous: 'Anterior' }
                        },
                        order: [[3, 'desc']],
                    });
                }

                const modal = new bootstrap.Modal(document.getElementById('modalEstatusTerminalesGestion'));
                modal.show();
            };

            const cardAgenciasSinVenta = document.getElementById('cardAgenciasSinVentaGestion');
            const btnExcel = document.getElementById('btnDescargarAgenciasSinVentaGestionExcel');
            const btnExcelEstatus = document.getElementById('btnDescargarEstatusTerminalesGestionExcel');

            if (cardAgenciasSinVenta) {
                cardAgenciasSinVenta.addEventListener('click', abrirModalAgenciasSinVenta);
                cardAgenciasSinVenta.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter' && event.key !== ' ') return;
                    event.preventDefault();
                    abrirModalAgenciasSinVenta();
                });
            }

            if (btnExcel) {
                btnExcel.addEventListener('click', descargarAgenciasSinVentaExcel);
            }

            if (btnExcelEstatus) {
                btnExcelEstatus.addEventListener('click', descargarEstatusTerminalesExcel);
            }

            document.querySelectorAll('[data-card-estatus]').forEach((card) => {
                const abrir = () => abrirModalEstatusTerminales(card.dataset.cardEstatus);

                card.addEventListener('click', abrir);
                card.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter' && event.key !== ' ') return;
                    event.preventDefault();
                    abrir();
                });
            });

            iniciarHoraServidor();
            renderTendenciaVentasHora();
            bootConsultaAgencia();
        });
    </script>
@endsection
