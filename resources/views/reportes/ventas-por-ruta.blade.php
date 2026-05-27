@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Ventas por ruta</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('reportes.index') }}">Reportes</a></li>
                                    <li class="breadcrumb-item active">Ventas por ruta</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h5 class="card-title mb-0">Filtros</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-2">
                                        <label for="sistema" class="form-label">Sistema</label>
                                        <select id="sistema" class="form-control">
                                            <option value="Lotobet">Lotobet</option>
                                            <option value="Lotonet">Lotonet</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="empresa" class="form-label">Empresa</label>
                                        <select id="empresa" class="form-control">
                                            <option value="todas">Todas</option>
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
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-primary d-block w-100" id="btnBuscar">
                                            <i class="ri-search-line"></i> Buscar
                                        </button>
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
                                <h5 class="card-title mb-0">Resultados</h5>
                            </div>
                            <div class="card-body">
                                <div style="overflow-x: auto;">
                                    <table id="tableVentasPorRuta"
                                        class="table table-bordered table-striped align-middle"
                                        style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>Ruta</th>
                                                <th>Tradicional</th>
                                                <th>No Tradicional</th>
                                                <th>Recarga</th>
                                                <th>Paquetico</th>
                                                <th>Total Dia</th>
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
        let table;

        function cargarDatos() {
            const sistema = document.getElementById('sistema').value;
            const empresa = document.getElementById('empresa').value;
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;

            if (!fechaInicio || !fechaFin) {
                Swal.fire({
                    title: 'Error',
                    text: 'Seleccione las fechas de inicio y fin',
                    icon: 'error'
                });
                return;
            }

            if (fechaInicio > fechaFin) {
                Swal.fire({
                    title: 'Error',
                    text: 'La fecha de inicio no puede ser mayor a la fecha fin',
                    icon: 'error'
                });
                return;
            }

            Swal.fire({
                title: 'Cargando datos...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            if (table) {
                table.clear().destroy();
                $('#tableVentasPorRuta tbody').empty();
            }

            table = $('#tableVentasPorRuta').DataTable({
                ajax: {
                    url: '/reportes-ventas-por-ruta/list',
                    type: 'GET',
                    data: {
                        sistema: sistema,
                        empresa: empresa,
                        fecha_inicio: fechaInicio,
                        fecha_fin: fechaFin
                    },
                    dataSrc: function(json) {
                        return json || [];
                    },
                    complete: function(jqXHR, textStatus) {
                        if (textStatus !== 'error') {
                            Swal.close();
                        }
                    },
                    error: function() {
                        Swal.fire({
                            title: 'Error',
                            text: 'No se pudieron cargar las ventas por ruta.',
                            icon: 'error'
                        });
                    }
                },
                columns: [
                    { data: 'Ruta' },
                    { data: 'Tradicional', className: 'text-end' },
                    { data: 'No_Tradicional', className: 'text-end' },
                    { data: 'Recarga', className: 'text-end' },
                    { data: 'Paquetico', className: 'text-end' },
                    { data: 'Total_Dia', className: 'text-end' }
                ],
                autoWidth: false,
                responsive: true,
                scrollX: true,
                columnDefs: [
                    { targets: [2, 3, 4], visible: $(window).width() > 768 }
                ],
                dom: 'Bfrtip',
                buttons: [
                    'copy', 'csv', 'excel', 'pdf', 'print'
                ],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                },
                order: [[0, 'asc']],
                paging: false,
                searching: true,
                info: false,
                createdRow: function(row, data) {
                    if (data.Ruta === 'TODAS') {
                        $(row).addClass('table-warning fw-bold');
                    }
                }
            });
        }

        document.getElementById('btnBuscar').addEventListener('click', cargarDatos);
    </script>
@endsection
