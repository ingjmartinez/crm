@extends('app')

@section('content')
    <div class="main-content">

        <div class="page-content">
            <div class="container-fluid">

                <!-- start page title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Empleados</h4>

                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="javascript: void(0);">Tables</a></li>
                                    <li class="breadcrumb-item active">Datatables</li>
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
                                <h5 class="card-title mb-0">Empleados</h5>

                                <div class="d-flex gap-3 align-items-center justify-content-between">
                                    <div><label class="mb-0" for="empresa">Empresa ID</label></div>
                                    <div>
                                        <select id="empresa" class="form-select">
                                            <option value="168">168</option>
                                            <option value="169">169</option>
                                        </select>
                                    </div>

                                    <button type="button" class="btn btn-primary" id="btnSincronizar">
                                        Sincronizar Empleados
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <table id="tableEmpleados"
                                    class="table table-bordered dt-responsive nowrap table-striped align-middle"
                                    style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Empresa</th>
                                            <th>Id Empleado</th>
                                            <th>Nombres</th>
                                            <th>Apellidos</th>
                                            <th>Cedula</th>
                                            <th>Fecha Ingreso</th>
                                            <th>Fecha Salida</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
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
    <!-- end main content-->


    <div id="myModal" class="modal fade" tabindex="-1" aria-labelledby="myModalLabel" aria-hidden="true"
        style="display: none;">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="myModalLabel">Formulario</h5>
                    <button type="button" id="btnClose" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="codigo" readonly>
                    <div class="mb-3">
                        <label for="empleadoid" class="form-label">Id Empleado</label>
                        <input type="text" class="form-control" id="empleadoid">
                    </div>
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="nombre">
                    </div>
                    <div class="mb-3">
                        <label for="cedula" class="form-label">Cedula</label>
                        <input type="text" class="form-control" id="cedula">
                    </div>
                    <div class="mb-3">
                        <label for="estado" class="form-label">Estado</label>
                        <select id="estado"class="form-select">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="btnGuardar">Registrar</button>
                </div>

            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->
@endsection

@section('script')
    <script>
        document.querySelector("#btnSincronizar").addEventListener("click", function() {
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
                textSwal.innerHTML = "Sincronizando: " + percent + "%";
            }, 1000);


            let empresa = document.getElementById('empresa').value;
            fetch('/empleados/sincronizar?empresa=' + empresa)
                .then(response => response.json())
                .then(data => {
                    textSwal.innerHTML = "Sincronizando: 100%";
                    clearInterval(interval);
                    Swal.fire({
                        title: "Listo",
                        text: "Sincronización completada con éxito",
                        icon: "success"
                    });
                    // Recargar la lista
                    $('#tableEmpleados').DataTable().destroy();
                    list();
                })
                .catch(error => {
                    textSwal.innerHTML = "Sincronizando: 100%";
                    clearInterval(interval);
                    Swal.fire({
                        title: "Error",
                        text: error,
                        icon: "warning"
                    });
                });
        });

        function list() {
            fetch("/empleados/list")
                .then(response => response.json())
                .then(data => {
                    const tableBody = document.querySelector('#tableEmpleados tbody');
                    tableBody.innerHTML = ''; // Limpiar filas existentes

                    data.forEach(item => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${item.company}</td>
                            <td>${item.empleadoid}</td>
                            <td>${item.nombres}</td>
                            <td>${item.apellidos}</td>
                            <td>${item.cedula}</td>
                            <td>${item.fechaingreso}</td>
                            <td>${item.fechasalida ?? ''}</td>
                        `;
                        tableBody.appendChild(row);
                    });

                    $('#tableEmpleados').DataTable({
                        responsive: true,
                        dom: 'Bfrtip',
                        buttons: [
                            'copy', 'csv', 'excel', 'pdf', 'print'
                        ]
                    });
                })
                .catch(error => console.error('Error fetching data:', error));
        }

        // Cargar la lista al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            list();
        });
    </script>
@endsection
