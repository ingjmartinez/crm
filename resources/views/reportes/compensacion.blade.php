@extends('app')

@section('content')
    <style>
        .compensacion-metric-icon,
        .compensacion-metric-icon .avatar-title,
        .compensacion-metric-icon .avatar-title i {
            opacity: 1 !important;
            visibility: visible !important;
        }

        .compensacion-metric-icon .avatar-title {
            display: flex !important;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }

        .compensacion-metric-icon .avatar-title,
        .compensacion-metric-icon .avatar-title i {
            color: #fff !important;
        }

        .compensacion-metric-icon .avatar-title i {
            display: inline-block !important;
            font-size: 1.35rem;
            line-height: 1;
        }

        .compensacion-dia-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .08);
            overflow: hidden;
        }

        .compensacion-dia-header {
            align-items: center;
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 52%, #eef6ff 100%);
            border-bottom: 1px solid #edf2f7;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: space-between;
            padding: 18px 20px;
        }

        .compensacion-dia-title {
            color: #172033;
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: .4px;
            margin: 0;
            text-transform: uppercase;
        }

        .compensacion-dia-subtitle {
            color: #64748b;
            font-size: .84rem;
            margin: 4px 0 0;
        }

        .compensacion-dia-legend {
            display: flex;
            gap: 14px;
            color: #475569;
            font-size: .82rem;
            font-weight: 700;
        }

        .compensacion-dia-legend i {
            border-radius: 999px;
            display: inline-block;
            height: 10px;
            margin-right: 5px;
            width: 10px;
        }

        .compensacion-dia-body {
            padding: 18px 20px 20px;
        }

        .compensacion-dia-bars {
            display: grid;
            gap: 5px;
            min-width: 170px;
        }

        .compensacion-dia-track {
            background: #eef2f7;
            border-radius: 999px;
            height: 11px;
            overflow: hidden;
        }

        .compensacion-dia-fill {
            border-radius: inherit;
            display: block;
            height: 100%;
            min-width: 2px;
        }

        .compensacion-color-pao {
            background: linear-gradient(90deg, #60a5fa, #2563eb);
        }

        .compensacion-color-ppo {
            background: linear-gradient(90deg, #fb923c, #ea580c);
        }

        .compensacion-dia-empty {
            color: #64748b;
            font-weight: 700;
            padding: 36px 12px;
            text-align: center;
        }
    </style>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Compensacion</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('reportes.index') }}">Reportes</a></li>
                                    <li class="breadcrumb-item active">Compensacion</li>
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
                                    <div class="col-md-3">
                                        <label for="empresa" class="form-label">Empresa</label>
                                        <select id="empresa" class="form-control">
                                            <option value="todos">Todas</option>
                                            <option value="grupo_joselito">Grupo Joselito</option>
                                            <option value="negosur">Negosur</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                                        <input type="date" id="fecha_inicio" class="form-control" value="{{ date('Y-m-01') }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="fecha_fin" class="form-label">Fecha Fin</label>
                                        <input type="date" id="fecha_fin" class="form-control" value="{{ date('Y-m-d') }}">
                                    </div>
                                    <div class="col-md-3">
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
                                <div class="d-flex align-items-center justify-content-between gap-3">
                                    <div>
                                        <p class="text-uppercase fw-medium text-muted mb-1">Pagos a Consorcios</p>
                                        <h4 class="mb-0" id="totalPagosAConsorcios">0.00</h4>
                                    </div>
                                    <div class="avatar-sm flex-shrink-0 compensacion-metric-icon">
                                        <span class="avatar-title bg-success rounded fs-3">
                                            <i class="ri-hand-coin-line text-white"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between gap-3">
                                    <div>
                                        <p class="text-uppercase fw-medium text-muted mb-1">Pagos de Consorcios</p>
                                        <h4 class="mb-0" id="totalPagosDeConsorcios">0.00</h4>
                                    </div>
                                    <div class="avatar-sm flex-shrink-0 compensacion-metric-icon">
                                        <span class="avatar-title bg-info rounded fs-3">
                                            <i class="ri-exchange-dollar-line text-white"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between gap-3">
                                    <div>
                                        <p class="text-uppercase fw-medium text-muted mb-1">Resultado</p>
                                        <h4 class="mb-0" id="totalResta">0.00</h4>
                                    </div>
                                    <div class="avatar-sm flex-shrink-0 compensacion-metric-icon">
                                        <span class="avatar-title bg-danger rounded fs-3">
                                            <i class="ri-stack-line text-white"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between gap-3">
                                    <div>
                                        <p class="text-uppercase fw-medium text-muted mb-1">Resultado + 2%</p>
                                        <h4 class="mb-0" id="totalBeneficio">0.00</h4>
                                    </div>
                                    <div class="avatar-sm flex-shrink-0 compensacion-metric-icon">
                                        <span class="avatar-title bg-warning rounded fs-3">
                                            <i class="ri-percent-line text-white"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Resultados-Tradicional</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="tableCompensacion" class="table table-bordered table-striped align-middle" style="width: 100%;">
                                        <thead>
                                            <tr>
                                                <th>Consorcios</th>
                                                <th class="text-end">Pagos a Consorcios</th>
                                                <th class="text-end">Pagos de Consorcios</th>
                                                <th class="text-end">Resultado</th>
                                                <th class="text-end">Resultado + 2%</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-lg-12">
                        <div class="compensacion-dia-card">
                            <div class="compensacion-dia-header">
                                <div>
                                    <h5 class="compensacion-dia-title">Compensacion por dia</h5>
                                    <p class="compensacion-dia-subtitle mb-0">Primera fase del resumen visual debajo del reporte tradicional.</p>
                                </div>
                                <div class="compensacion-dia-legend">
                                    <span><i class="compensacion-color-pao"></i>Pagos a Consorcios</span>
                                    <span><i class="compensacion-color-ppo"></i>Pagos de Consorcios</span>
                                </div>
                            </div>
                            <div class="compensacion-dia-body">
                                <div class="table-responsive">
                                    <table id="tableCompensacionDia" class="table table-bordered table-hover align-middle mb-0" style="width: 100%;">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th class="text-end">Pagos a Consorcios</th>
                                                <th class="text-end">Pagos de Consorcios</th>
                                                <th class="text-end">Resultado</th>
                                                <th class="text-end">Resultado + 2%</th>
                                                <th>Visual</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td colspan="6">
                                                    <div class="compensacion-dia-empty">Consulta el reporte para generar la data por dia.</div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-lg-12">
                        <div class="compensacion-dia-card">
                            <div class="compensacion-dia-header">
                                <div>
                                    <h5 class="compensacion-dia-title">Compensacion por consorcio</h5>
                                    <p class="compensacion-dia-subtitle mb-0">Muestra visual comparando PAO y PPO por consorcio dentro del rango seleccionado.</p>
                                </div>
                                <div class="compensacion-dia-legend">
                                    <span><i class="compensacion-color-pao"></i>Pagos a Consorcios</span>
                                    <span><i class="compensacion-color-ppo"></i>Pagos de Consorcios</span>
                                </div>
                            </div>
                            <div class="compensacion-dia-body">
                                <div class="table-responsive">
                                    <table id="tableCompensacionDiaConsorcio" class="table table-bordered table-hover align-middle mb-0" style="width: 100%;">
                                        <thead>
                                            <tr>
                                                <th>Consorcio</th>
                                                <th class="text-end">PAO</th>
                                                <th class="text-end">PPO</th>
                                                <th class="text-end">Resultado</th>
                                                <th>Grafico</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td colspan="5">
                                                    <div class="compensacion-dia-empty">Consulta el reporte para generar la data por consorcio.</div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-lg-12">
                        <div class="compensacion-dia-card">
                            <div class="compensacion-dia-header">
                                <div>
                                    <h5 class="compensacion-dia-title">Top 10 rutas por compensacion</h5>
                                    <p class="compensacion-dia-subtitle mb-0">Rutas mas relevantes por volumen total PAO + PPO dentro del rango seleccionado.</p>
                                </div>
                                <div class="compensacion-dia-legend">
                                    <span><i class="compensacion-color-pao"></i>Pagos a Consorcios</span>
                                    <span><i class="compensacion-color-ppo"></i>Pagos de Consorcios</span>
                                </div>
                            </div>
                            <div class="compensacion-dia-body">
                                <div class="table-responsive">
                                    <table id="tableCompensacionTopRutas" class="table table-bordered table-hover align-middle mb-0" style="width: 100%;">
                                        <thead>
                                            <tr>
                                                <th>Ruta</th>
                                                <th class="text-end">PAO</th>
                                                <th class="text-end">PPO</th>
                                                <th class="text-end">Total</th>
                                                <th class="text-end">Resultado</th>
                                                <th>Grafico</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td colspan="6">
                                                    <div class="compensacion-dia-empty">Consulta el reporte para generar el top 10 de rutas.</div>
                                                </td>
                                            </tr>
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
@endsection

