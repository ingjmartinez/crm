@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Seguimiento Cruce de Usuarios</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('recursos-humanos.index') }}">Recursos Humanos</a></li>
                                    <li class="breadcrumb-item active">Seguimiento Cruce de Usuarios</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                @if ($setupPending)
                    <div class="alert alert-warning">
                        La tabla de seguimiento aun no existe. Ejecuta las migraciones para activar este flujo.
                    </div>
                @endif

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="card border mb-0">
                            <div class="card-body">
                                <div class="text-muted text-uppercase small fw-semibold">Pendientes</div>
                                <div class="fs-2 fw-semibold text-warning" id="statPendiente">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border mb-0">
                            <div class="card-body">
                                <div class="text-muted text-uppercase small fw-semibold">En gestion</div>
                                <div class="fs-2 fw-semibold text-info" id="statEnGestion">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border mb-0">
                            <div class="card-body">
                                <div class="text-muted text-uppercase small fw-semibold">Finalizados</div>
                                <div class="fs-2 fw-semibold text-success" id="statFinalizado">0</div>
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
                                    <option value="pendiente">Pendiente</option>
                                    <option value="en_gestion">En gestion</option>
                                    <option value="finalizado">Finalizado</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="filtroBusqueda" class="form-label">Busqueda</label>
                                <input type="text" id="filtroBusqueda" class="form-control" placeholder="Cedula, nombre, agencia o estatus">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-primary w-100" id="btnFiltrar">
                                    <i class="ri-search-line"></i> Buscar
                                </button>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-soft-secondary w-100" id="btnLimpiar">
                                    Limpiar
                                </button>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-info w-100" id="btnIniciarMasivo">
                                    <i class="ri-play-circle-line"></i> Iniciar masivo
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="tablaSeguimientoCruce" class="table table-bordered table-striped align-middle w-100">
                                <thead>
                                    <tr>
                                        <th>Caso</th>
                                        <th>Cedula</th>
                                        <th>Nombre</th>
                                        <th>Estatus reporte</th>
                                        <th>Detalle</th>
                                        <th>Ultima venta</th>
                                        <th>Estado</th>
                                        <th>Inicio gestion</th>
                                        <th>Finalizado</th>
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
@endsection

