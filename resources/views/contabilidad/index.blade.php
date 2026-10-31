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
                            <h4 class="mb-sm-0">Inicio Contable</h4>
                            <div class="page-title-right d-flex flex-wrap align-items-center justify-content-end gap-2">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('contabilidad.index') }}">Contabilidad</a></li>
                                    <li class="breadcrumb-item active">Inicio Contable</li>
                                </ol>
                                <a href="{{ route('contabilidad.electricidad') }}" class="btn btn-outline-primary btn-sm">
                                    Electricidad
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end page title -->

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Cuentas</h5>
                                    <div class="d-flex flex-wrap gap-2 align-items-end">
                                        <div>
                                            <label for="limiteSincronizarCuentas" class="form-label mb-1">Limite de cuentas</label>
                                            <input type="number" id="limiteSincronizarCuentas" name="limite" class="form-control" min="1" max="10000" step="1" value="200" required style="width: 140px">
                                        </div>
                                        <button type="button" class="btn btn-info me-2" id="btnSincronizarCuentas">
                                            Sincronizar
                                        </button>
                                        <button type="button" class="btn btn-success" id="btnNuevaCuenta">
                                            Nueva cuenta
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="filtroEmpresaContabilidad" class="form-label">Empresa</label>
                                        <select id="filtroEmpresaContabilidad" class="form-select">
                                            <option value="168">168 - Grupo Joselito</option>
                                            <option value="169">169 - Negosur</option>
                                        </select>
                                    </div>
                                </div>
                                <table id="tableCuentas"
                                    class="table table-bordered dt-responsive nowrap table-striped align-middle"
                                    style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Cuenta</th>
                                            <th>Descripción</th>
                                            <th>Cuenta Control</th>
                                            <th>Tipo</th>
                                            <th>Acciones</th>
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
                        <label for="fechaInicio" class="form-label">Fecha inicio</label>
                        <input type="date" class="form-control" id="fechaInicio" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="fechaFin" class="form-label">Fecha fin</label>
                        <input type="date" class="form-control" id="fechaFin" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="btnEntradas">Consultar Entradas</button>
                </div>

            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->

    <div id="cuentaModal" class="modal fade" tabindex="-1" aria-labelledby="cuentaModalLabel" aria-hidden="true"
        style="display: none;">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cuentaModalLabel">Nueva cuenta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="cuentaId">
                    <div class="mb-3">
                        <label for="formCuenta" class="form-label">Cuenta</label>
                        <input type="text" class="form-control" id="formCuenta" placeholder="Cuenta">
                    </div>
                    <div class="mb-3">
                        <label for="formDescripcion" class="form-label">Descripción</label>
                        <input type="text" class="form-control" id="formDescripcion" placeholder="Descripción">
                    </div>
                    <div class="mb-3">
                        <label for="formCtaControl" class="form-label">Cuenta Control</label>
                        <input type="text" class="form-control" id="formCtaControl" placeholder="Cuenta Control">
                    </div>
                    <div class="mb-3">
                        <label for="formTipo" class="form-label">Tipo</label>
                        <input type="text" class="form-control" id="formTipo" placeholder="Tipo">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarCuenta">Guardar</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        const csrfToken = '{{ csrf_token() }}';
        const cuentaModalElement = document.getElementById('cuentaModal');
        const cuentaModal = new bootstrap.Modal(cuentaModalElement);

        document.getElementById('btnNuevaCuenta').addEventListener('click', abrirModalNuevaCuenta);
        document.getElementById('btnGuardarCuenta').addEventListener('click', guardarCuenta);
        document.getElementById('btnSincronizarCuentas').addEventListener('click', sincronizarCuentas);

        const filtroEmpresaContabilidad = document.getElementById('filtroEmpresaContabilidad');
        let versionConsultaEmpresa = 0;
        filtroEmpresaContabilidad.addEventListener('change', () => {
            versionConsultaEmpresa++;
            document.getElementById('cuenta').value = '';
            cuentaModal.hide();
            bootstrap.Modal.getInstance(document.getElementById('myModal'))?.hide();
            for (const selector of ['#tableCuentas', '#tableDetalleCuenta']) {
                if ($.fn.DataTable.isDataTable(selector)) {
                    $(selector).DataTable().clear().destroy();
                }
                document.querySelector(selector + ' tbody').innerHTML = '';
            }
            cargarCuentas();
        });

        function empresaSeleccionada() {
            return filtroEmpresaContabilidad.value;
        }

        cargarCuentas();

        function cargarCuentas() {
            const version = versionConsultaEmpresa;
            fetch('/api-cuentas?empresa=' + empresaSeleccionada())
                .then(async response => {
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.message || 'No se pudieron cargar las cuentas.');
                    return data;
                })
                .then(data => {
                    if (version !== versionConsultaEmpresa) return;
                    if ($.fn.DataTable.isDataTable('#tableCuentas')) $('#tableCuentas').DataTable().destroy();
                    const tableBody = document.querySelector('#tableCuentas tbody');
                    tableBody.innerHTML = '';

                    data.forEach(item => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${item.CUENTA ?? ''}</td>
                            <td>${item.DESCRIPCION ?? ''}</td>
                            <td>${item.CTACONTROL ?? ''}</td>
                            <td>${item.TIPO ?? ''}</td>
                            <td>
                                <button class="btn btn-primary btn-sm me-1" onclick="verEntradasCuenta('${item.CUENTA}')">Ver Entradas</button>
                                <button class="btn btn-warning btn-sm me-1" onclick="editarCuenta(${item.id}, '${escapeJs(item.CUENTA)}', '${escapeJs(item.DESCRIPCION)}', '${escapeJs(item.CTACONTROL)}', '${escapeJs(item.TIPO)}')">Editar</button>
                                <button class="btn btn-danger btn-sm" onclick="eliminarCuenta(${item.id})">Eliminar</button>
                            </td>
                        `;
                        tableBody.appendChild(row);
                    });

                    if ($.fn.DataTable.isDataTable('#tableCuentas')) {
                        $('#tableCuentas').DataTable().destroy();
                    }

                    $('#tableCuentas').DataTable({
                        responsive: true,
                        scrollX: true,
                        columnDefs: [
                            { targets: [2, 3], visible: $(window).width() > 768 }
                        ],
                        dom: 'Bfrtip',
                        buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
                    });
                })
                .catch(error => {
                    console.error('Error fetching data:', error);
                    alert('No se pudieron cargar las cuentas.');
                });
        }

        function abrirModalNuevaCuenta() {
            document.getElementById('cuentaModalLabel').innerText = 'Nueva cuenta';
            document.getElementById('cuentaId').value = '';
            document.getElementById('formCuenta').value = '';
            document.getElementById('formDescripcion').value = '';
            document.getElementById('formCtaControl').value = '';
            document.getElementById('formTipo').value = '';
            cuentaModal.show();
        }

        function editarCuenta(id, cuenta, descripcion, ctacontrol, tipo) {
            document.getElementById('cuentaModalLabel').innerText = 'Editar cuenta';
            document.getElementById('cuentaId').value = id;
            document.getElementById('formCuenta').value = cuenta || '';
            document.getElementById('formDescripcion').value = descripcion || '';
            document.getElementById('formCtaControl').value = ctacontrol || '';
            document.getElementById('formTipo').value = tipo || '';
            cuentaModal.show();
        }

        function guardarCuenta() {
            const id = document.getElementById('cuentaId').value;
            const payload = {
                empresa: empresaSeleccionada(),
                cuenta: document.getElementById('formCuenta').value.trim(),
                descripcion: document.getElementById('formDescripcion').value.trim(),
                ctacontrol: document.getElementById('formCtaControl').value.trim(),
                tipo: document.getElementById('formTipo').value.trim(),
            };

            if (!payload.cuenta || !payload.descripcion) {
                alert('Cuenta y descripción son obligatorias.');
                return;
            }

            const url = id ? `/api-cuentas/${id}` : '/api-cuentas';
            const method = id ? 'PUT' : 'POST';

            fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                })
                .then(async response => {
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        const errorText = data.message || 'No se pudo guardar la cuenta.';
                        throw new Error(errorText);
                    }
                    return data;
                })
                .then(() => {
                    cuentaModal.hide();
                    cargarCuentas();
                })
                .catch(error => {
                    alert(error.message || 'Error guardando la cuenta.');
                });
        }

        function sincronizarCuentas() {
            const campoLimite = document.getElementById('limiteSincronizarCuentas');
            if (!campoLimite.reportValidity()) return;
            const limite = Number(campoLimite.value);
            if (!confirm('¿Deseas sincronizar las cuentas desde el API externo?')) {
                return;
            }

            const boton = document.getElementById('btnSincronizarCuentas');
            const textoOriginal = boton.innerText;
            boton.disabled = true;
            campoLimite.disabled = true;
            boton.innerText = 'Sincronizando...';

            fetch('/api-cuentas/sync?empresa=' + empresaSeleccionada(), {
                    method: 'POST',
                    body: JSON.stringify({ limite }),
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                })
                .then(async response => {
                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        throw new Error(data.message || 'No se pudo sincronizar.');
                    }

                    alert(
                        `Sincronización completada.\nRecibidas: ${data.total_recibidas ?? 0}\nCreadas: ${data.creadas ?? 0}\nActualizadas: ${data.actualizadas ?? 0}\nOmitidas: ${data.omitidas ?? 0}`
                    );

                    cargarCuentas();
                })
                .catch(error => {
                    alert(error.message || 'Error sincronizando cuentas.');
                })
                .finally(() => {
                    boton.disabled = false;
                    campoLimite.disabled = false;
                    boton.innerText = textoOriginal;
                });
        }

        function eliminarCuenta(id) {
            if (!confirm('¿Seguro que desea eliminar esta cuenta?')) {
                return;
            }

            fetch(`/api-cuentas/${id}?empresa=${empresaSeleccionada()}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                })
                .then(async response => {
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        const errorText = data.message || 'No se pudo eliminar la cuenta.';
                        throw new Error(errorText);
                    }
                    return data;
                })
                .then(() => {
                    cargarCuentas();
                })
                .catch(error => {
                    alert(error.message || 'Error eliminando la cuenta.');
                });
        }

        function escapeJs(value) {
            return String(value ?? '')
                .replace(/\\/g, '\\\\')
                .replace(/'/g, "\\'")
                .replace(/\"/g, '&quot;');
        }

        function verEntradasCuenta(cuenta) {
            setCuenta(cuenta);
            const modal = new bootstrap.Modal(document.getElementById('myModal'));
            modal.show();
        }

        function setCuenta(cuenta) {
            document.getElementById('cuenta').value = cuenta;
        }

        document.getElementById('btnEntradas').addEventListener('click', verDetalle);

        function verDetalle() {
            let cuenta = document.getElementById('cuenta').value;
            let fechaInicio = document.getElementById('fechaInicio').value;
            let fechaFin = document.getElementById('fechaFin').value;

            if (!cuenta) {
                alert("Por favor, seleccione una cuenta.");
                return;
            }

            if (!fechaInicio || !fechaFin) {
                alert("Por favor, seleccione el rango de fechas.");
                return;
            }

            if (fechaInicio > fechaFin) {
                alert("La fecha inicio no puede ser mayor que la fecha fin.");
                return;
            }

            fetch("/api-entradas?empresa=" + empresaSeleccionada() + "&fecha_inicio=" + encodeURIComponent(fechaInicio) + "&fecha_fin=" + encodeURIComponent(fechaFin) + "&cuenta=" + encodeURIComponent(cuenta))
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
                        scrollX: true,
                        columnDefs: [
                            { targets: [3, 4, 5, 6, 7, 8, 9, 10, 11], visible: $(window).width() > 992 }
                        ],
                        dom: 'Bfrtip',
                        buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
                    });
                })
                .catch(error => {
                    alert("Error en la petición: " + error);
                });

        }

        function parseFechaIso(fecha) {
            const partes = String(fecha || '').split('-').map(Number);
            if (partes.length !== 3) {
                return null;
            }

            const [anio, mes, dia] = partes;
            if (!anio || !mes || !dia) {
                return null;
            }

            return new Date(anio, mes - 1, dia);
        }

        function formatFechaIso(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }

        function listarFechasRango(fechaInicio, fechaFin) {
            const inicio = parseFechaIso(fechaInicio);
            const fin = parseFechaIso(fechaFin);
            const fechas = [];

            if (!inicio || !fin) {
                return fechas;
            }

            const cursor = new Date(inicio);
            while (cursor <= fin) {
                fechas.push(formatFechaIso(cursor));
                cursor.setDate(cursor.getDate() + 1);
            }

            return fechas;
        }

        function actualizarSwalProgresoEntradas(cuenta, fechaActual, completados, total) {
            if (typeof Swal === 'undefined' || !Swal.isVisible()) {
                return;
            }

            const porcentaje = total > 0 ? Math.min(100, Math.round((completados / total) * 100)) : 0;
            const html = Swal.getHtmlContainer();
            if (!html) {
                return;
            }

            html.innerHTML = `
                <div class="text-start">
                    <div><strong>Cuenta:</strong> ${cuenta}</div>
                    <div><strong>Progreso:</strong> ${completados} de ${total} dias (${porcentaje}%)</div>
                    <div class="progress mt-2" style="height:10px;">
                        <div class="progress-bar" role="progressbar" style="width:${porcentaje}%;" aria-valuenow="${porcentaje}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="text-muted small mt-2">Procesando ${fechaActual}</div>
                </div>
            `;
        }

        async function consultarEntradasDia(cuenta, fecha, empresa) {
            const response = await fetch("/api-entradas?empresa=" + empresa + "&fecha_inicio=" + encodeURIComponent(fecha) + "&fecha_fin=" + encodeURIComponent(fecha) + "&cuenta=" + encodeURIComponent(cuenta));
            const contentType = response.headers.get("content-type");
            let data;

            if (contentType && contentType.includes("application/json")) {
                data = await response.json();
            } else {
                const text = await response.text();
                try {
                    data = JSON.parse(text);
                } catch {
                    throw new Error(text || "No hay nada encontrado");
                }
            }

            if (!response.ok) {
                throw new Error(data?.message || ("No se pudo consultar la fecha " + fecha));
            }

            return Array.isArray(data?.result?.Det) ? data.result.Det : [];
        }

        async function verDetalle() {
            const empresa = empresaSeleccionada();
            const version = versionConsultaEmpresa;
            let cuenta = document.getElementById('cuenta').value;
            let fechaInicio = document.getElementById('fechaInicio').value;
            let fechaFin = document.getElementById('fechaFin').value;

            if (!cuenta) {
                alert("Por favor, seleccione una cuenta.");
                return;
            }

            if (!fechaInicio || !fechaFin) {
                alert("Por favor, seleccione el rango de fechas.");
                return;
            }

            if (fechaInicio > fechaFin) {
                alert("La fecha inicio no puede ser mayor que la fecha fin.");
                return;
            }

            const fechas = listarFechasRango(fechaInicio, fechaFin);

            try {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Consultando entradas',
                        html: '<div class="text-start"><strong>Preparando consulta...</strong></div>',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: function () {
                            Swal.showLoading();
                        }
                    });
                }

                const detalles = [];

                for (let index = 0; index < fechas.length; index += 1) {
                    const fecha = fechas[index];
                    actualizarSwalProgresoEntradas(cuenta, fecha, index, fechas.length);
                    const items = await consultarEntradasDia(cuenta, fecha, empresa);
                    if (version !== versionConsultaEmpresa) {
                        if (typeof Swal !== 'undefined') Swal.close();
                        return;
                    }
                    items.forEach(function (item) {
                        detalles.push(item);
                    });
                    actualizarSwalProgresoEntradas(cuenta, fecha, index + 1, fechas.length);
                }

                if (typeof Swal !== 'undefined') {
                    Swal.close();
                }

                if (!detalles.length) {
                    alert("No hay datos encontrados en la respuesta");
                    return;
                }

                const tableBody = document.querySelector('#tableDetalleCuenta tbody');
                tableBody.innerHTML = '';

                detalles.forEach(item => {
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
                    scrollX: true,
                    columnDefs: [
                        { targets: [3, 4, 5, 6, 7, 8, 9, 10, 11], visible: $(window).width() > 992 }
                    ],
                    dom: 'Bfrtip',
                    buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
                });
            } catch (error) {
                if (typeof Swal !== 'undefined') {
                    Swal.close();
                }
                alert("Error en la petición: " + error.message);
            }
        }
    </script>
@endsection
