@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">

                <!-- start page title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Ventas por Usuario</h4>

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
                                <h5 class="card-title mb-0">Configurar Token</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-5">
                                    <div class="col-2">
                                        <button id="btnGenerarData" class="btn btn-primary">Generar Data</button>
                                    </div>

                                    <div class="col-2">
                                        <input type="date" id="inputFecha" class="form-control">
                                    </div>
                                    <div class="col-3">
                                        <button id="btnGuardarData" class="btn btn-primary">Guardar Data</button>
                                        <button id="btnEliminarData" class="btn btn-danger">Eliminar Data</button>
                                    </div>
                                    <div class="col-4 text-end">
                                        <button id="btnConsultar" type="button" class="btn btn-primary"
                                            data-bs-toggle="modal" data-bs-target="#myModal">Generar Data Por Fecha</button>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table id="tableVentas"
                                        class="table table-bordered dt-responsive nowrap table-striped align-middle"
                                        style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>Dia</th>
                                                <th>EDiFecha</th>
                                                <th>GrupoID</th>
                                                <th>GruNombre</th>
                                                <th>RiferoID</th>
                                                <th>RifNombre</th>
                                                <th>BancaID</th>
                                                <th>BanNombre</th>
                                                <th>BanContacto</th>
                                                <th>BanComisionQ</th>
                                                <th>BanComisionP</th>
                                                <th>BanComisionT</th>
                                                <th>BanVComision</th>
                                                <th>PagoDeOtra</th>
                                                <th>PagoEnOtra</th>
                                                <th>PagosPendiente</th>
                                                <th>DiasPendiente</th>
                                                <th>VTarjComisionBanca</th>
                                                <th>VTarjComision</th>
                                                <th>VTarjetas</th>
                                                <th>CVQuinielas</th>
                                                <th>VQuinielas</th>
                                                <th>CVPales</th>
                                                <th>CVTripletas</th>
                                                <th>VPales</th>
                                                <th>VTripletas</th>
                                                <th>CPrimero</th>
                                                <th>CSegundo</th>
                                                <th>CTercero</th>
                                                <th>CPales</th>
                                                <th>CTripletas</th>
                                                <th>MPrimero</th>
                                                <th>MSegundo</th>
                                                <th>MTercero</th>
                                                <th>MPales</th>
                                                <th>MTripletas</th>
                                                <th>RifDescuento</th>
                                                <th>ISRRetenido</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!--end row-->
            </div>
            <!-- container-fluid -->
        </div>
    </div>

    <div id="myModal" class="modal fade" tabindex="-1" aria-labelledby="myModalLabel" aria-hidden="true"
        style="display: none;">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="myModalLabel">Guardar Datos Por Rango De Fechas</h5>
                    <button type="button" id="btnClose" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="fechaInicio" class="form-label">Fecha Inicio</label>
                        <input type="date" class="form-control" id="fechaInicio">
                    </div>
                    <div class="mb-3">
                        <label for="fechaFin" class="form-label">Fecha Fin</label>
                        <input type="date" class="form-control" id="fechaFin">
                    </div>


                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarDataFecha">Registrar Data</button>
                </div>

            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->
@endsection

