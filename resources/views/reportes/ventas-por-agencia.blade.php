@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <!-- start page title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Ventas por Agencia</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="javascript: void(0);">Reportes</a></li>
                                    <li class="breadcrumb-item active">Ventas por Agencia</li>
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
                                    <div class="col-md-3">
                                        <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                                        <input type="date" id="fecha_inicio" class="form-control" value="{{ date('Y-m-01') }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="fecha_fin" class="form-label">Fecha Fin</label>
                                        <input type="date" id="fecha_fin" class="form-control" value="{{ date('Y-m-d') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="periodo" class="form-label">Período</label>
                                        <select id="periodo" class="form-control">
                                            <option value="dia">Día</option>
                                            <option value="mes">Mes</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="terminal_codigo" class="form-label">Terminal</label>
                                        <input type="text" id="terminal_codigo" class="form-control" placeholder="Código">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="agencia_codigo" class="form-label">Agencia</label>
                                        <input type="text" id="agencia_codigo" class="form-control" readonly>
                                    </div>
                                    <div class="col-md-4 mt-2">
                                        <label for="agencia_nombre" class="form-label">Nombre Agencia</label>
                                        <input type="text" id="agencia_nombre" class="form-control" readonly>
                                    </div>
                                    <div class="col-md-2 mt-2">
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
                                    <table id="tableVentasPorAgencia"
                                        class="table table-bordered table-striped align-middle"
                                        style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>Terminal</th>
                                                <th>Coordinador</th>
                                                <th>Nombre Agencia</th>
                                                <th>Ruta</th>
                                                <th>Período</th>
                                                <th>Tradicional</th>
                                                <th>No Tradicional</th>
                                                <th>Recargas</th>
                                                <th>Paquetico</th>
                                                <th>Total</th>
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

        function buscarAgencia() {
            const codigo = document.getElementById('terminal_codigo').value.trim();
            const nombreInput = document.getElementById('agencia_nombre');
            const agenciaInput = document.getElementById('agencia_codigo');

            if (!codigo) {
                nombreInput.value = '';
                agenciaInput.value = '';
                return;
            }

            fetch(`/reportes-ventas-por-agencia/agencia?codigo=${encodeURIComponent(codigo)}`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.nombre_agencia) {
                        nombreInput.value = data.nombre_agencia;
                        agenciaInput.value = data.agencia || '';
                    } else {
                        nombreInput.value = '';
                        agenciaInput.value = '';
                        Swal.fire({
                            title: 'Agencia no encontrada',
                            text: 'Verifique el código de la agencia',
                            icon: 'warning'
                        });
                    }
                })
                .catch(() => {
                    nombreInput.value = '';
                    agenciaInput.value = '';
                });
        }

        function cargarDatos() {
            const sistema = document.getElementById('sistema').value;
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            const periodo = document.getElementById('periodo').value;
            const terminal = document.getElementById('terminal_codigo').value.trim();

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

            if (!terminal) {
                Swal.fire({
                    title: 'Error',
                    text: 'Ingrese el código de la terminal',
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

            table = $('#tableVentasPorAgencia').DataTable({
                ajax: {
                    url: '/reportes-ventas-por-agencia/list',
                    type: 'GET',
                    data: {
                        sistema: sistema,
                        fecha_inicio: fechaInicio,
                        fecha_fin: fechaFin,
                        periodo: periodo,
                        terminal: terminal
                    },
                    dataSrc: '',
                    complete: function() {
                        Swal.close();
                    }
                },
                columns: [
                    { data: 'terminal' },
                    { data: 'coordinador' },
                    { data: 'nombre_agencia' },
                    { data: 'ruta' },
                    { data: 'periodo' },
                    { data: 'tradicional', className: 'text-end' },
                    { data: 'no_tradicional', className: 'text-end' },
                    { data: 'recargas', className: 'text-end' },
                    { data: 'paquetico', className: 'text-end' },
                    { data: 'total', className: 'text-end' }
                ],
                autoWidth: false,
                dom: 'Bfrtip',
                buttons: [
                    'copy', 'csv', 'excel', 'pdf', 'print'
                ],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                },
                order: [[1, 'asc']],
                paging: false,
                searching: false,
                info: false
            });
        }

        document.getElementById('btnBuscar').addEventListener('click', cargarDatos);
        document.getElementById('terminal_codigo').addEventListener('change', buscarAgencia);
        document.getElementById('terminal_codigo').addEventListener('blur', buscarAgencia);

        document.addEventListener('DOMContentLoaded', function() {
            // cargarDatos();
        });
    </script>
@endsection
