@extends('app')

@section('content')
    <div class="main-content">

        <div class="page-content">
            <div class="container-fluid">

                <!-- start page title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Informe de Faltantes Lotobet</h4>

                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="javascript: void(0);">Reportes</a></li>
                                    <li class="breadcrumb-item active">Faltantes Lotobet</li>
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
                                <h5 class="card-title mb-0">Reporte de Faltantes por Cédula</h5>

                                <div class="d-flex gap-3 align-items-center justify-content-between flex-wrap">
                                    <div>
                                        <label class="mb-0" for="fecha_inicio">Desde</label>
                                        <input type="date" class="form-control" id="fecha_inicio">
                                    </div>

                                    <div>
                                        <label class="mb-0" for="fecha_fin">Hasta</label>
                                        <input type="date" class="form-control" id="fecha_fin">
                                    </div>

                                    <button id="btnFiltrar" class="btn btn-primary">
                                        Filtrar
                                    </button>

                                    <button id="btnExportarExcel" class="btn btn-success">
                                        Exportar Excel
                                    </button>

                                    <button id="btnExportarPdf" class="btn btn-danger">
                                        Exportar PDF
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive" style="width:100%; height:525px; max-height:525px; overflow-y:scroll;">
                                    <table id="tableFaltantes"
                                        class="table table-bordered dt-responsive nowrap table-striped align-middle"
                                        style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>Cédula</th>
                                                <th>Nombre Empleado</th>
                                                <th>Cantidad de Faltantes</th>
                                                <th>Monto Total</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                                <div class="d-flex flex-sm-fill d-sm-flex align-items-sm-center justify-content-sm-between mt-3">
                                    <div>
                                        <p class="small text-muted">
                                            Mostrando
                                            <span id="fromPage" class="fw-semibold">0</span>
                                            de
                                            <span id="toPage" class="fw-semibold">0</span>
                                            entradas. Total
                                            <span id="totalRegistros" class="fw-semibold">0</span>
                                            entradas.
                                        </p>
                                    </div>

                                    <div>
                                        <ul id="pagination" class="pagination"></ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!--end row-->
            </div>
            <!-- container-fluid -->
        </div>
        <!-- End Page-content -->

        <footer class="footer">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <script>
                            document.write(new Date().getFullYear())
                        </script> © CRM.
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <script>
        const urlBase = '/reportes-faltantes-bet';
        let currentPage = 1;

        // Cargar datos iniciales
        document.addEventListener('DOMContentLoaded', function() {
            cargarDatos(1);
        });

        function cargarDatos(page = 1) {
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;

            const params = new URLSearchParams({
                fecha_inicio: fechaInicio,
                fecha_fin: fechaFin,
                page: page
            });

            fetch(`${urlBase}/list?${params}`)
                .then(response => response.json())
                .then(data => {
                    mostrarDatos(data);
                    generarPaginacion(data);
                })
                .catch(error => console.error('Error:', error));
        }

        function mostrarDatos(data) {
            const tbody = document.querySelector('#tableFaltantes tbody');
            tbody.innerHTML = '';

            data.data.forEach(registro => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${registro.identificacion}</td>
                    <td>${registro.nombre_empleado || 'Sin especificar'}</td>
                    <td class="text-center">${registro.cantidad_faltantes}</td>
                    <td class="text-end">$${parseFloat(registro.total_monto).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                `;
                tbody.appendChild(row);
            });

            // Actualizar información de paginación
            document.getElementById('fromPage').textContent = (data.from || 0);
            document.getElementById('toPage').textContent = (data.to || 0);
            document.getElementById('totalRegistros').textContent = (data.total || 0);
        }

        function generarPaginacion(data) {
            const pagination = document.getElementById('pagination');
            pagination.innerHTML = '';

            if (data.prev_page_url) {
                const li = document.createElement('li');
                li.className = 'page-item';
                li.innerHTML = `<a class="page-link" href="#" onclick="cargarDatos(${data.current_page - 1}); return false;">Anterior</a>`;
                pagination.appendChild(li);
            }

            for (let i = 1; i <= data.last_page; i++) {
                const li = document.createElement('li');
                li.className = data.current_page === i ? 'page-item active' : 'page-item';
                li.innerHTML = `<a class="page-link" href="#" onclick="cargarDatos(${i}); return false;">${i}</a>`;
                pagination.appendChild(li);
            }

            if (data.next_page_url) {
                const li = document.createElement('li');
                li.className = 'page-item';
                li.innerHTML = `<a class="page-link" href="#" onclick="cargarDatos(${data.current_page + 1}); return false;">Siguiente</a>`;
                pagination.appendChild(li);
            }
        }

        document.getElementById('btnFiltrar').addEventListener('click', function() {
            cargarDatos(1);
        });

        document.getElementById('btnExportarExcel').addEventListener('click', function() {
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;

            const params = new URLSearchParams({
                fecha_inicio: fechaInicio,
                fecha_fin: fechaFin
            });

            window.location.href = `${urlBase}/excel?${params}`;
        });

        document.getElementById('btnExportarPdf').addEventListener('click', function() {
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;

            const params = new URLSearchParams({
                fecha_inicio: fechaInicio,
                fecha_fin: fechaFin
            });

            window.location.href = `${urlBase}/pdf?${params}`;
        });
    </script>
@endsection
