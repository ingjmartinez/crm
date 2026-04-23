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
                                        <label for="sistema" class="form-label">Empresa</label>
                                        <select id="sistema" class="form-control">
                                            <option value="todos">Todas</option>
                                            <option value="lotobet">Lotobet</option>
                                            <option value="lotonet">Lotonet</option>
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
                    <div class="col-xl col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between gap-3">
                                    <div>
                                        <p class="text-uppercase fw-medium text-muted mb-1">Pagos a otra empresa Lotobet</p>
                                        <h4 class="mb-0" id="totalAotraBet">0.00</h4>
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
                    <div class="col-xl col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between gap-3">
                                    <div>
                                        <p class="text-uppercase fw-medium text-muted mb-1">Pagos a otra empresa Lotonet</p>
                                        <h4 class="mb-0" id="totalAotraNet">0.00</h4>
                                    </div>
                                    <div class="avatar-sm flex-shrink-0 compensacion-metric-icon">
                                        <span class="avatar-title bg-primary rounded fs-3">
                                            <i class="ri-refund-2-line text-white"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between gap-3">
                                    <div>
                                        <p class="text-uppercase fw-medium text-muted mb-1">Pagos por otra empresa Lotobet</p>
                                        <h4 class="mb-0" id="totalPorotraBet">0.00</h4>
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
                    <div class="col-xl col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between gap-3">
                                    <div>
                                        <p class="text-uppercase fw-medium text-muted mb-1">Pagos por otra empresa Lotonet</p>
                                        <h4 class="mb-0" id="totalPorotraNet">0.00</h4>
                                    </div>
                                    <div class="avatar-sm flex-shrink-0 compensacion-metric-icon">
                                        <span class="avatar-title bg-warning rounded fs-3">
                                            <i class="ri-money-dollar-box-line text-white"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between gap-3">
                                    <div>
                                        <p class="text-uppercase fw-medium text-muted mb-1">Total General</p>
                                        <h4 class="mb-0" id="totalGeneral">0.00</h4>
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
                                                <th class="text-end">Pagos a otra empresa Lotobet</th>
                                                <th class="text-end">Pagos a otra empresa Lotonet</th>
                                                <th class="text-end">Pagos por otra empresa Lotobet</th>
                                                <th class="text-end">Pagos por otra empresa Lotonet</th>
                                                <th class="text-end">Total General</th>
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
@endsection

@section('script')
    <script>
        let tableCompensacion;

        function formatearNumero(valor) {
            return parseFloat(valor || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function actualizarResumen(resumen) {
            document.getElementById('totalAotraBet').textContent = formatearNumero(resumen.aotra_bet);
            document.getElementById('totalAotraNet').textContent = formatearNumero(resumen.aotra_net);
            document.getElementById('totalPorotraBet').textContent = formatearNumero(resumen.porotra_bet);
            document.getElementById('totalPorotraNet').textContent = formatearNumero(resumen.porotra_net);
            document.getElementById('totalGeneral').textContent = formatearNumero(resumen.total_general);
        }

        function cargarCompensacion() {
            const sistema = document.getElementById('sistema').value;
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
                        sistema: sistema,
                        fecha_inicio: fechaInicio,
                        fecha_fin: fechaFin
                    },
                    dataSrc: function (json) {
                        actualizarResumen(json.resumen || {});
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
                        data: 'aotra_bet',
                        className: 'text-end',
                        render: function (data) {
                            return formatearNumero(data);
                        }
                    },
                    {
                        data: 'aotra_net',
                        className: 'text-end',
                        render: function (data) {
                            return formatearNumero(data);
                        }
                    },
                    {
                        data: 'porotra_bet',
                        className: 'text-end',
                        render: function (data) {
                            return formatearNumero(data);
                        }
                    },
                    {
                        data: 'porotra_net',
                        className: 'text-end',
                        render: function (data) {
                            return formatearNumero(data);
                        }
                    },
                    {
                        data: 'total_general',
                        className: 'text-end',
                        render: function (data) {
                            return formatearNumero(data);
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
