@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <!-- start page title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Cruce de Usuarios</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="javascript: void(0);">Reportes</a></li>
                                    <li class="breadcrumb-item active">Cruce de Usuarios</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end page title -->

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h5 class="card-title mb-0">Filtros</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-2">
                                        <label for="sistema" class="form-label">Sistema</label>
                                        <select id="sistema" class="form-control">
                                            <option value="Lotobet">Lotobet</option>
                                            <option value="Lotonet">Lotonet</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="estatus" class="form-label">Estatus</label>
                                        <select id="estatus" class="form-control">
                                            <option value="">Todos</option>
                                            <option value="No activo">No activo</option>
                                            <option value="No registrado">No registrado</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                                        <input type="date" id="fecha_inicio" class="form-control" value="{{ date('Y-m-01') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="fecha_fin" class="form-label">Fecha Fin</label>
                                        <input type="date" id="fecha_fin" class="form-control" value="{{ date('Y-m-d') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="button" class="btn btn-primary d-block" id="btnBuscar">
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
                                    <table id="tableCruceUsuarios"
                                        class="table table-bordered table-striped align-middle"
                                        style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>Identificación</th>
                                                <th>Empleado ID</th>
                                                <th>Nombre Completo</th>
                                                <th>Detalle</th>
                                                <th>Estatus</th>
                                                <th>Última Fecha Venta</th>
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
            const estatus = document.getElementById('estatus').value;
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
                table.destroy();
            }

            table = $('#tableCruceUsuarios').DataTable({
                ajax: {
                    url: '/reportes-cruce-usuarios/list',
                    type: 'GET',
                    data: {
                        sistema: sistema,
                        estatus: estatus,
                        fecha_inicio: fechaInicio,
                        fecha_fin: fechaFin
                    },
                    dataSrc: '',
                    complete: function() {
                        Swal.close();
                    }
                },
                columns: [
                    { data: 'Identificacion' },
                    { data: 'Empleado_ID' },
                    { data: 'NombreCompleto' },
                    { data: 'Detalle' },
                    { 
                        data: 'Estatus',
                        render: function(data, type, row) {
                            if (data === 'Activo') {
                                return '<span class="badge bg-success">' + data + '</span>';
                            } else if (data === 'No registrado') {
                                return '<span class="badge bg-warning">' + data + '</span>';
                            } else {
                                return '<span class="badge bg-danger">' + data + '</span>';
                            }
                        }
                    },
                    { data: 'Ultima_Fecha_Venta' }
                ],
                autoWidth: false,
                columnDefs: [
                    { width: "100px", targets: 0 },
                    { width: "80px", targets: 1 },
                    { width: "200px", targets: 2 },
                    { width: "auto", targets: 3 },
                    { width: "150px", targets: 4 },
                    { width: "120px", targets: 5 }
                ],
                paging: false,
                dom: 'Bfrtip',
                buttons: [
                    'copy', 'csv', 'excel', 'pdf', 'print'
                ],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                },
                order: [[5, 'desc']]
            });
        }

        document.getElementById('btnBuscar').addEventListener('click', cargarDatos);

        // Cargar datos automáticamente al inicio
        document.addEventListener('DOMContentLoaded', function() {
            // cargarDatos();
        });
    </script>
@endsection
