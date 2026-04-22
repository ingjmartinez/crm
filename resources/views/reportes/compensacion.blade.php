@extends('app')

@section('content')
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
                                        <label for="sistema" class="form-label">Sistema</label>
                                        <select id="sistema" class="form-control">
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
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <p class="text-uppercase fw-medium text-muted mb-1">Pagos a otra empresa</p>
                                <h4 class="mb-0" id="totalAotra">0.00</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <p class="text-uppercase fw-medium text-muted mb-1">Pagos por otra empresa</p>
                                <h4 class="mb-0" id="totalPorOtra">0.00</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <p class="text-uppercase fw-medium text-muted mb-1">Balance</p>
                                <h4 class="mb-0" id="totalBalance">0.00</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <p class="text-uppercase fw-medium text-muted mb-1">Registros</p>
                                <h4 class="mb-0" id="totalRegistros">0</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Resultados</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="tableCompensacion" class="table table-bordered table-striped align-middle" style="width: 100%;">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Sistema</th>
                                                <th>Movimiento</th>
                                                <th>Agencia</th>
                                                <th>Producto</th>
                                                <th>Descripcion</th>
                                                <th>Consorcio Origen</th>
                                                <th>Consorcio Destino</th>
                                                <th>Plataforma</th>
                                                <th class="text-end">Cantidad</th>
                                                <th class="text-end">Monto</th>
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
            document.getElementById('totalAotra').textContent = formatearNumero(resumen.total_a_otra_empresa);
            document.getElementById('totalPorOtra').textContent = formatearNumero(resumen.total_por_otra_empresa);
            document.getElementById('totalBalance').textContent = formatearNumero(resumen.balance);
            document.getElementById('totalRegistros').textContent = parseInt(resumen.registros || 0).toLocaleString('en-US');
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
                    { data: 'fecha' },
                    { data: 'sistema' },
                    { data: 'tipo_movimiento' },
                    { data: 'agencia_id' },
                    { data: 'producto_id' },
                    { data: 'descripcion' },
                    { data: 'consorcio_origen' },
                    { data: 'consorcio_destino' },
                    { data: 'plataforma' },
                    {
                        data: 'cantidad',
                        className: 'text-end',
                        render: function (data) {
                            return parseInt(data || 0).toLocaleString('en-US');
                        }
                    },
                    {
                        data: 'total_monto',
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
                order: [[0, 'desc'], [3, 'asc']],
                pageLength: 25,
                lengthMenu: [25, 50, 100, 200]
            });
        }

        document.getElementById('btnBuscar').addEventListener('click', cargarCompensacion);
        document.addEventListener('DOMContentLoaded', cargarCompensacion);
    </script>
@endsection