@section('script')
    <script>
        let tableCompensacion;
        let tableCompensacionDia;
        let tableCompensacionDiaConsorcio;
        let tableCompensacionTopRutas;

        function formatearNumero(valor) {
            return parseFloat(valor || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function actualizarResumen(resumen) {
            const pagosAConsorcios = parseFloat(resumen.aotra_bet || 0) + parseFloat(resumen.aotra_net || 0);
            const pagosDeConsorcios = parseFloat(resumen.porotra_bet || 0) + parseFloat(resumen.porotra_net || 0);
            const resta = pagosAConsorcios - pagosDeConsorcios;
            const beneficio = resta * 1.02;

            document.getElementById('totalPagosAConsorcios').textContent = formatearNumero(pagosAConsorcios);
            document.getElementById('totalPagosDeConsorcios').textContent = formatearNumero(pagosDeConsorcios);
            document.getElementById('totalResta').textContent = formatearNumero(resta);
            document.getElementById('totalBeneficio').textContent = formatearNumero(beneficio);
        }

        function renderTablaCompensacionDia(diario) {
            const data = Array.isArray(diario) ? diario : [];
            const maxValor = Math.max(
                1,
                ...data.map(item => Math.max(Number(item.pao || 0), Number(item.ppo || 0)))
            );

            if (tableCompensacionDia) {
                tableCompensacionDia.destroy();
                $('#tableCompensacionDia tbody').empty();
            }

            tableCompensacionDia = $('#tableCompensacionDia').DataTable({
                data: data,
                columns: [
                    { data: 'fecha' },
                    {
                        data: 'pao',
                        className: 'text-end',
                        render: function (data, type) {
                            const valor = Number(data || 0);
                            return type === 'display' ? formatearNumero(valor) : valor;
                        }
                    },
                    {
                        data: 'ppo',
                        className: 'text-end',
                        render: function (data, type) {
                            const valor = Number(data || 0);
                            return type === 'display' ? formatearNumero(valor) : valor;
                        }
                    },
                    {
                        data: null,
                        className: 'text-end',
                        render: function (data, type, row) {
                            const resultado = Number(row.pao || 0) - Number(row.ppo || 0);
                            return type === 'display' ? formatearNumero(resultado) : resultado;
                        }
                    },
                    {
                        data: null,
                        className: 'text-end',
                        render: function (data, type, row) {
                            const beneficio = (Number(row.pao || 0) - Number(row.ppo || 0)) * 1.02;
                            return type === 'display' ? formatearNumero(beneficio) : beneficio;
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return '';
                            }

                            const paoWidth = Math.max((Number(row.pao || 0) / maxValor) * 100, Number(row.pao || 0) > 0 ? 2 : 0);
                            const ppoWidth = Math.max((Number(row.ppo || 0) / maxValor) * 100, Number(row.ppo || 0) > 0 ? 2 : 0);

                            return `
                                <div class="compensacion-dia-bars">
                                    <div class="compensacion-dia-track">
                                        <span class="compensacion-dia-fill compensacion-color-pao" style="width:${paoWidth}%"></span>
                                    </div>
                                    <div class="compensacion-dia-track">
                                        <span class="compensacion-dia-fill compensacion-color-ppo" style="width:${ppoWidth}%"></span>
                                    </div>
                                </div>
                            `;
                        }
                    }
                ],
                autoWidth: false,
                dom: 'frtip',
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                },
                order: [[0, 'asc']],
                pageLength: 10,
                lengthMenu: [10, 25, 50]
            });
        }

        function renderGraficoPaoPpo(row, maxValor) {
            const pao = Number(row.pao || 0);
            const ppo = Number(row.ppo || 0);
            const paoWidth = Math.max((pao / maxValor) * 100, pao > 0 ? 2 : 0);
            const ppoWidth = Math.max((ppo / maxValor) * 100, ppo > 0 ? 2 : 0);

            return `
                <div class="compensacion-dia-bars">
                    <div class="compensacion-dia-track">
                        <span class="compensacion-dia-fill compensacion-color-pao" style="width:${paoWidth}%"></span>
                    </div>
                    <div class="compensacion-dia-track">
                        <span class="compensacion-dia-fill compensacion-color-ppo" style="width:${ppoWidth}%"></span>
                    </div>
                </div>
            `;
        }

        function renderTablaCompensacionDiaConsorcio(diarioConsorcio) {
            const data = Array.isArray(diarioConsorcio) ? diarioConsorcio : [];
            const maxValor = Math.max(
                1,
                ...data.map(item => Math.max(Number(item.pao || 0), Number(item.ppo || 0)))
            );

            if (tableCompensacionDiaConsorcio) {
                tableCompensacionDiaConsorcio.destroy();
                $('#tableCompensacionDiaConsorcio tbody').empty();
            }

            tableCompensacionDiaConsorcio = $('#tableCompensacionDiaConsorcio').DataTable({
                data: data,
                columns: [
                    { data: 'consorcios' },
                    {
                        data: 'pao',
                        className: 'text-end',
                        render: function (data, type) {
                            const valor = Number(data || 0);
                            return type === 'display' ? formatearNumero(valor) : valor;
                        }
                    },
                    {
                        data: 'ppo',
                        className: 'text-end',
                        render: function (data, type) {
                            const valor = Number(data || 0);
                            return type === 'display' ? formatearNumero(valor) : valor;
                        }
                    },
                    {
                        data: null,
                        className: 'text-end',
                        render: function (data, type, row) {
                            const resultado = Number(row.pao || 0) - Number(row.ppo || 0);
                            return type === 'display' ? formatearNumero(resultado) : resultado;
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            return type === 'display' ? renderGraficoPaoPpo(row, maxValor) : '';
                        }
                    }
                ],
                autoWidth: false,
                dom: 'frtip',
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                },
                order: [[1, 'desc']],
                pageLength: 10,
                lengthMenu: [10, 25, 50]
            });
        }

        function renderTablaTopRutas(topRutas) {
            const data = Array.isArray(topRutas) ? topRutas : [];
            const maxValor = Math.max(
                1,
                ...data.map(item => Math.max(Number(item.pao || 0), Number(item.ppo || 0)))
            );

            if (tableCompensacionTopRutas) {
                tableCompensacionTopRutas.destroy();
                $('#tableCompensacionTopRutas tbody').empty();
            }

            tableCompensacionTopRutas = $('#tableCompensacionTopRutas').DataTable({
                data: data,
                columns: [
                    { data: 'ruta' },
                    {
                        data: 'pao',
                        className: 'text-end',
                        render: function (data, type) {
                            const valor = Number(data || 0);
                            return type === 'display' ? formatearNumero(valor) : valor;
                        }
                    },
                    {
                        data: 'ppo',
                        className: 'text-end',
                        render: function (data, type) {
                            const valor = Number(data || 0);
                            return type === 'display' ? formatearNumero(valor) : valor;
                        }
                    },
                    {
                        data: null,
                        className: 'text-end',
                        render: function (data, type, row) {
                            const total = Number(row.pao || 0) + Number(row.ppo || 0);
                            return type === 'display' ? formatearNumero(total) : total;
                        }
                    },
                    {
                        data: null,
                        className: 'text-end',
                        render: function (data, type, row) {
                            const resultado = Number(row.pao || 0) - Number(row.ppo || 0);
                            return type === 'display' ? formatearNumero(resultado) : resultado;
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            return type === 'display' ? renderGraficoPaoPpo(row, maxValor) : '';
                        }
                    }
                ],
                autoWidth: false,
                dom: 'frtip',
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                },
                order: [[3, 'desc']],
                pageLength: 10,
                lengthMenu: [10]
            });
        }

        function cargarCompensacion() {
            const empresa = document.getElementById('empresa').value;
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;

            if (!fechaInicio || !fechaFin) {
                Swal.fire('Error', 'Seleccione la fecha de inicio y fin.', 'error');
                return;
            }

            if (fechaInicio > fechaFin) {
                Swal.fire('Error', 'La fecha de inicio no puede ser mayor que la fecha fin.', 'error');
                return;
            }

            Swal.fire({
                title: 'Consultando...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            if (tableCompensacion) {
                tableCompensacion.destroy();
            }

            tableCompensacion = $('#tableCompensacion').DataTable({
                ajax: {
                    url: '/reportes-compensacion/list',
                    type: 'GET',
                    data: {
                        empresa: empresa,
                        fecha_inicio: fechaInicio,
                        fecha_fin: fechaFin
                    },
                    dataSrc: function (json) {
                        actualizarResumen(json.resumen || {});
                        renderTablaCompensacionDia(json.visual?.diario || []);
                        renderTablaCompensacionDiaConsorcio(json.visual?.diario_consorcio || []);
                        renderTablaTopRutas(json.visual?.top_rutas || []);
                        return json.data || [];
                    },
                    complete: function () {
                        Swal.close();
                    },
                    error: function (xhr) {
                        Swal.close();
                        const message = xhr?.responseJSON?.message || 'No se pudo consultar la compensacion.';
                        Swal.fire('Error', message, 'error');
                    }
                },
                columns: [
                    { data: 'consorcios' },
                    {
                        data: null,
                        className: 'text-end',
                        render: function (data, type, row) {
                            const total = parseFloat(row.aotra_bet || 0) + parseFloat(row.aotra_net || 0);
                            return formatearNumero(total);
                        }
                    },
                    {
                        data: null,
                        className: 'text-end',
                        render: function (data, type, row) {
                            const total = parseFloat(row.porotra_bet || 0) + parseFloat(row.porotra_net || 0);
                            return formatearNumero(total);
                        }
                    },
                    {
                        data: null,
                        className: 'text-end',
                        render: function (data, type, row) {
                            const pagosAConsorcios = parseFloat(row.aotra_bet || 0) + parseFloat(row.aotra_net || 0);
                            const pagosDeConsorcios = parseFloat(row.porotra_bet || 0) + parseFloat(row.porotra_net || 0);
                            return formatearNumero(pagosAConsorcios - pagosDeConsorcios);
                        }
                    },
                    {
                        data: null,
                        className: 'text-end',
                        render: function (data, type, row) {
                            const pagosAConsorcios = parseFloat(row.aotra_bet || 0) + parseFloat(row.aotra_net || 0);
                            const pagosDeConsorcios = parseFloat(row.porotra_bet || 0) + parseFloat(row.porotra_net || 0);
                            const resta = pagosAConsorcios - pagosDeConsorcios;
                            return formatearNumero(resta * 1.02);
                        }
                    }
                ],
                autoWidth: false,
                dom: 'Bfrtip',
                buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                },
                order: [],
                pageLength: 25,
                lengthMenu: [25, 50, 100, 200],
                rowCallback: function (row, data) {
                    if (data.consorcios === 'TOTAL') {
                        row.classList.add('fw-bold');
                    }
                }
            });
        }

        document.getElementById('btnBuscar').addEventListener('click', cargarCompensacion);
    </script>
@endsection