@section('script')
    <script>
        const btnGenerarData = document.getElementById('btnGenerarData');
        btnGenerarData.addEventListener('click', () => {
            const fecha = document.getElementById('inputFecha').value;
            if (!fecha) {
                Swal.fire({
                    title: "Error",
                    text: "Por favor, selecciona una fecha",
                    icon: "error"
                });
                return;
            }

            Swal.fire({
                title: "Cargando ...",
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                timerProgressBar: true,
                didOpen: () => Swal.showLoading()
            });

            $('#tableVentas').DataTable().destroy();
            const tableBody = document.querySelector('#tableVentas tbody');
            tableBody.innerHTML = '';

            fetch(`/get-mar-ventas?fecha=${fecha}`)
                .then(response => response.json())
                .then(data => {
                    if (data.code != 0) {
                        Swal.fire({
                            title: "Error",
                            text: data.message,
                            icon: "error"
                        });
                    } else {
                        Swal.fire({
                            title: "Listo",
                            text: "Datos obtenidos correctamente",
                            icon: "success"
                        });

                        tableBody.innerHTML = ''; // Clear existing rows

                        data.datos.forEach(item => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${item.Dia}</td>
                                <td>${item.EDiFecha}</td>
                                <td>${item.GrupoID}</td>
                                <td>${item.GruNombre}</td>
                                <td>${item.RiferoID}</td>
                                <td>${item.RifNombre}</td>
                                <td>${item.BancaID}</td>
                                <td>${item.BanNombre}</td>
                                <td>${item.BanContacto}</td>
                                <td>${item.BanComisionQ}</td>
                                <td>${item.BanComisionP}</td>
                                <td>${item.BanComisionT}</td>
                                <td>${item.BanVComision}</td>
                                <td>${item.PagoDeOtra}</td>
                                <td>${item.PagoEnOtra}</td>
                                <td>${item.PagosPendiente}</td>
                                <td>${item.DiasPendiente}</td>
                                <td>${item.VTarjComisionBanca}</td>
                                <td>${item.VTarjComision}</td>
                                <td>${item.VTarjetas}</td>
                                <td>${item.CVQuinielas}</td>
                                <td>${item.VQuinielas}</td>
                                <td>${item.CVPales}</td>
                                <td>${item.CVTripletas}</td>
                                <td>${item.VPales}</td>
                                <td>${item.VTripletas}</td>
                                <td>${item.CPrimero}</td>
                                <td>${item.CSegundo}</td>
                                <td>${item.CTercero}</td>
                                <td>${item.CPales}</td>
                                <td>${item.CTripletas}</td>
                                <td>${item.MPrimero}</td>
                                <td>${item.MSegundo}</td>
                                <td>${item.MTercero}</td>
                                <td>${item.MPales}</td>
                                <td>${item.MTripletas}</td>
                                <td>${item.RifDescuento}</td>
                                <td>${item.ISRRetenido}</td>
                            `;
                            tableBody.appendChild(row);
                        });

                        $('#tableVentas').DataTable({
                            destroy: true,
                            responsive: true,
                            dom: 'Bfrtip',
                            buttons: [
                                'copy', 'csv', 'excel', 'pdf', 'print'
                            ]
                        });
                    }
                })
                .catch(error => console.error('Error fetching data:', error));
        });

        const btnGuardarData = document.getElementById('btnGuardarData');
        btnGuardarData.addEventListener('click', () => {
            const fecha = document.getElementById('inputFecha').value;
            if (!fecha) {
                Swal.fire({
                    title: "Error",
                    text: "Por favor, selecciona una fecha",
                    icon: "error"
                });
                return;
            }

            Swal.fire({
                title: "Guardando información ...",
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                timerProgressBar: true,
                didOpen: () => Swal.showLoading()
            });
            fetch(`/save-mar-ventas?fecha=${fecha}`)
                .then(response => response.json())
                .then(data => {
                    Swal.fire({
                        title: "Listo",
                        text: data.message,
                        icon: "success"
                    });
                })
                .catch(error => console.error('Error fetching data:', error));
        });

        const btnEliminarData = document.getElementById('btnEliminarData');
        btnEliminarData.addEventListener('click', () => {
            const fecha = document.getElementById('inputFecha').value;
            if (!fecha) {
                Swal.fire({
                    title: "Error",
                    text: "Por favor, selecciona una fecha",
                    icon: "error"
                });
                return;
            }

            Swal.fire({
                title: "Guardando información ...",
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                timerProgressBar: true,
                didOpen: () => Swal.showLoading()
            });
            fetch(`/delete-mar-ventas?fecha=${fecha}`)
                .then(response => response.json())
                .then(data => {
                    Swal.fire({
                        title: "Listo",
                        text: data.message,
                        icon: "success"
                    });
                })
                .catch(error => console.error('Error fetching data:', error));
        });

        const btnGuardarDataFecha = document.getElementById('btnGuardarDataFecha');
        btnGuardarDataFecha.addEventListener('click', async () => {
            // Quiero consultar un api y hacer una peticion por cada fecha entre el rango seleccionado
            const fechaInicio = document.getElementById('fechaInicio').value;
            const fechaFin = document.getElementById('fechaFin').value;
            if (!fechaInicio || !fechaFin) {
                Swal.fire({
                    title: "Error",
                    text: "Por favor, selecciona ambas fechas",
                    icon: "error"
                });
                return;
            }

            // recorrer la fecha entre el rango
            const startDate = new Date(fechaInicio);
            const endDate = new Date(fechaFin);

            if (startDate > endDate) {
                Swal.fire({
                    title: "Error",
                    text: "La fecha de inicio debe ser anterior a la fecha de fin",
                    icon: "error"
                });
                return;
            }

            let responses = [];
            let currentDate = new Date(startDate);
            const dates = [];

            while (currentDate <= endDate) {
                dates.push(currentDate.toISOString().split('T')[0]); // Formato YYYY-MM-DD
                currentDate.setDate(currentDate.getDate() + 1); // Incrementar un día
            }

            // Ejecutar las peticiones una a una (secuencialmente)
            btnGuardarDataFecha.disabled = true;
            try {
                Swal.fire({
                    title: "Guardando información ...",
                    html: `0 / ${dates.length}`,
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });

                for (let i = 0; i < dates.length; i++) {
                    const date = dates[i];
                    // Actualizar progreso en el modal
                    Swal.update({
                        html: `Procesando ${date} (${i + 1} / ${dates.length})`
                    });

                    const response = await fetch(`/save-mar-ventas?fecha=${date}`);
                    if (!response.ok) {
                        const text = await response.text().catch(() => null);
                        throw new Error(text || `Error HTTP ${response.status}`);
                    }
                    const data = await response.json().catch(() => null);

                    // Si tu API devuelve un código de error, puedes manejarlo aquí
                    if (data && data.code !== undefined && data.code !== 0) {
                        throw new Error(data.message || `Error guardando fecha ${date}`);
                    }

                    if (!data.total) {
                        responses.push(data.message);
                    } else {
                        responses.push('Fecha: ' + date + ' Total: ' + data.total);
                    }
                    // Opcional: puedes hacer una pequeña pausa si tu API lo requiere
                    // await new Promise(r => setTimeout(r, 200));
                }

                // Cerrar modal del range y notificar éxito
                document.getElementById('btnClose').click();
                Swal.fire({
                    title: "Listo",
                    html: responses.join('<br>'),
                    icon: "success"
                });
            } catch (error) {
                Swal.fire({
                    title: "Error",
                    text: error.message || "Ocurrió un error al procesar las fechas",
                    icon: "error"
                });
            } finally {
                btnGuardarDataFecha.disabled = false;
            }
        });
    </script>
@endsection
