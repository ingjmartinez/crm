@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <style>
                    .btn-rojo-claro,
                    .btn-rojo-claro:hover,
                    .btn-rojo-claro:focus,
                    .btn-rojo-claro:active {
                        background-color: #fc0137 !important;
                        border-color: #fc0137 !important;
                        color: #fff !important;
                    }
                </style>
                <!-- start page title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Cuadre de Premios</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('reportes.index') }}">Reportes</a></li>
                                    <li class="breadcrumb-item active">Cuadre de Premios</li>
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
                                        <label class="form-label">&nbsp;</label>
                                        <button type="button" class="btn btn-primary d-block w-100" id="btnBuscar">
                                            <i class="ri-search-line"></i> Buscar
                                        </button>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="button" class="btn btn-success d-block w-100" id="btnDiasFaltantes" disabled>
                                            Días faltantes del rango
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
                                <p class="text-muted">Sin data indica que no hay registros en esa fuente para el día. Un monto de 0.00 sí tiene registros. La ausencia de registros no confirma un fallo del endpoint.</p><div style="overflow-x: auto;">
                                    <table id="tableCuadrePremios"
                                        class="table table-bordered table-striped align-middle"
                                        style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Premios sacados</th><th>Pagados a otra empresa</th><th>Pagados por otra empresa</th>
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
        let fechasFaltantesGlobal = [];
        const fuentesPremios = {
            premios_sacados: 'Premios sacados',
            pagados_a_otra_empresa: 'Pagados a otra empresa',
            pagados_por_otra_empresa: 'Pagados por otra empresa'
        };

        function formatoFecha(fecha) {
            return fecha.split('-').reverse().join('/');
        }

        function actualizarBotonDiasFaltantes(consultado = false) {
            const boton = document.getElementById('btnDiasFaltantes');
            boton.classList.remove('btn-success', 'btn-rojo-claro', 'btn-secondary');
            boton.disabled = !fechasFaltantesGlobal.length;
            boton.classList.add(!consultado ? 'btn-secondary' : (boton.disabled ? 'btn-success' : 'btn-rojo-claro'));
            boton.textContent = !consultado ? 'Días sin data por fuente' : (boton.disabled ? 'No faltan días del rango' : `Faltan datos en ${fechasFaltantesGlobal.length} días`);
        }

        function mostrarDiasFaltantes(event) {
            event.preventDefault();
            if (!fechasFaltantesGlobal.length) return;
            const lista = fechasFaltantesGlobal.map(fila => `<li><strong>${formatoFecha(fila.fecha)}</strong>: ${fila.fuentes.map(fuente => fuentesPremios[fuente]).join(', ')}</li>`).join('');
            Swal.fire({
                title: 'Fechas sin data por fuente',
                html: `<ul class="text-start mb-0 ps-4">${lista}</ul>`,
                icon: 'info',
                confirmButtonText: 'Cerrar',
                width: 700
            });
        }

        function mostrarMonto(valor, tipo) {
            if (valor === null) {
                return tipo === 'display' ? '<span class="badge bg-danger">Sin data</span>' : 'Sin data';
            }
            return tipo === 'display' || tipo === 'filter'
                ? Number(valor).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})
                : valor;
        }

        function cargarDatos() {
            const sistema = document.getElementById('sistema').value;
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            if (!fechaInicio || !fechaFin || fechaInicio > fechaFin) {
                Swal.fire({title: 'Error', text: 'Seleccione un rango de fechas válido.', icon: 'error'});
                return;
            }
            Swal.fire({title: 'Cargando datos...', allowOutsideClick: false, didOpen: () => Swal.showLoading()});
            if (table) {
                table.clear().destroy();
                $('#tableCuadrePremios tbody').empty();
            }
            fechasFaltantesGlobal = [];
            actualizarBotonDiasFaltantes();
            table = $('#tableCuadrePremios').DataTable({
                ajax: {
                    url: @json(route('reportes.cuadre-premios.list')),
                    type: 'GET',
                    data: {sistema: sistema, fecha_inicio: fechaInicio, fecha_fin: fechaFin},
                    dataSrc: function(json) {
                        fechasFaltantesGlobal = json.fechas_faltantes || [];
                        actualizarBotonDiasFaltantes(true);
                        return json.resultados || [];
                    },
                    complete: function(jqXHR, textStatus) {
                        if (textStatus === 'success') Swal.close();
                    },
                    error: function() {
                        fechasFaltantesGlobal = [];
                        actualizarBotonDiasFaltantes();
                        Swal.fire({title: 'Error', text: 'No se pudieron cargar los datos del cuadre de premios.', icon: 'error'});
                    }
                },
                columns: [
                    {data: 'fecha'},
                    {data: 'premios_sacados', className: 'text-end', render: mostrarMonto},
                    {data: 'pagados_a_otra_empresa', className: 'text-end', render: mostrarMonto},
                    {data: 'pagados_por_otra_empresa', className: 'text-end', render: mostrarMonto}
                ],
                autoWidth: false,
                responsive: true,
                scrollX: true,
                dom: 'Bfrtip',
                buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
                language: {url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'},
                order: [[0, 'asc']],
                paging: false,
                searching: false,
                info: false,
                createdRow: function(row, data) {
                    if (data.fecha === 'TOTAL') {
                        $(row).addClass('table-warning fw-bold');
                    } else if (Object.keys(fuentesPremios).some(campo => data[campo] === null)) {
                        $(row).addClass('table-danger');
                    }
                }
            });
        }

        document.getElementById('btnBuscar').addEventListener('click', cargarDatos);
        document.getElementById('btnDiasFaltantes').addEventListener('click', mostrarDiasFaltantes);
        actualizarBotonDiasFaltantes();
    </script>
@endsection