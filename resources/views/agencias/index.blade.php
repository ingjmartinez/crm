@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">

                <!-- start page title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-flex flex-column flex-md-row align-items-start align-md-items-center justify-content-between">
                            <h4 class="mb-3 mb-md-0">Mantenimiento de Agencias</h4>

                            <div class="page-title-right w-100 w-md-auto">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="javascript: void(0);">Administración</a></li>
                                    <li class="breadcrumb-item active">Agencias</li>
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
                                <div class="row align-items-start align-md-items-center">
                                    <div class="col-12 col-md-6 mb-3 mb-md-0">
                                        <h5 class="card-title mb-0">Lista de Agencias</h5>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="row g-2">
                                            <div class="col-6 col-md-3 d-grid">
                                                <a href="{{ route('agencias.incumplimientos') }}" class="btn btn-warning btn-sm">
                                                    <i class="ri-alarm-warning-line align-bottom me-1"></i><span class="d-none d-md-inline">Incumplimientos</span><span class="d-md-none">Incump.</span>
                                                </a>
                                            </div>
                                            <div class="col-6 col-md-3 d-grid">
                                                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#importModal">
                                                    <i class="ri-upload-2-line align-bottom me-1"></i><span class="d-none d-md-inline">Importar</span><span class="d-md-none">Imp.</span>
                                                </button>
                                            </div>
                                            <div class="col-6 col-md-3 d-grid">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#massUpdateModal">
                                                    <i class="ri-refresh-line align-bottom me-1"></i><span class="d-none d-md-inline">Actualizar masiva</span><span class="d-md-none">Act.</span>
                                                </button>
                                            </div>
                                            <div class="col-6 col-md-3 d-grid">
                                                <a href="{{ route('agencias.export') }}" class="btn btn-info btn-sm">
                                                    <i class="ri-download-2-line align-bottom me-1"></i><span class="d-none d-md-inline">Exportar</span><span class="d-md-none">Exp.</span>
                                                </a>
                                            </div>
                                            <div class="col-6 col-md-3 d-grid">
                                                <a href="{{ route('agencias.create') }}" class="btn btn-primary btn-sm">
                                                    <i class="ri-add-line align-bottom me-1"></i><span class="d-none d-md-inline">Nueva</span><span class="d-md-none">+</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-2 p-md-3">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div>
                                            <small class="text-muted d-block">Agencias activas</small>
                                            <h5 class="mb-0 text-success" id="countAgenciasActivas">0</h5>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">Agencias inactivas</small>
                                            <h5 class="mb-0 text-danger" id="countAgenciasInactivas">0</h5>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnFiltroEstadoTodos">Todos</button>
                                        <button type="button" class="btn btn-sm btn-outline-success" id="btnFiltroEstadoActivos">Activas</button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" id="btnFiltroEstadoInactivos">Inactivas</button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table id="tableAgencias" class="table table-bordered table-striped align-middle table-sm" style="width:100%;">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center" style="min-width: 50px;">ID</th>
                                                <th style="min-width: 80px;">Agencia</th>
                                                <th style="min-width: 80px;">Terminal</th>
                                                <th style="min-width: 130px;">Horario AM</th>
                                                <th style="min-width: 130px;">Horario PM</th>
                                                <th style="min-width: 120px;">Nombre</th>
                                                <th style="min-width: 80px;">Sistema</th>
                                                <th style="min-width: 100px;">Ciudad</th>
                                                <th style="min-width: 100px;">Ruta</th>
                                                <th style="min-width: 100px;">Operador</th>
                                                <th style="min-width: 100px;">Coordinador</th>
                                                <th style="min-width: 90px;">Estatus</th>
                                                <th style="min-width: 80px;">Incentivo</th>
                                                <th class="text-center" style="min-width: 80px;">Acciones</th>
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

    <!-- Modal para eliminar -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirmar eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    ¿Está seguro que desea eliminar esta agencia?
                </div>
                <div class="modal-footer d-flex gap-2">
                    <button type="button" class="btn btn-secondary flex-grow-1" data-bs-dismiss="modal">Cancelar</button>
                    <form id="deleteForm" method="POST" style="display:inline; flex-grow: 1;" class="w-100">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-100">Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para importar -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('agencias.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="importModalLabel">Importar Agencias desde Excel</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="file" class="form-label">Seleccione el archivo Excel</label>
                            <input type="file" class="form-control" id="file" name="file" accept=".xlsx,.xls,.csv" required>
                            <div class="form-text">Formatos aceptados: .xlsx, .xls, .csv</div>
                        </div>
                        <div class="alert alert-info mb-0">
                            <strong class="d-block mb-2">Formato del archivo:</strong>
                            <ul class="mb-0 ps-3">
                                <li>Columna A: Agencia</li>
                                <li>Columna B: Terminal</li>
                                <li>Columna C: Horario AM</li>
                                <li>Columna D: Horario PM</li>
                                <li>Columna E: Nombre Agencia</li>
                                <li>Columna F: Sistema</li>
                                <li>Columna G: Ciudad</li>
                                <li>Columna H: Ruta</li>
                                <li>Columna I: Operador</li>
                                <li>Columna J: Coordinador</li>
                                <li>Columna K: Estatus (1 Activo / 0 Inactivo)</li>
                                <li>Columna L: Aplica Incentivo (SI/NO)</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer d-flex gap-2">
                        <a href="{{ route('agencias.template') }}" class="btn btn-outline-primary btn-sm">
                            <i class="ri-download-line me-1"></i>Plantilla
                        </a>
                        <button type="button" class="btn btn-secondary flex-grow-1" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success flex-grow-1">
                            <i class="ri-upload-2-line me-1"></i>Importar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para actualización masiva -->
    <div class="modal fade" id="massUpdateModal" tabindex="-1" aria-labelledby="massUpdateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('agencias.mass-update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="massUpdateModalLabel">Actualización masiva de Agencias</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="mass_update_file" class="form-label">Seleccione el archivo Excel</label>
                            <input type="file" class="form-control" id="mass_update_file" name="file" accept=".xlsx,.xls,.csv" required>
                            <div class="form-text">Formatos aceptados: .xlsx, .xls, .csv</div>
                        </div>

                        <div class="mb-3">
                            <a href="{{ route('agencias.mass-update-template') }}" class="btn btn-outline-primary btn-sm">
                                <i class="ri-download-line me-1"></i>Descargar plantilla de actualización
                            </a>
                            <div class="form-text mt-1">Use esta plantilla para actualizar solo los campos que necesite por agencia.</div>
                        </div>

                        <div class="alert alert-warning mb-0">
                            <strong class="d-block mb-2">Reglas de actualización:</strong>
                            <ul class="mb-2 ps-3">
                                <li>Para ubicar la agencia, incluya al menos una columna: ID, Terminal o Agencia.</li>
                                <li>Solo se actualizan los campos que tengan valor en cada fila.</li>
                                <li>Si una celda viene vacía, ese campo no se modifica.</li>
                                <li>Puede actualizar 1, 2 o más campos en el mismo archivo.</li>
                            </ul>
                            <small class="text-muted">Campos soportados: Agencia, Terminal, Horario AM, Horario PM, Nombre Agencia, Sistema, Ciudad, Ruta, Operador, Coordinador, Estatus, Aplica Incentivo.</small>
                        </div>
                    </div>
                    <div class="modal-footer d-flex gap-2">
                        <button type="button" class="btn btn-secondary flex-grow-1" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="ri-refresh-line me-1"></i>Actualizar masiva
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
    $(document).ready(function() {
        var estadoFiltro = 'todos';
        var countAgenciasActivas = $('#countAgenciasActivas');
        var countAgenciasInactivas = $('#countAgenciasInactivas');

        function aplicarEstadoBotones() {
            $('#btnFiltroEstadoTodos').removeClass('btn-secondary').addClass('btn-outline-secondary');
            $('#btnFiltroEstadoActivos').removeClass('btn-success').addClass('btn-outline-success');
            $('#btnFiltroEstadoInactivos').removeClass('btn-danger').addClass('btn-outline-danger');

            if (estadoFiltro === 'activo') {
                $('#btnFiltroEstadoActivos').removeClass('btn-outline-success').addClass('btn-success');
                return;
            }

            if (estadoFiltro === 'inactivo') {
                $('#btnFiltroEstadoInactivos').removeClass('btn-outline-danger').addClass('btn-danger');
                return;
            }

            $('#btnFiltroEstadoTodos').removeClass('btn-outline-secondary').addClass('btn-secondary');
        }

        // Configuración responsive de DataTables
        var responsiveColumns = [
            { targets: 4, visible: false },  // Horario PM
            { targets: 9, visible: false },  // Operador
            { targets: 10, visible: false }  // Coordinador
        ];

        // En móvil, ocultar más columnas
        if ($(window).width() < 768) {
            responsiveColumns = [
                { targets: 3, visible: false },  // Horario AM
                { targets: 4, visible: false },  // Horario PM
                { targets: 6, visible: false },  // Sistema
                { targets: 8, visible: false },  // Ruta
                { targets: 9, visible: false },  // Operador
                { targets: 10, visible: false }  // Coordinador
            ];
        }

        var table = $('#tableAgencias').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route('agencias.list') }}',
                data: function(d) {
                    d.estatus_filter = estadoFiltro;
                },
                dataSrc: function(json) {
                    countAgenciasActivas.text((json.total_activas || 0).toLocaleString('es-DO'));
                    countAgenciasInactivas.text((json.total_inactivas || 0).toLocaleString('es-DO'));
                    return json.data || [];
                }
            },
            responsive: true,
            columnDefs: responsiveColumns,
            scrollX: true,
            columns: [
                { data: 'id', name: 'id', className: 'text-center' },
                { data: 'agencia', name: 'agencia' },
                { data: 'terminal', name: 'terminal', defaultContent: '-' },
                { data: 'horario_am', name: 'horario_am', defaultContent: '-' },
                { data: 'horario_pm', name: 'horario_pm', defaultContent: '-' },
                { data: 'nombre_agencia', name: 'nombre_agencia', defaultContent: '-' },
                { data: 'sistema', name: 'sistema', defaultContent: '-' },
                { data: 'ciudad', name: 'ciudad', defaultContent: '-' },
                { data: 'ruta', name: 'ruta', defaultContent: '-' },
                { data: 'operador', name: 'operador', defaultContent: '-' },
                { data: 'coordinador', name: 'coordinador', defaultContent: '-' },
                {
                    data: 'estatus',
                    name: 'estatus',
                    className: 'text-center',
                    render: function(data) {
                        return Number(data) === 1
                            ? '<span class="badge bg-success">Activo</span>'
                            : '<span class="badge bg-danger">Inactivo</span>';
                    }
                },
                {
                    data: 'aplica_incentivo',
                    name: 'aplica_incentivo',
                    className: 'text-center',
                    render: function(data) {
                        return data ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function(data, type, row) {
                        return `
                            <div class="d-flex gap-1 justify-content-center flex-nowrap">
                                <a href="/agencias/${row.id}/edit" class="btn btn-sm btn-success" title="Editar">
                                    <i class="ri-pencil-line"></i>
                                </a>
                                <button class="btn btn-sm btn-danger btn-delete" data-id="${row.id}" title="Eliminar">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </div>
                        `;
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
            },
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
        });

        aplicarEstadoBotones();

        $('#btnFiltroEstadoTodos').on('click', function() {
            estadoFiltro = 'todos';
            aplicarEstadoBotones();
            table.ajax.reload();
        });

        $('#btnFiltroEstadoActivos').on('click', function() {
            estadoFiltro = 'activo';
            aplicarEstadoBotones();
            table.ajax.reload();
        });

        $('#btnFiltroEstadoInactivos').on('click', function() {
            estadoFiltro = 'inactivo';
            aplicarEstadoBotones();
            table.ajax.reload();
        });

        // Manejar eliminación
        $('#tableAgencias').on('click', '.btn-delete', function() {
            var id = $(this).data('id');
            var form = $('#deleteForm');
            form.attr('action', '/agencias/' + id);
            $('#deleteModal').modal('show');
        });

        // Mostrar mensaje de éxito si existe
        @if(session('success') && !session('mass_update_result'))
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: '{{ session('success') }}',
                timer: 3000,
                showConfirmButton: false
            });
        @endif

        // Resumen de actualización masiva con conteo
        @if(session('mass_update_result'))
            const massUpdateResult = @json(session('mass_update_result'));
            Swal.fire({
                icon: 'info',
                title: 'Actualización masiva finalizada',
                html: `
                    <div class="text-start">
                        <p class="mb-2">Resumen del archivo procesado:</p>
                        <ul class="mb-0 ps-3">
                            <li>Filas procesadas: <strong>${Number(massUpdateResult.procesadas || 0).toLocaleString('es-DO')}</strong></li>
                            <li>Agencias actualizadas: <strong>${Number(massUpdateResult.actualizadas || 0).toLocaleString('es-DO')}</strong></li>
                            <li>Sin cambios: <strong>${Number(massUpdateResult.sin_cambios || 0).toLocaleString('es-DO')}</strong></li>
                            <li>No encontradas: <strong>${Number(massUpdateResult.no_encontradas || 0).toLocaleString('es-DO')}</strong></li>
                            <li>Filas inválidas: <strong>${Number(massUpdateResult.invalidas || 0).toLocaleString('es-DO')}</strong></li>
                        </ul>
                    </div>
                `,
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#0ab39c'
            });
        @endif

        // Mostrar mensaje de error si existe
        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '{{ session('error') }}'
            });
        @endif

        // Mostrar errores de validación
        @if($errors->any())
            Swal.fire({
                icon: 'error',
                title: 'Error de validación',
                html: `{!! implode('<br>', $errors->all()) !!}`
            });
        @endif

        // Reajustar columnas en resize
        $(window).on('resize', function() {
            table.columns.adjust().draw();
        });
    });
</script>
@endsection
