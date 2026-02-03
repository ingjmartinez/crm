@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <!-- start page title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Incentivos Reporte de Pagos</h4>

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
                                <div>
                                    <h3 class="mb-0">Total a pagar: <span id="totalPago"></span></h1>
                                </div>
                                <div class="d-flex gap-3 align-items-center justify-content-between">
                                    <div class="d-flex flex-column gap-2">
                                        <div class="d-flex gap-3 align-items-center justify-content-between">
                                            <div>
                                                <div><label class="mb-0" for="tipo">Tipo</label></div>
                                                <select id="tipo" class="form-select">
                                                    <option value="">Todos</option>
                                                    <option value="1">Agente de Venta</option>
                                                    <option value="2">Coordinador</option>
                                                    <option value="3">Administrativo</option>
                                                    <option value="4">Operador</option>
                                                </select>
                                            </div>
                                            <div>
                                                <div><label class="mb-0" for="empresa">Empresa</label></div>
                                                <select id="empresa" class="form-select">
                                                    <option value="">Todas</option>
                                                    <option value="168">Joselito</option>
                                                    <option value="169">Negosur</option>
                                                </select>
                                            </div>
                                            <div>
                                                <div><label class="mb-0" for="year">Año</label></div>
                                                <select id="year" class="form-select">
                                                    <option value="2026">2026</option>
                                                    <option value="2025">2025</option>
                                                    <option value="2024">2024</option>
                                                    <option value="2023">2023</option>
                                                    <option value="2022">2022</option>
                                                    <option value="2021">2021</option>
                                                </select>
                                            </div>
                                            <div>
                                                <div><label class="mb-0" for="mes">Mes</label></div>
                                                <select id="mes" class="form-select">
                                                    <option value="">Seleccione</option>
                                                    <option value="1">Enero</option>
                                                    <option value="2">Febrero</option>
                                                    <option value="3">Marzo</option>
                                                    <option value="4">Abril</option>
                                                    <option value="5">Mayo</option>
                                                    <option value="6">Junio</option>
                                                    <option value="7">Julio</option>
                                                    <option value="8">Agosto</option>
                                                    <option value="9">Septiembre</option>
                                                    <option value="10">Octubre</option>
                                                    <option value="11">Noviembre</option>
                                                    <option value="12">Diciembre</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-3 align-items-center justify-content-between">
                                            <div>
                                                <div><label class="mb-0" for="califican">Califican</label></div>
                                                <select id="califican" class="form-select">
                                                    <option value="1">Todos</option>
                                                    <option value="2">Califican</option>
                                                    <option value="3">No Califican</option>
                                                </select>
                                            </div>
                                            <div>
                                                <div><label class="mb-0" for="horas">Horas</label></div>
                                                <select id="horas" class="form-select">
                                                    <option value="1">Todos</option>
                                                    <option value="2">> 150</option>
                                                </select>
                                            </div>
                                            <div>
                                                <div><label class="mb-0" for="pago">Pago</label></div>
                                                <select id="pago" class="form-select">
                                                    <option value="1">Todos</option>
                                                    <option value="2">< $200.00</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-primary" id="btnGenerar">
                                        Genarar Data
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <table id="table"
                                    class="table table-bordered dt-responsive nowrap table-striped align-middle"
                                    style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Empresa</th>
                                            <th>Tipo Empleado</th>
                                            <th>ID Empleado</th>
                                            <th>Nombres y Apellidos</th>
                                            <th>Cedula</th>
                                            <th>Cuenta</th>
                                            <th>Monto a pagar</th>
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
    </div>
    <!-- end main content-->
@endsection

@section('script')
    <script>
        let datosToSave = [];

        function list() {
            let totalPago = 0;
            datosToSave = [];
            let year = document.getElementById('year').value;
            let mes = document.getElementById('mes').value;
            let empresa = document.getElementById('empresa').value;
            let tipo = document.getElementById('tipo').value;
            let califican = document.getElementById('califican').value;
            let horas = document.getElementById('horas').value;
            let pago = document.getElementById('pago').value;

            Swal.fire({
                title: "Procesando Información ...",
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                timerProgressBar: true,
                didOpen: () => Swal.showLoading()
            });

            $('#table').DataTable().destroy();
            $('#table tbody').empty();
            fetch("/incentivos/reporte-pago-incentivos?mes=" + mes + "&empresa=" + empresa + "&tipo=" + tipo + "&year=" +
                    year + "&califican=" + califican + "&horas=" + horas + "&pago=" + pago)
                .then(response => response.json())
                .then(data => {
                    if ('message' in data) {
                        Swal.fire({
                            title: "Información",
                            text: data.message,
                            icon: "warning"
                        });
                        return;
                    }

                    const tableBody = document.querySelector('#table tbody');
                    tableBody.innerHTML = ''; // Limpiar filas existentes

                    data.forEach(item => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${item.company}</td>
                            <td>${item.tipo}</td>
                            <td>${item.empleado_id}</td>
                            <td>${item.nombres}</td>
                            <td>${item.cedula}</td>
                            <td>${item.cuenta}</td>
                            <td>${item.monto}</td>
                        `;
                        tableBody.appendChild(row);
                    });

                    // Calcular el total
                    let total = data.reduce((sum, item) => {
                        return sum + parseFloat(item.monto.replace(/,/g, ''));
                    }, 0);
                    document.getElementById('totalPago').innerText = new Intl.NumberFormat('en-US', {
                        style: 'currency',
                        currency: 'USD'
                    }).format(total);

                    var table = $('#table').DataTable({
                        responsive: true,
                        dom: 'Bfrtip',
                        buttons: [
                            'copy', 'csv', 'excel', 'pdf', 'print'
                        ],
                        order: [
                            [0, 'asc'],
                            [2, 'asc']
                        ],
                        pageLength: 10000,
                        scrollY: '500px',
                        scrollCollapse: true,
                        language: {
                            lengthMenu: "Mostrar _MENU_ registros por página",
                            info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
                            infoEmpty: "No hay registros disponibles",
                            infoFiltered: "(filtrado de _MAX_ registros totales)",
                            search: "Buscar:",
                            paginate: {
                                first: "Primero",
                                last: "Último",
                                next: "Siguiente",
                                previous: "Anterior"
                            }
                        }
                    });
                    Swal.close();
                })
                .catch(error => {
                    Swal.fire({
                        title: "Error",
                        text: error,
                        icon: "warning"
                    });
                });
        }

        document.querySelector("#btnGenerar").addEventListener('click', function() {
            list();
        });
    </script>
@endsection
