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
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('reportes.index') }}">Reportes</a></li>
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
                                        <input type="hidden" id="sistema" value="todos">
                                        <label for="empresa" class="form-label">Empresa</label>
                                        <select id="empresa" class="form-control">
                                            <option value="todos">Todas</option>
                                            <option value="grupo_joselito">Grupo Joselito</option>
                                            <option value="negosur">Negosur</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="estatus" class="form-label">Estatus</label>
                                        <select id="estatus" class="form-control">
                                            <option value="">Todos</option>
                                            <option value="No activo">No activo</option>
                                            <option value="No registrado">No registrado</option>
                                            <option value="En gestion">En gestion</option>
                                            <option value="Finalizado">Finalizado</option>
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
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="button" class="btn btn-outline-info d-block" id="btnEnviarSeguimiento" disabled>
                                            <i class="ri-send-plane-line"></i> Enviar a seguimiento
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row" id="sinCedulaSummary" style="display: none;">
                    <div class="col-lg-12">
                        <div class="alert alert-warning d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <strong>Agencias con ventas sin cédula:</strong>
                                <span id="sinCedulaCount" class="badge bg-danger ms-2">0</span>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-dark" id="btnVerSinCedula" data-bs-toggle="modal" data-bs-target="#modalSinCedula">
                                Ver detalle
                            </button>
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
                                                <th>Finalizada</th>
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

                <div class="modal fade" id="modalSinCedula" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Agencias con ventas sin cédula</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="table-responsive" style="max-height: 45vh; overflow-y: auto;">
                                    <table class="table table-bordered table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Agencia</th>
                                                <th>Días sin cédulas con ventas</th>
                                                <th>Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbodySinCedula"></tbody>
                                    </table>
                                </div>

                                <div id="detalleFechasContainer" class="mt-3" style="display:none;">
                                    <h6 class="mb-2" id="detalleFechasTitulo"></h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered table-striped mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Cantidad de ventas sin cédula</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tbodyFechasSinCedula"></tbody>
                                        </table>
                                    </div>
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
        let agenciasSinCedula = [];
        const CRUCE_SEGUIMIENTO_STORE_URL = '{{ route('reportes.cruce-usuarios.seguimiento.store') }}';
        const CSRF_TOKEN = '{{ csrf_token() }}';

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function limpiarDetalleFechas() {
            document.getElementById('detalleFechasContainer').style.display = 'none';
            document.getElementById('detalleFechasTitulo').textContent = '';
            document.getElementById('tbodyFechasSinCedula').innerHTML = '';
        }

        function abrirModalSinCedula() {
            const modalElement = document.getElementById('modalSinCedula');
            if (!modalElement) {
                return;
            }

            if (modalElement.parentElement !== document.body) {
                document.body.appendChild(modalElement);
            }

            if (window.bootstrap && typeof window.bootstrap.Modal === 'function') {
                const modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
                modal.show();
                return;
            }

            if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
                window.jQuery(modalElement).modal('show');
            }
        }

        function renderDetalleFechas(agenciaId, fechas) {
            const container = document.getElementById('detalleFechasContainer');
            const titulo = document.getElementById('detalleFechasTitulo');
            const tbody = document.getElementById('tbodyFechasSinCedula');

            titulo.textContent = 'Fechas con ventas sin cédula - Agencia ' + agenciaId;
            tbody.innerHTML = '';

            if (!fechas.length) {
                const tr = document.createElement('tr');
                tr.innerHTML = '<td colspan="2" class="text-center">No hay fechas disponibles</td>';
                tbody.appendChild(tr);
            } else {
                fechas.forEach(function(item) {
                    const tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + escapeHtml(item.Fecha ?? '') + '</td>' +
                        '<td>' + escapeHtml(item.Cantidad_Ventas ?? 0) + '</td>';
                    tbody.appendChild(tr);
                });
            }

            container.style.display = 'block';
        }

        function cargarDetalleFechasSinCedula(agenciaId) {
            const sistema = document.getElementById('sistema').value;
            const empresa = document.getElementById('empresa').value;
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;

            const tbody = document.getElementById('tbodyFechasSinCedula');
            document.getElementById('detalleFechasContainer').style.display = 'block';
            document.getElementById('detalleFechasTitulo').textContent = 'Fechas con ventas sin cédula - Agencia ' + agenciaId;
            tbody.innerHTML = '<tr><td colspan="2" class="text-center">Cargando...</td></tr>';

            $.ajax({
                url: '/reportes-cruce-usuarios/sin-cedula-fechas',
                type: 'GET',
                data: {
                    sistema: sistema,
                    empresa: empresa,
                    fecha_inicio: fechaInicio,
                    fecha_fin: fechaFin,
                    agencia_id: agenciaId
                },
                success: function(json) {
                    renderDetalleFechas(agenciaId, json.fechas || []);
                },
                error: function() {
                    tbody.innerHTML = '<tr><td colspan="2" class="text-center text-danger">No fue posible cargar el detalle</td></tr>';
                }
            });
        }

        function renderResumenSinCedula() {
            const summary = document.getElementById('sinCedulaSummary');
            const count = document.getElementById('sinCedulaCount');
            const tbody = document.getElementById('tbodySinCedula');

            count.textContent = agenciasSinCedula.length;
            summary.style.display = agenciasSinCedula.length > 0 ? 'block' : 'none';

            tbody.innerHTML = '';

            agenciasSinCedula.forEach(function(item) {
                const tr = document.createElement('tr');
                const agencia = item.Agencia ?? '';
                tr.innerHTML =
                    '<td>' + escapeHtml(agencia) + '</td>' +
                    '<td>' + escapeHtml(item.Dias_Sin_Cedula_Con_Ventas ?? 0) + '</td>' +
                    '<td class="text-center">' +
                        '<button type="button" class="btn btn-sm btn-outline-primary btnVerFechasSinCedula" data-agencia="' + escapeHtml(agencia) + '">' +
                            '<i class="ri-pencil-line"></i>' +
                        '</button>' +
                    '</td>';
                tbody.appendChild(tr);
            });
        }

        function renderSeguimiento(data, type, row) {
            if (!row.Seguimiento_Estado) {
                return '<span class="badge bg-secondary">Sin seguimiento</span>';
            }

            if (row.Seguimiento_Estado === 'finalizado') {
                return '<span class="badge bg-success">Finalizada' +
                    (row.Seguimiento_Finalizado ? ' - ' + escapeHtml(row.Seguimiento_Finalizado) : '') +
                    '</span>';
            }

            if (row.Seguimiento_Estado === 'en_gestion') {
                return '<span class="badge bg-info">En gestion' +
                    (row.Seguimiento_Inicio ? ' - ' + escapeHtml(row.Seguimiento_Inicio) : '') +
                    '</span>';
            }

            return '<span class="badge bg-warning text-dark">Pendiente</span>';
        }

        function filasActualesReporte() {
            if (!table) {
                return [];
            }

            return table.rows({ search: 'applied' }).data().toArray();
        }

        function enviarSeguimiento() {
            const filas = filasActualesReporte();

            if (!filas.length) {
                Swal.fire('Sin datos', 'Primero genera el reporte para enviar casos a seguimiento.', 'info');
                return;
            }

            Swal.fire({
                title: 'Enviar a seguimiento',
                text: 'Se crearan casos para las filas visibles del reporte. Los casos existentes no se duplicaran.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Enviar',
                cancelButtonText: 'Cancelar'
            }).then(function(result) {
                if (!result.isConfirmed) {
                    return;
                }

                Swal.fire({
                    title: 'Creando seguimiento...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                $.ajax({
                    url: CRUCE_SEGUIMIENTO_STORE_URL,
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': CSRF_TOKEN
                    },
                    data: JSON.stringify({
                        items: filas,
                        meta: {
                            sistema: document.getElementById('sistema').value,
                            empresa: document.getElementById('empresa').value,
                            fecha_inicio: document.getElementById('fecha_inicio').value,
                            fecha_fin: document.getElementById('fecha_fin').value
                        }
                    }),
                    contentType: 'application/json',
                    success: function(response) {
                        Swal.fire('Listo', response.message || 'Seguimiento creado.', 'success');
                        table.ajax.reload(null, false);
                    },
                    error: function(xhr) {
                        const message = xhr.responseJSON?.message || 'No fue posible crear el seguimiento.';
                        Swal.fire('Error', message, 'error');
                    }
                });
            });
        }

        function cargarDatos() {
            const sistema = document.getElementById('sistema').value;
            const estatus = document.getElementById('estatus').value;
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
                table.destroy();
            }

            table = $('#tableCruceUsuarios').DataTable({
                ajax: {
                    url: '/reportes-cruce-usuarios/list',
                    type: 'GET',
                    data: {
                        sistema: sistema,
                        estatus: estatus,
                        empresa: empresa,
                        fecha_inicio: fechaInicio,
                        fecha_fin: fechaFin
                    },
                    dataSrc: function(json) {
                        agenciasSinCedula = json.agencias_sin_cedula || [];
                        renderResumenSinCedula();
                        const resultados = json.resultados || [];
                        document.getElementById('btnEnviarSeguimiento').disabled = resultados.length === 0;
                        return resultados;
                    },
                    complete: function() {
                        Swal.close();
                    }
                },
                responsive: true,
                scrollX: true,
                columnDefs: [
                    { targets: [1, 3], visible: $(window).width() > 768 }
                ],
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
                    {
                        data: 'Seguimiento_Estado',
                        render: renderSeguimiento
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
                    { width: "170px", targets: 5 },
                    { width: "120px", targets: 6 }
                ],
                paging: false,
                dom: 'Bfrtip',
                buttons: [
                    'copy', 'csv', 'excel', 'pdf', 'print'
                ],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                },
                rowCallback: function(row, data) {
                    $(row).removeClass('table-success');

                    if (data.Seguimiento_Estado === 'finalizado') {
                        $(row).addClass('table-success');
                    }
                },
                order: [[6, 'desc']]
            });
        }

        document.getElementById('btnBuscar').addEventListener('click', cargarDatos);
        document.getElementById('btnEnviarSeguimiento').addEventListener('click', enviarSeguimiento);

        document.getElementById('btnVerSinCedula').addEventListener('click', function(event) {
            event.preventDefault();
            limpiarDetalleFechas();
            abrirModalSinCedula();
        });

        document.addEventListener('DOMContentLoaded', function() {
            const modalElement = document.getElementById('modalSinCedula');

            if (modalElement && modalElement.parentElement !== document.body) {
                document.body.appendChild(modalElement);
            }

            if (modalElement) {
                modalElement.addEventListener('hidden.bs.modal', function() {
                    limpiarDetalleFechas();
                });
            }
        });

        document.getElementById('tbodySinCedula').addEventListener('click', function(event) {
            const btn = event.target.closest('.btnVerFechasSinCedula');
            if (!btn) {
                return;
            }

            const agenciaId = btn.getAttribute('data-agencia');
            if (!agenciaId) {
                return;
            }

            cargarDetalleFechasSinCedula(agenciaId);
        });
    </script>
@endsection
