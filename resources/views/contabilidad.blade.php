@extends('app')

@section('content')
    <!-- ============================================================== -->
    <!-- Start right Content here -->
    <!-- ============================================================== -->
    <div class="main-content">

        <div class="page-content">
            <div class="container-fluid">

                <!-- start page title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Datatables</h4>

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
                            <div class="card-header">
                                <h5 class="card-title mb-0">Cuentas</h5>
                            </div>
                            <div class="card-body">
                                <table id="tableCuentas"
                                    class="table table-bordered dt-responsive nowrap table-striped align-middle"
                                    style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Cuenta</th>
                                            <th>Descripción</th>
                                            <th>Cuenta Control</th>
                                            <th>Tipo</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div><!--end col-->

                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Detalle de cuenta</h5>
                            </div>
                            <div class="card-body">
                                <table id="tableDetalleCuenta"
                                    class="table table-bordered dt-responsive nowrap table-striped align-middle"
                                    style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Nro. Asiento</th>
                                            <th>Fecha</th>
                                            <th>Referencia</th>
                                            <th>Referencia No.</th>
                                            <th>Débito</th>
                                            <th>Crédito</th>
                                            <th>Descripción</th>
                                            <th>Grupo</th>
                                            <th>Subgrupo</th>
                                            <th>División</th>
                                            <th>Centro de Costo</th>
                                            <th>Conciliado</th>
                                            <th>Módulo</th>
                                            <th>Fecha Grabado</th>
                                            <th>Fecha Modificado</th>
                                            <th>Creado Por</th>
                                            <th>Modificado Por</th>
                                            <th>Referencia Descripción</th>
                                            <th>Sociedad</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div><!--end col-->
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
                    <h5 class="modal-title" id="myModalLabel">Modal Heading</h5>
                    <button type="button" id="btnClose" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="cuenta" class="form-label">Cuenta</label>
                        <input type="text" class="form-control" id="cuenta" placeholder="Cuenta" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="fecha" class="form-label">Fecha</label>
                        <input type="date" class="form-control" id="fecha" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="btnEntradas">Consultar Entradas</button>
                </div>

            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->
@endsection

@section('script')
    <script>
        fetch("/api-cuentas")
            .then(response => response.json())
            .then(data => {
                const tableBody = document.querySelector('#tableCuentas tbody');
                tableBody.innerHTML = ''; // Limpiar filas existentes

                data.forEach(item => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${item.CUENTA}</td>
                        <td>${item.DESCRIPCION}</td>
                        <td>${item.CTACONTROL}</td>
                        <td>${item.TIPO}</td>
                        <td><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#myModal" onclick="setCuenta('${item.CUENTA}')">Ver Entradas</button></td>
                    `;
                    tableBody.appendChild(row);
                });

                $('#tableCuentas').DataTable({
                    responsive: true,
                    dom: 'Bfrtip',
                    buttons: [
                        'copy', 'csv', 'excel', 'pdf', 'print'
                    ]
                });
            })
            .catch(error => console.error('Error fetching data:', error));

        function setCuenta(cuenta) {
            document.getElementById('cuenta').value = cuenta;
        }

        document.getElementById('btnEntradas').addEventListener('click', verDetalle);

        function verDetalle() {
            let cuenta = document.getElementById('cuenta').value;
            let fecha = document.getElementById('fecha').value;

            if (!cuenta) {
                alert("Por favor, seleccione una cuenta.");
                return;
            }

            fetch("/api-entradas?fecha=" + fecha + "&cuenta=" + cuenta)
                .then(async response => {
                    let data;

                    // Intentamos detectar si la respuesta es JSON o texto
                    const contentType = response.headers.get("content-type");

                    if (contentType && contentType.includes("application/json")) {
                        data = await response.json();
                    } else {
                        const text = await response.text();
                        // Intentamos parsear por si el servidor no envía el header correcto
                        try {
                            data = JSON.parse(text);
                        } catch {
                            // No es JSON, es texto plano
                            alert(text || "No hay nada encontrado");
                            return; // detenemos aquí
                        }
                    }

                    // Si llega aquí, es un JSON válido
                    if (!data.result || !data.result.Det) {
                        alert("No hay datos encontrados en la respuesta");
                        return;
                    }

                    const tableBody = document.querySelector('#tableDetalleCuenta tbody');
                    tableBody.innerHTML = '';

                    data.result.Det.forEach(item => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${item.NoAsiento}</td>
                            <td>${item.Fecha}</td>
                            <td>${item.Ref}</td>
                            <td>${item.NoRef}</td>
                            <td>${item.Debito}</td>
                            <td>${item.Credito}</td>
                            <td>${item.Descripcion}</td>
                            <td>${item.Grupo}</td>
                            <td>${item.SubGrupo}</td>
                            <td>${item.Division}</td>
                            <td>${item.CentroCosto}</td>
                            <td>${item.Conciliado}</td>
                            <td>${item.Modulo}</td>
                            <td>${item.FechaGrabado}</td>
                            <td>${item.CreadoPor}</td>
                            <td>${item.FechaModificado}</td>
                            <td>${item.ModificadoPor}</td>
                            <td>${item.RefDesc}</td>
                            <td>${item.Sociedad}</td>
                        `;
                        tableBody.appendChild(row);
                    });

                    document.getElementById('btnClose').click();

                    $('#tableDetalleCuenta').DataTable({
                        destroy: true,
                        responsive: true,
                        dom: 'Bfrtip',
                        buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
                    });
                })
                .catch(error => {
                    alert("Error en la petición: " + error);
                });

        }
    </script>
@endsection
