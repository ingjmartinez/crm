@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Mantenimiento de Equipos</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('servicios-generales.index') }}">Servicios Generales</a></li>
                                    <li class="breadcrumb-item active">Mantenimiento de Equipos</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                @if ($setupPending)
                    <div class="alert alert-warning">
                        La tabla de mantenimiento de equipos aun no existe. Ejecuta las migraciones para activar este reporte.
                    </div>
                @endif

                <div class="row g-3 mb-3">
                    <div class="col-xl-3 col-md-6">
                        <div class="card border mb-0">
                            <div class="card-body">
                                <div class="text-muted text-uppercase small fw-semibold">Vencidos</div>
                                <div class="fs-2 fw-semibold text-danger" id="statVencido">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card border mb-0">
                            <div class="card-body">
                                <div class="text-muted text-uppercase small fw-semibold">Por vencer</div>
                                <div class="fs-2 fw-semibold text-warning" id="statPorVencer">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card border mb-0">
                            <div class="card-body">
                                <div class="text-muted text-uppercase small fw-semibold">Vigentes</div>
                                <div class="fs-2 fw-semibold text-success" id="statVigente">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card border mb-0">
                            <div class="card-body">
                                <div class="text-muted text-uppercase small fw-semibold">Realizados</div>
                                <div class="fs-2 fw-semibold text-info" id="statRealizado">0</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label for="filtroEstado" class="form-label">Estado</label>
                                <select id="filtroEstado" class="form-control">
                                    <option value="">Todos</option>
                                    <option value="vencido">Vencido</option>
                                    <option value="por_vencer">Por vencer</option>
                                    <option value="vigente">Vigente</option>
                                    <option value="realizado">Realizado</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="filtroBusqueda" class="form-label">Busqueda</label>
                                <input type="text" id="filtroBusqueda" class="form-control" placeholder="Terminal, agencia o equipo">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-primary w-100" id="btnBuscar">
                                    <i class="ri-search-line"></i> Buscar
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-success w-100" id="btnNuevo">
                                    <i class="ri-add-line"></i> Nuevo mantenimiento
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="tablaMantenimientoEquipos" class="table table-bordered table-striped align-middle w-100">
                                <thead>
                                    <tr>
                                        <th>Terminal</th>
                                        <th>Agencia</th>
                                        <th>Equipo</th>
                                        <th>Codigo</th>
                                        <th>Fecha mantenimiento</th>
                                        <th>Estado</th>
                                        <th>Dias</th>
                                        <th>Realizado</th>
                                        <th>Acciones</th>
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

    <div class="modal fade" id="modalMantenimiento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form id="formMantenimiento">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalMantenimientoTitle">Nuevo mantenimiento</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="mantenimientoId">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="terminalCodigo" class="form-label">Terminal</label>
                                <input type="text" id="terminalCodigo" class="form-control" list="terminalesList" required>
                                <datalist id="terminalesList">
                                    @foreach ($terminales as $terminal)
                                        <option value="{{ $terminal->terminal }}">{{ $terminal->nombre_agencia }} {{ $terminal->ciudad ? '- ' . $terminal->ciudad : '' }}</option>
                                    @endforeach
                                </datalist>
                            </div>
                            <div class="col-md-6">
                                <label for="equipoTipo" class="form-label">Equipo</label>
                                <input type="text" id="equipoTipo" class="form-control" placeholder="Ej: Inversor, CPU, Monitor, Router" required>
                            </div>
                            <div class="col-md-6">
                                <label for="equipoCodigo" class="form-label">Codigo / Serial</label>
                                <input type="text" id="equipoCodigo" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label for="fechaMantenimiento" class="form-label">Fecha mantenimiento</label>
                                <input type="date" id="fechaMantenimiento" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label for="descripcion" class="form-label">Descripcion</label>
                                <textarea id="descripcion" class="form-control" rows="3" placeholder="Detalle del equipo o mantenimiento programado"></textarea>
                            </div>
                            <div class="col-12">
                                <label for="observacion" class="form-label">Observacion</label>
                                <textarea id="observacion" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        const MANTENIMIENTO_BASE_URL = '{{ url('/servicios-generales/mantenimiento-equipos') }}';
        const MANTENIMIENTO_LIST_URL = '{{ route('servicios-generales.mantenimiento-equipos.list') }}';
        const CSRF_MANTENIMIENTO = '{{ csrf_token() }}';
        let tablaMantenimiento;
        let modalMantenimiento;

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function estadoBadge(row) {
            if (row.estado_calculado === 'realizado') {
                return '<span class="badge bg-info">Realizado</span>';
            }

            if (row.estado_calculado === 'vencido') {
                return '<span class="badge bg-danger">Vencido</span>';
            }

            if (row.estado_calculado === 'por_vencer') {
                return '<span class="badge bg-warning text-dark">Por vencer</span>';
            }

            return '<span class="badge bg-success">Vigente</span>';
        }

        function diasTexto(row) {
            if (row.estado_calculado === 'realizado') {
                return '-';
            }

            const dias = Number(row.dias_restantes ?? 0);

            if (dias < 0) {
                return Math.abs(dias) + ' dias vencido';
            }

            return dias + ' dias';
        }

        function acciones(data, type, row) {
            const editar = '<button type="button" class="btn btn-sm btn-outline-primary btnEditar" data-id="' + row.id + '">Editar</button>';
            const eliminar = '<button type="button" class="btn btn-sm btn-outline-danger btnEliminar" data-id="' + row.id + '">Eliminar</button>';
            const realizar = row.estado_calculado !== 'realizado'
                ? '<button type="button" class="btn btn-sm btn-success btnRealizar" data-id="' + row.id + '">Realizar</button>'
                : '';

            return '<div class="d-flex flex-wrap gap-2">' + editar + realizar + eliminar + '</div>';
        }

        function actualizarStats(stats) {
            document.getElementById('statVencido').textContent = Number(stats?.vencido || 0).toLocaleString('en-US');
            document.getElementById('statPorVencer').textContent = Number(stats?.por_vencer || 0).toLocaleString('en-US');
            document.getElementById('statVigente').textContent = Number(stats?.vigente || 0).toLocaleString('en-US');
            document.getElementById('statRealizado').textContent = Number(stats?.realizado || 0).toLocaleString('en-US');
        }

        function cargarTabla() {
            if (tablaMantenimiento) {
                tablaMantenimiento.ajax.reload();
                return;
            }

            tablaMantenimiento = $('#tablaMantenimientoEquipos').DataTable({
                ajax: {
                    url: MANTENIMIENTO_LIST_URL,
                    type: 'GET',
                    data: function(params) {
                        params.estado = document.getElementById('filtroEstado').value;
                        params.search = document.getElementById('filtroBusqueda').value;
                    },
                    dataSrc: function(json) {
                        actualizarStats(json.stats || {});

                        if (json.setup_pending) {
                            Swal.fire('Migracion pendiente', json.message || 'La tabla aun no existe.', 'warning');
                        }

                        return json.data || [];
                    }
                },
                columns: [
                    { data: 'terminal_codigo' },
                    { data: 'nombre_agencia', defaultContent: '-' },
                    { data: 'equipo_tipo' },
                    { data: 'equipo_codigo', defaultContent: '-' },
                    { data: 'fecha_mantenimiento' },
                    { data: null, render: function(data, type, row) { return estadoBadge(row); } },
                    { data: null, render: function(data, type, row) { return diasTexto(row); } },
                    { data: 'realizado_at', defaultContent: '-' },
                    { data: null, orderable: false, searchable: false, render: acciones }
                ],
                order: [[4, 'asc']],
                pageLength: 25,
                scrollX: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                }
            });
        }

        function limpiarForm() {
            document.getElementById('mantenimientoId').value = '';
            document.getElementById('terminalCodigo').value = '';
            document.getElementById('equipoTipo').value = '';
            document.getElementById('equipoCodigo').value = '';
            document.getElementById('fechaMantenimiento').value = '';
            document.getElementById('descripcion').value = '';
            document.getElementById('observacion').value = '';
            document.getElementById('modalMantenimientoTitle').textContent = 'Nuevo mantenimiento';
        }

        function llenarForm(row) {
            document.getElementById('mantenimientoId').value = row.id;
            document.getElementById('terminalCodigo').value = row.terminal_codigo || '';
            document.getElementById('equipoTipo').value = row.equipo_tipo || '';
            document.getElementById('equipoCodigo').value = row.equipo_codigo || '';
            document.getElementById('fechaMantenimiento').value = row.fecha_mantenimiento || '';
            document.getElementById('descripcion').value = row.descripcion || '';
            document.getElementById('observacion').value = row.observacion || '';
            document.getElementById('modalMantenimientoTitle').textContent = 'Editar mantenimiento';
        }

        function payloadForm() {
            return {
                terminal_codigo: document.getElementById('terminalCodigo').value,
                equipo_tipo: document.getElementById('equipoTipo').value,
                equipo_codigo: document.getElementById('equipoCodigo').value,
                fecha_mantenimiento: document.getElementById('fechaMantenimiento').value,
                descripcion: document.getElementById('descripcion').value,
                observacion: document.getElementById('observacion').value
            };
        }

        function guardarMantenimiento(event) {
            event.preventDefault();

            const id = document.getElementById('mantenimientoId').value;
            const url = id ? MANTENIMIENTO_BASE_URL + '/' + id : MANTENIMIENTO_BASE_URL;
            const method = id ? 'PUT' : 'POST';

            $.ajax({
                url: url,
                type: method,
                headers: { 'X-CSRF-TOKEN': CSRF_MANTENIMIENTO },
                data: JSON.stringify(payloadForm()),
                contentType: 'application/json',
                success: function(response) {
                    Swal.fire('Listo', response.message || 'Guardado.', 'success');
                    modalMantenimiento.hide();
                    tablaMantenimiento.ajax.reload(null, false);
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'No fue posible guardar el mantenimiento.';
                    Swal.fire('Error', message, 'error');
                }
            });
        }

        function postAccion(url, payload, method) {
            $.ajax({
                url: url,
                type: method || 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_MANTENIMIENTO },
                data: JSON.stringify(payload || {}),
                contentType: 'application/json',
                success: function(response) {
                    Swal.fire('Listo', response.message || 'Accion completada.', 'success');
                    tablaMantenimiento.ajax.reload(null, false);
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'No fue posible completar la accion.';
                    Swal.fire('Error', message, 'error');
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            modalMantenimiento = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalMantenimiento'));
            cargarTabla();

            document.getElementById('btnBuscar').addEventListener('click', cargarTabla);
            document.getElementById('filtroBusqueda').addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    cargarTabla();
                }
            });

            document.getElementById('btnNuevo').addEventListener('click', function() {
                limpiarForm();
                modalMantenimiento.show();
            });

            document.getElementById('formMantenimiento').addEventListener('submit', guardarMantenimiento);

            document.getElementById('tablaMantenimientoEquipos').addEventListener('click', function(event) {
                const btnEditar = event.target.closest('.btnEditar');
                const btnRealizar = event.target.closest('.btnRealizar');
                const btnEliminar = event.target.closest('.btnEliminar');

                if (btnEditar) {
                    const row = tablaMantenimiento.row($(btnEditar).closest('tr')).data();
                    llenarForm(row);
                    modalMantenimiento.show();
                    return;
                }

                if (btnRealizar) {
                    Swal.fire({
                        title: 'Marcar realizado',
                        input: 'textarea',
                        inputLabel: 'Observacion',
                        inputPlaceholder: 'Detalle opcional...',
                        showCancelButton: true,
                        confirmButtonText: 'Realizar',
                        cancelButtonText: 'Cancelar'
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            postAccion(MANTENIMIENTO_BASE_URL + '/' + btnRealizar.dataset.id + '/realizar', {
                                observacion: result.value || ''
                            });
                        }
                    });
                    return;
                }

                if (btnEliminar) {
                    Swal.fire({
                        title: 'Eliminar registro',
                        text: 'Esta accion quitara el mantenimiento del reporte.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            postAccion(MANTENIMIENTO_BASE_URL + '/' + btnEliminar.dataset.id, {}, 'DELETE');
                        }
                    });
                }
            });
        });
    </script>
@endsection
