@extends('app')

@section('content')
    <style>
        .employee-page {
            --employee-ink: #172033;
            --employee-muted: #64748b;
            --employee-border: #dfe7f1;
            --employee-blue: #405189;
            --employee-teal: #0f9d92;
            color: var(--employee-ink);
        }

        .employee-page .employee-panel {
            background: var(--vz-card-bg);
            border: 1px solid var(--employee-border);
            border-radius: 1rem;
            box-shadow: 0 8px 28px rgba(15, 23, 42, .055);
            overflow: hidden;
        }

        .employee-page .employee-hero {
            background: linear-gradient(120deg, #f7f9ff 0%, #eef5ff 58%, #eaf8f5 100%);
            border: 1px solid #dce8f5;
            border-radius: 1.1rem;
            overflow: hidden;
            position: relative;
        }

        .employee-page .employee-hero::after {
            background: radial-gradient(circle, rgba(64, 81, 137, .11), transparent 68%);
            content: '';
            height: 23rem;
            pointer-events: none;
            position: absolute;
            right: -7rem;
            top: -10rem;
            width: 23rem;
        }

        .employee-page .employee-eyebrow {
            color: var(--employee-blue);
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .11em;
            text-transform: uppercase;
        }

        .employee-page .employee-hero h1 {
            color: #1c2a49;
            font-size: clamp(1.7rem, 2.5vw, 2.55rem);
            font-weight: 750;
            letter-spacing: -.035em;
            line-height: 1.12;
        }

        .employee-page .employee-hero-copy {
            color: #566782;
            max-width: 38rem;
        }

        .employee-page .employee-filter-panel {
            background: rgba(255, 255, 255, .85);
            border: 1px solid #dce5f1;
            border-radius: .9rem;
            box-shadow: 0 10px 30px rgba(41, 67, 112, .07);
            position: relative;
            z-index: 1;
        }

        .employee-page .employee-section-label {
            color: var(--employee-blue);
            font-size: .7rem;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .employee-page .employee-section-title {
            color: var(--employee-ink);
            font-size: 1.18rem;
            font-weight: 700;
            margin: .15rem 0 0;
        }

        .employee-page .employee-kpi {
            border-top: 3px solid var(--employee-blue);
            min-height: 10.3rem;
        }

        .employee-page .employee-kpi.is-teal { border-top-color: #0f9d92; }
        .employee-page .employee-kpi.is-coral { border-top-color: #e98b74; }
        .employee-page .employee-kpi.is-gold { border-top-color: #e9ad50; }

        .employee-page .employee-kpi-label {
            color: var(--employee-muted);
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .employee-page .employee-kpi-value {
            color: var(--employee-ink);
            font-size: clamp(1.5rem, 2vw, 2rem);
            font-weight: 750;
            letter-spacing: -.04em;
            line-height: 1.2;
        }

        .employee-page .employee-icon {
            align-items: center;
            background: #eef2ff;
            border-radius: .8rem;
            color: var(--employee-blue);
            display: inline-flex;
            font-size: 1.35rem;
            height: 3rem;
            justify-content: center;
            width: 3rem;
        }

        .employee-page .employee-kpi.is-teal .employee-icon { background: #e4f7f2; color: #0d9488; }
        .employee-page .employee-kpi.is-coral .employee-icon { background: #fff0e9; color: #db765e; }
        .employee-page .employee-kpi.is-gold .employee-icon { background: #fff6e5; color: #c78621; }

        .employee-page .employee-panel-header {
            align-items: flex-start;
            border-bottom: 1px solid #e9eef5;
            display: flex;
            gap: .8rem;
            justify-content: space-between;
            padding: 1.3rem 1.4rem 0;
        }

        .employee-page .employee-panel-header h3 {
            color: var(--employee-ink);
            font-size: 1rem;
            font-weight: 700;
            margin: 0 0 .25rem;
        }

        .employee-page .employee-panel-header p {
            color: var(--employee-muted);
            font-size: .8rem;
            margin-bottom: 1rem;
        }

        .employee-page .employee-chart {
            min-height: 320px;
        }

        .employee-page .employee-table th {
            color: #52617a;
            font-size: .71rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .employee-page .employee-table td { vertical-align: middle; }

        .employee-page .employee-update-status {
            align-items: center;
            color: var(--employee-muted);
            display: inline-flex;
            font-size: .78rem;
            gap: .45rem;
        }

        .employee-page .employee-update-status::before {
            background: #0f9d92;
            border-radius: 50%;
            content: '';
            height: .45rem;
            width: .45rem;
        }

        html[data-layout-mode="dark"] .employee-page {
            --employee-ink: #e9eef7;
            --employee-muted: #a8b4c6;
            --employee-border: #344159;
        }

        html[data-layout-mode="dark"] .employee-page .employee-hero {
            background: linear-gradient(120deg, #202c42, #1d3443);
            border-color: #344159;
        }

        html[data-layout-mode="dark"] .employee-page .employee-filter-panel {
            background: rgba(29, 43, 61, .95);
            border-color: #344159;
        }

        html[data-layout-mode="dark"] .employee-page .employee-hero h1,
        html[data-layout-mode="dark"] .employee-page .employee-kpi-value,
        html[data-layout-mode="dark"] .employee-page .employee-panel-header h3 {
            color: var(--employee-ink);
        }

        html[data-layout-mode="dark"] .employee-page .employee-hero-copy,
        html[data-layout-mode="dark"] .employee-page .employee-panel-header p {
            color: var(--employee-muted);
        }

        @media (max-width: 575.98px) {
            .employee-page .employee-panel-header { padding: 1.1rem 1.1rem 0; }
            .employee-page .employee-chart { min-height: 280px; }
        }
    </style>
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid employee-page">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Recursos Humanos</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('recursos-humanos.index') }}">Recursos Humanos</a></li>
                                    <li class="breadcrumb-item active">Empleados</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12">
                        <div class="employee-hero">
                            <div class="p-4 p-lg-5 position-relative">
                                <div class="row align-items-center g-4">
                                    <div class="col-lg-7">
                                        <div class="employee-eyebrow mb-2">Personas · Recursos Humanos</div>
                                        <h1 class="mb-3">Panorama de empleados</h1>
                                        <p class="employee-hero-copy mb-3">
                                            Plantilla, actividad y masa salarial en una sola vista. Explora la distribución por ciudad y consulta la maestra de empleados.
                                        </p>
                                        <span class="employee-update-status" id="dashboardActualizado">Preparando indicadores</span>
                                    </div>
                                    <div class="col-lg-5">
                                        <div class="employee-filter-panel p-3 p-lg-4">
                                        <div class="employee-section-label mb-3">Filtros y acciones</div>
                                        <div class="row g-3">
                                            <div class="col-sm-6">
                                                <label class="form-label fw-semibold" for="empresa">Empresa</label>
                                                <select id="empresa" class="form-select">
                                                    <option value="">Todas</option>
                                                    <option value="168">168 = Grupo Joselito</option>
                                                    <option value="169">169 = Negosur</option>
                                                </select>
                                            </div>
                                            <div class="col-sm-6">
                                                <label class="form-label fw-semibold" for="cedulaSincronizar">Cédula para sincronizar</label>
                                                <input type="text" id="cedulaSincronizar" class="form-control"
                                                       inputmode="numeric" maxlength="13" placeholder="Opcional: 11 dígitos">
                                            </div>
                                            <div class="col-12">
                                                <div class="d-flex flex-wrap gap-2">
                                                    <button type="button" class="btn btn-primary fw-semibold flex-grow-1" id="btnRefrescarDashboard">
                                                        <i class="ri-refresh-line me-1"></i> Actualizar datos
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary fw-semibold flex-grow-1" id="btnSincronizar">
                                                        <i class="ri-download-cloud-2-line me-1"></i> Sincronizar
                                                    </button>
                                                    <a href="{{ route('empleados.ventas-bet-sin-maestra') }}" class="btn btn-soft-secondary fw-semibold w-100">
                                                        Revisar cédulas de ventas <i class="ri-arrow-right-up-line ms-1"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
                    <div>
                        <div class="employee-section-label">Indicadores clave</div>
                        <h2 class="employee-section-title">Plantilla y nómina</h2>
                    </div>
                    <span class="badge bg-primary-subtle text-primary" id="badgeEmpresaActual">Todas las empresas</span>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6 col-xl-3">
                        <div class="employee-panel employee-kpi h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <div class="employee-kpi-label mb-2">Empleados totales</div>
                                        <div class="employee-kpi-value mb-0" id="kpi-total-empleados">0</div>
                                    </div>
                                    <div>
                                        <span class="employee-icon">
                                            <i class="ri-team-line"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="text-muted small">Conteo total según empresa filtrada.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="employee-panel employee-kpi is-teal h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <div class="employee-kpi-label mb-2">Activos</div>
                                        <div class="employee-kpi-value mb-0" id="kpi-activos">0</div>
                                    </div>
                                    <div>
                                        <span class="employee-icon">
                                            <i class="ri-user-follow-line"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="text-muted small"><span id="kpi-tasa-actividad">0%</span> de la plantilla está activa.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="employee-panel employee-kpi is-coral h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <div class="employee-kpi-label mb-2">Inactivos</div>
                                        <div class="employee-kpi-value mb-0" id="kpi-inactivos">0</div>
                                    </div>
                                    <div>
                                        <span class="employee-icon">
                                            <i class="ri-user-unfollow-line"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="text-muted small">Fecha de salida con dato = empleado inactivo.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="employee-panel employee-kpi is-gold h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <div class="employee-kpi-label mb-2">Masa salarial mensual</div>
                                        <div class="employee-kpi-value mb-0" id="kpi-salario-total">0.00</div>
                                    </div>
                                    <div>
                                        <span class="employee-icon">
                                            <i class="ri-money-dollar-circle-line"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="text-muted small">Masa salarial mensual solo de usuarios activos.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="employee-section-label">Análisis visual</div>
                    <h2 class="employee-section-title">Distribución de la plantilla</h2>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-xl-4">
                        <div class="employee-panel h-100">
                            <div class="employee-panel-header">
                                <div><h3>Estado de empleados</h3><p>Activos e inactivos de la empresa seleccionada</p></div>
                                <span class="employee-icon"><i class="ri-pie-chart-2-line"></i></span>
                            </div>
                            <div class="card-body">
                                <div id="chartEstadoEmpleados" class="employee-chart"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-8">
                        <div class="employee-panel h-100">
                            <div class="employee-panel-header">
                                <div><h3>Masa salarial por ciudad</h3><p>Salario mensual de empleados activos · 10 ciudades principales</p></div>
                                <span class="employee-icon"><i class="ri-bar-chart-grouped-line"></i></span>
                            </div>
                            <div class="card-body">
                                <div id="chartSalarioCiudad" class="employee-chart"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-xl-6">
                        <div class="employee-panel h-100">
                            <div class="employee-panel-header">
                                <div><h3>Empleados por ciudad</h3><p>Volumen de personas en las ciudades principales</p></div>
                                <span class="employee-icon"><i class="ri-map-pin-user-line"></i></span>
                            </div>
                            <div class="card-body">
                                <div id="chartEmpleadosCiudad" class="employee-chart"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="employee-panel h-100">
                            <div class="employee-panel-header">
                                <div><h3>Participación salarial</h3><p>Distribución de la masa salarial activa por empresa</p></div>
                                <span class="employee-icon"><i class="ri-donut-chart-line"></i></span>
                            </div>
                            <div class="card-body">
                                <div id="chartSalarioEmpresa" class="employee-chart"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="employee-section-label">Detalle operativo</div>
                    <h2 class="employee-section-title">Ciudades y maestra de empleados</h2>
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        <div class="employee-panel h-100">
                            <div class="employee-panel-header">
                                <div><h3>Ciudades principales</h3><p>Empleados, activos y masa salarial mensual</p></div>
                                <span class="employee-icon"><i class="ri-map-2-line"></i></span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table employee-table table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Ciudad</th>
                                                <th class="text-center">Empleados</th>
                                                <th class="text-center">Activos</th>
                                                <th class="text-end">Salario</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyResumenCiudad"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="employee-panel">
                            <div class="employee-panel-header">
                                <div><h3>Directorio de empleados</h3><p>Busca, ordena y exporta el listado según la empresa seleccionada</p></div>
                                <span class="employee-icon"><i class="ri-team-line"></i></span>
                            </div>
                            <div class="card-body">
                                <table id="tableEmpleados" class="table employee-table table-hover dt-responsive nowrap align-middle" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Empresa</th>
                                            <th>Id Empleado</th>
                                            <th>Nombres</th>
                                            <th>Apellidos</th>
                                            <th>Cedula</th>
                                            <th>Ciudad</th>
                                            <th>Departamento</th>
                                            <th>Salario Mensual</th>
                                            <th>Fecha Ingreso</th>
                                            <th>Fecha Salida</th>
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

        <footer class="footer">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <script>
                            document.write(new Date().getFullYear())
                        </script> © Velzon.
                    </div>
                    <div class="col-sm-6">
                        <div class="text-sm-end d-none d-sm-block">
                            Design & Develop by Themesbrand
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </div>
@endsection

@section('script')
    <script src="{{ asset('libs/apexcharts/apexcharts.min.js') }}"></script>
    <script>
        let chartEstado = null;
        let chartSalarioCiudad = null;
        let chartEmpleadosCiudad = null;
        let chartSalarioEmpresa = null;
        let empleadosTable = null;
        let dashboardRequestId = 0;
        const empleadosExportUrl = @json(route('empleados.export'));

        function formatoMonto(valor) {
            return Number(valor || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        }

        function empresaTexto(valor) {
            if (String(valor) === '168') return 'Grupo Joselito';
            if (String(valor) === '169') return 'Negosur';
            return 'Todas las empresas';
        }

        function escaparHtml(valor) {
            return String(valor ?? '').replace(/[&<>"']/g, caracter => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
            })[caracter]);
        }

        function obtenerEmpresaActual() {
            return document.getElementById('empresa').value || '';
        }

        function destruirChart(instancia) {
            if (instancia && typeof instancia.destroy === 'function') {
                instancia.destroy();
            }
        }

        async function parsearRespuestaJson(response, contextoError) {
            const contentType = (response.headers.get('content-type') || '').toLowerCase();
            const cuerpo = await response.text();
            let payload = null;

            if (cuerpo) {
                try {
                    payload = JSON.parse(cuerpo);
                } catch (_errorParse) {
                    payload = null;
                }
            }

            if (!response.ok) {
                const mensajeServidor = payload?.message || payload?.error || '';
                const mensajeNoJson = !contentType.includes('application/json')
                    ? 'El servidor devolvio HTML/no JSON. Revisa storage/logs/laravel.log.'
                    : '';
                const detalle = [mensajeServidor, mensajeNoJson].filter(Boolean).join(' | ');
                throw new Error(contextoError + ' (HTTP ' + response.status + ')' + (detalle ? ': ' + detalle : ''));
            }

            if (!payload) {
                throw new Error(contextoError + ': Respuesta no valida en formato JSON.');
            }

            return payload;
        }

        function renderCharts(payload) {
            const estado = payload?.charts?.estado || { labels: [], series: [] };
            const salarioCiudad = payload?.charts?.salario_ciudad || { labels: [], series: [] };
            const empleadosCiudad = payload?.charts?.empleados_ciudad || { labels: [], series: [] };
            const salarioEmpresa = payload?.charts?.salario_empresa || { labels: [], series: [] };
            const chartFont = 'inherit';
            const chartText = document.documentElement.getAttribute('data-layout-mode') === 'dark' ? '#b5c1d2' : '#64748b';
            const noData = { text: 'No hay datos para la selección actual' };

            destruirChart(chartEstado);
            destruirChart(chartSalarioCiudad);
            destruirChart(chartEmpleadosCiudad);
            destruirChart(chartSalarioEmpresa);

            chartEstado = new ApexCharts(document.querySelector('#chartEstadoEmpleados'), {
                chart: { type: 'donut', height: 320, fontFamily: chartFont, toolbar: { show: false } },
                series: estado.series || [],
                labels: estado.labels || [],
                colors: ['#0f9d92', '#e98b74'],
                legend: { position: 'bottom', fontSize: '13px', labels: { colors: chartText } },
                dataLabels: { enabled: false },
                stroke: { width: 3, colors: ['#ffffff'] },
                noData,
                plotOptions: { pie: { donut: { size: '72%', labels: {
                    show: true,
                    name: { show: true, color: chartText },
                    value: { show: true, fontSize: '24px', fontWeight: 700, formatter: value => Number(value || 0).toLocaleString('en-US') },
                    total: { show: true, label: 'Empleados', formatter: () => Number(payload?.resumen?.total_empleados || 0).toLocaleString('en-US') }
                } } } }
            });

            chartSalarioCiudad = new ApexCharts(document.querySelector('#chartSalarioCiudad'), {
                chart: { type: 'bar', height: 320, fontFamily: chartFont, toolbar: { show: false } },
                series: [{ name: 'Salario mensual', data: salarioCiudad.series || [] }],
                colors: ['#405189'],
                plotOptions: { bar: { borderRadius: 5, horizontal: true, barHeight: '55%', distributed: false } },
                dataLabels: { enabled: false },
                grid: { borderColor: '#e9eef5', strokeDashArray: 4 },
                noData,
                xaxis: { categories: salarioCiudad.labels || [], labels: { formatter: value => '$' + Number(value || 0).toLocaleString('en-US', { notation: 'compact' }) } },
                tooltip: {
                    y: {
                        formatter: function (value) {
                            return '$' + formatoMonto(value);
                        }
                    }
                }
            });

            chartEmpleadosCiudad = new ApexCharts(document.querySelector('#chartEmpleadosCiudad'), {
                chart: { type: 'bar', height: 320, fontFamily: chartFont, toolbar: { show: false } },
                series: [{ name: 'Empleados', data: empleadosCiudad.series || [] }],
                xaxis: { categories: empleadosCiudad.labels || [] },
                colors: ['#0f9d92'],
                plotOptions: { bar: { borderRadius: 5, horizontal: true, barHeight: '55%' } },
                grid: { borderColor: '#e9eef5', strokeDashArray: 4 },
                dataLabels: { enabled: false },
                noData
            });

            const salarioEmpresaSeries = salarioEmpresa.series || [];
            const salarioEmpresaTotal = salarioEmpresaSeries.reduce(function (acc, item) {
                return acc + Number(item || 0);
            }, 0);
            const salarioEmpresaPorcentaje = salarioEmpresaSeries.map(function (value) {
                if (salarioEmpresaTotal <= 0) {
                    return 0;
                }

                return Number(((Number(value || 0) / salarioEmpresaTotal) * 100).toFixed(2));
            });

            chartSalarioEmpresa = new ApexCharts(document.querySelector('#chartSalarioEmpresa'), {
                chart: { type: 'donut', height: 320, fontFamily: chartFont, toolbar: { show: false } },
                series: salarioEmpresaPorcentaje,
                labels: salarioEmpresa.labels || [],
                colors: ['#405189', '#e9ad50'],
                stroke: { width: 3, colors: ['#ffffff'] },
                noData,
                legend: {
                    show: true,
                    position: 'bottom',
                    formatter: function (seriesName, opts) {
                        const porcentaje = salarioEmpresaPorcentaje[opts.seriesIndex] || 0;
                        const monto = salarioEmpresaSeries[opts.seriesIndex] || 0;
                        return seriesName + ' - ' + porcentaje.toFixed(2) + '% ($' + formatoMonto(monto) + ')';
                    }
                },
                dataLabels: {
                    enabled: true,
                    formatter: function (value) {
                        return value.toFixed(1) + '%';
                    }
                },
                tooltip: {
                    y: {
                        formatter: function (_value, opts) {
                            const indice = opts.seriesIndex;
                            const porcentaje = salarioEmpresaPorcentaje[indice] || 0;
                            const monto = salarioEmpresaSeries[indice] || 0;
                            return porcentaje.toFixed(2) + '% | $' + formatoMonto(monto);
                        }
                    }
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '70%',
                            labels: {
                                show: true,
                                name: {
                                    show: true,
                                    fontSize: '16px'
                                },
                                value: {
                                    show: true,
                                    fontSize: '18px',
                                    formatter: function (value) {
                                        return Number(value || 0).toFixed(2) + '%';
                                    }
                                },
                                total: {
                                    show: true,
                                    label: 'Total',
                                    formatter: function () {
                                        return '$' + formatoMonto(salarioEmpresaTotal);
                                    }
                                }
                            }
                        }
                    }
                }
            });

            chartEstado.render();
            chartSalarioCiudad.render();
            chartEmpleadosCiudad.render();
            chartSalarioEmpresa.render();
        }

        function renderResumen(payload) {
            const resumen = payload?.resumen || {};
            const total = Number(resumen.total_empleados || 0);
            const activos = Number(resumen.activos || 0);
            document.getElementById('kpi-total-empleados').textContent = total.toLocaleString('en-US');
            document.getElementById('kpi-activos').textContent = activos.toLocaleString('en-US');
            document.getElementById('kpi-inactivos').textContent = Number(resumen.inactivos || 0).toLocaleString('en-US');
            document.getElementById('kpi-salario-total').textContent = '$' + formatoMonto(resumen.salario_mensual_activos || 0);
            document.getElementById('kpi-tasa-actividad').textContent = total > 0 ? ((activos / total) * 100).toFixed(1) + '%' : '0%';
        }

        function renderTablaCiudades(payload) {
            const tbody = document.getElementById('tbodyResumenCiudad');
            const filas = Array.isArray(payload?.detalle_ciudad) ? payload.detalle_ciudad : [];

            tbody.innerHTML = '';
            if (!filas.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No hay ciudades para este filtro.</td></tr>';
                return;
            }
            filas.forEach(function (fila) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><div class="fw-semibold">${escaparHtml(fila.ciudad || 'Sin ciudad')}</div></td>
                    <td class="text-center">${Number(fila.empleados || 0).toLocaleString('en-US')}</td>
                    <td class="text-center">${Number(fila.activos || 0).toLocaleString('en-US')}</td>
                    <td class="text-end fw-semibold">$${formatoMonto(fila.salario || 0)}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        function actualizarBadgeEmpresa() {
            document.getElementById('badgeEmpresaActual').textContent = empresaTexto(obtenerEmpresaActual());
        }

        function cargarDashboard(refresh = false) {
            const requestId = ++dashboardRequestId;
            const empresa = obtenerEmpresaActual();
            actualizarBadgeEmpresa();
            document.getElementById('dashboardActualizado').textContent = 'Actualizando indicadores';
            const params = new URLSearchParams({ empresa });

            if (refresh) {
                params.set('refresh', '1');
            }

            return fetch('/empleados/dashboard?' + params.toString(), {
                headers: {
                    'Accept': 'application/json',
                },
            })
                .then(response => parsearRespuestaJson(response, 'Error al cargar dashboard de empleados'))
                .then(payload => {
                    if (requestId !== dashboardRequestId) return;
                    renderResumen(payload);
                    renderCharts(payload);
                    renderTablaCiudades(payload);
                    document.getElementById('dashboardActualizado').textContent =
                        'Actualizado a las ' + new Date().toLocaleTimeString('es-DO', { hour: '2-digit', minute: '2-digit' });
                })
                .catch(error => {
                    if (requestId !== dashboardRequestId) return;
                    console.error('Error dashboard empleados:', error);
                    document.getElementById('dashboardActualizado').textContent = 'No se pudieron actualizar los indicadores';
                    Swal.fire('Error', 'No se pudo cargar el dashboard de Recursos Humanos.', 'error');
                });
        }

        function list() {
            if (!empleadosTable) {
                empleadosTable = $('#tableEmpleados').DataTable({
                    processing: true,
                    serverSide: true,
                    deferRender: true,
                    responsive: true,
                    scrollX: true,
                    searchDelay: 450,
                    pageLength: 10,
                    dom: 'Bfrtip',
                    buttons: [
                        'copy',
                        'csv',
                        {
                            text: '<i class="ri-file-excel-2-line me-1"></i>Excel completo',
                            titleAttr: 'Descargar todos los empleados que coinciden con el filtro actual',
                            action: function () {
                                const params = new URLSearchParams({
                                    empresa: obtenerEmpresaActual(),
                                    buscar: empleadosTable ? empleadosTable.search().trim() : '',
                                });

                                window.location.assign(empleadosExportUrl + '?' + params.toString());
                            }
                        },
                        'pdf',
                        'print'
                    ],
                    ajax: {
                        url: '/empleados/list',
                        data: function (data) {
                            data.empresa = obtenerEmpresaActual();
                        },
                        error: function (xhr) {
                            console.error('Error fetching empleados:', xhr);
                            Swal.fire('Error', 'No se pudieron cargar los empleados.', 'error');
                        }
                    },
                    columns: [
                        { data: 'company', defaultContent: '-' },
                        { data: 'empleadoid', defaultContent: '-' },
                        { data: 'nombres', defaultContent: '-' },
                        { data: 'apellidos', defaultContent: '-' },
                        { data: 'cedula', defaultContent: '' },
                        { data: 'ciudad', defaultContent: '' },
                        { data: 'depto', defaultContent: '' },
                        {
                            data: 'salariomensual',
                            className: 'text-end',
                            render: function (data) {
                                return '$' + formatoMonto(data || 0);
                            }
                        },
                        { data: 'fechaingreso', defaultContent: '' },
                        {
                            data: 'fechasalida',
                            render: function (data) {
                                return !data
                                    ? '<span class="badge bg-success-subtle text-success">Activo</span>'
                                    : '<span class="badge bg-danger-subtle text-danger">' + data + '</span>';
                            }
                        }
                    ]
                });

                return Promise.resolve();
            }

            return new Promise(function (resolve) {
                empleadosTable.one('draw', resolve);
                empleadosTable.ajax.reload(null, true);
            });
        }

        document.querySelector("#btnSincronizar").addEventListener("click", async function () {
            const btnSincronizar = this;
            const empresa = document.getElementById('empresa').value;
            const cedula = document.getElementById('cedulaSincronizar').value.replace(/\D/g, '');

            if (!empresa) {
                Swal.fire({
                    title: 'Empresa requerida',
                    text: 'Debe seleccionar una empresa antes de sincronizar empleados.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return;
            }

            if (cedula && cedula.length !== 11) {
                Swal.fire({
                    title: 'Cédula inválida',
                    text: 'La cédula debe contener 11 dígitos.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return;
            }

            const seleccionLimite = await Swal.fire({
                title: 'Cantidad de registros',
                text: '¿Cuántos empleados deseas solicitar al proveedor?',
                input: 'number',
                inputValue: cedula ? 1 : 10000,
                inputAttributes: { min: 1, max: 10000, step: 1 },
                showCancelButton: true,
                confirmButtonText: 'Sincronizar',
                cancelButtonText: 'Cancelar',
                inputValidator: (valor) => {
                    const limite = Number(valor);
                    if (!Number.isInteger(limite) || limite < 1 || limite > 10000) {
                        return 'Escribe un número entero entre 1 y 10,000.';
                    }
                }
            });

            if (!seleccionLimite.isConfirmed) {
                return;
            }

            const limite = Number(seleccionLimite.value);

            btnSincronizar.disabled = true;

            Swal.fire({
                title: "Sincronizando: 0% ...",
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                timerProgressBar: true,
                didOpen: () => Swal.showLoading()
            });

            let textSwal = document.querySelector('#swal2-title');
            let elapsed = 0;
            const duration = 600;
            const interval = setInterval(() => {
                elapsed += 1;
                let percent = Math.min(Math.round((elapsed / duration) * 90), 90);
                if (textSwal) {
                    textSwal.innerHTML = "Sincronizando: " + percent + "%";
                }
            }, 1000);

            const params = new URLSearchParams({ empresa, limite: String(limite) });
            if (cedula) {
                params.set('cedula', cedula);
            }

            fetch('/empleados/sincronizar?' + params.toString(), {
                headers: {
                    'Accept': 'application/json',
                },
            })
                .then(response => parsearRespuestaJson(response, 'Error durante la sincronizacion de empleados'))
                .then(() => {
                    if (textSwal) {
                        textSwal.innerHTML = "Sincronizando: 100%";
                    }
                    clearInterval(interval);
                    return Swal.fire({
                        title: "Listo",
                        text: "Sincronización completada con éxito",
                        icon: "success"
                    }).then(() => {
                        return cargarDashboard(true).finally(list);
                    });
                })
                .catch(error => {
                    if (textSwal) {
                        textSwal.innerHTML = "Sincronizando: 100%";
                    }
                    clearInterval(interval);
                    Swal.fire({
                        title: "Error",
                        text: error?.message || 'No fue posible sincronizar empleados.',
                        icon: "warning"
                    });
                })
                .finally(() => {
                    btnSincronizar.disabled = false;
                });
        });

        document.getElementById('empresa').addEventListener('change', function () {
            cargarDashboard().finally(list);
        });

        document.getElementById('btnRefrescarDashboard').addEventListener('click', function () {
            cargarDashboard(true).finally(list);
        });

        document.addEventListener('DOMContentLoaded', function () {
            cargarDashboard().finally(list);
        });
    </script>
@endsection