@section('script')
    <script>
        const SEGUIMIENTO_LIST_URL = '{{ route('recursos-humanos.cruce-usuarios.list') }}';
        const SEGUIMIENTO_INICIAR_URL = '{{ url('/recursos-humanos/seguimiento-cruce-usuarios') }}/';
        const SEGUIMIENTO_INICIAR_MASIVO_URL = '{{ route('recursos-humanos.cruce-usuarios.iniciar-masivo') }}';
        const CSRF_TOKEN_CRUCE = '{{ csrf_token() }}';
        let tablaSeguimiento;

        function escapeHtmlCruce(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function estadoLabel(estado) {
            if (estado === 'finalizado') {
                return '<span class="badge bg-success">Finalizado</span>';
            }

            if (estado === 'en_gestion') {
                return '<span class="badge bg-info">En gestion</span>';
            }

            return '<span class="badge bg-warning text-dark">Pendiente</span>';
        }

        function renderAcciones(data, type, row) {
            if (row.estado === 'finalizado') {
                return '<span class="text-muted">Completado</span>';
            }

            const iniciar = row.estado === 'pendiente'
                ? '<button type="button" class="btn btn-sm btn-outline-info btnIniciar" data-id="' + row.id + '">Iniciar</button>'
                : '';

            return '<div class="d-flex gap-2">' +
                iniciar +
                '<button type="button" class="btn btn-sm btn-success btnFinalizar" data-id="' + row.id + '">Finalizar</button>' +
            '</div>';
        }

        function actualizarStats(stats) {
            document.getElementById('statPendiente').textContent = Number(stats?.pendiente || 0).toLocaleString('en-US');
            document.getElementById('statEnGestion').textContent = Number(stats?.en_gestion || 0).toLocaleString('en-US');
            document.getElementById('statFinalizado').textContent = Number(stats?.finalizado || 0).toLocaleString('en-US');
        }

        function cargarTabla() {
            if (tablaSeguimiento) {
                tablaSeguimiento.ajax.reload();
                return;
            }

            tablaSeguimiento = $('#tablaSeguimientoCruce').DataTable({
                ajax: {
                    url: SEGUIMIENTO_LIST_URL,
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
                    { data: 'codigo' },
                    { data: 'cedula' },
                    { data: 'nombre_completo', defaultContent: '-' },
                    { data: 'estatus_origen' },
                    {
                        data: 'detalle',
                        render: function(data) {
                            return '<span title="' + escapeHtmlCruce(data || '') + '">' + escapeHtmlCruce(data || '-') + '</span>';
                        }
                    },
                    { data: 'ultima_fecha_venta', defaultContent: '-' },
                    { data: 'estado', render: estadoLabel },
                    { data: 'gestion_inicio_at', defaultContent: '-' },
                    { data: 'finalizado_at', defaultContent: '-' },
                    { data: null, orderable: false, searchable: false, render: renderAcciones }
                ],
                order: [[5, 'desc']],
                pageLength: 25,
                scrollX: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                }
            });
        }

        function postAccion(url, payload, successMessage) {
            Swal.fire({
                title: 'Procesando...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            $.ajax({
                url: url,
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN_CRUCE
                },
                data: JSON.stringify(payload || {}),
                contentType: 'application/json',
                success: function(response) {
                    Swal.fire('Listo', response.message || successMessage, 'success');
                    tablaSeguimiento.ajax.reload(null, false);
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'No fue posible completar la accion.';
                    Swal.fire('Error', message, 'error');
                }
            });
        }

        function iniciarMasivo() {
            const estado = document.getElementById('filtroEstado').value;
            const search = document.getElementById('filtroBusqueda').value;

            if (estado && estado !== 'pendiente') {
                Swal.fire('Filtro no valido', 'El inicio masivo solo aplica a casos pendientes.', 'info');
                return;
            }

            Swal.fire({
                title: 'Iniciar gestion masiva',
                text: search
                    ? 'Se iniciaran los casos pendientes que coincidan con la busqueda actual.'
                    : 'Se iniciaran todos los casos pendientes.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Iniciar',
                cancelButtonText: 'Cancelar'
            }).then(function(result) {
                if (!result.isConfirmed) {
                    return;
                }

                postAccion(SEGUIMIENTO_INICIAR_MASIVO_URL, {
                    estado: estado,
                    search: search
                }, 'Gestion masiva iniciada.');
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            cargarTabla();

            document.getElementById('btnFiltrar').addEventListener('click', cargarTabla);
            document.getElementById('btnIniciarMasivo').addEventListener('click', iniciarMasivo);

            document.getElementById('btnLimpiar').addEventListener('click', function() {
                document.getElementById('filtroEstado').value = '';
                document.getElementById('filtroBusqueda').value = '';
                cargarTabla();
            });

            document.getElementById('filtroBusqueda').addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    cargarTabla();
                }
            });

            document.getElementById('tablaSeguimientoCruce').addEventListener('click', function(event) {
                const btnIniciar = event.target.closest('.btnIniciar');
                const btnFinalizar = event.target.closest('.btnFinalizar');

                if (btnIniciar) {
                    postAccion(SEGUIMIENTO_INICIAR_URL + btnIniciar.dataset.id + '/iniciar', {}, 'Gestion iniciada.');
                    return;
                }

                if (btnFinalizar) {
                    Swal.fire({
                        title: 'Finalizar caso',
                        input: 'textarea',
                        inputLabel: 'Observacion',
                        inputPlaceholder: 'Detalle opcional de cierre...',
                        showCancelButton: true,
                        confirmButtonText: 'Finalizar',
                        cancelButtonText: 'Cancelar'
                    }).then(function(result) {
                        if (!result.isConfirmed) {
                            return;
                        }

                        postAccion(
                            SEGUIMIENTO_INICIAR_URL + btnFinalizar.dataset.id + '/finalizar',
                            { observacion: result.value || '' },
                            'Caso finalizado.'
                        );
                    });
                }
            });
        });
    </script>
@endsection
