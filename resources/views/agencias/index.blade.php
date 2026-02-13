@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">

                <!-- start page title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Mantenimiento de Agencias</h4>

                            <div class="page-title-right">
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
                                <div class="row align-items-center">
                                    <div class="col-12 col-md-6 mb-3 mb-md-0">
                                        <h5 class="card-title mb-0">Lista de Agencias</h5>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-md-end">
                                            <a href="{{ route('agencias.incumplimientos') }}" class="btn btn-warning">
                                                <i class="ri-alarm-warning-line align-bottom me-1"></i> Incumplimientos
                                            </a>
                                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">
                                                <i class="ri-upload-2-line align-bottom me-1"></i> Importar
                                            </button>
                                            <a href="{{ route('agencias.export') }}" class="btn btn-info">
                                                <i class="ri-download-2-line align-bottom me-1"></i> Exportar
                                            </a>
                                            <a href="{{ route('agencias.create') }}" class="btn btn-primary">
                                                <i class="ri-add-line align-bottom me-1"></i> Nueva
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="tableAgencias" class="table table-bordered table-striped align-middle" style="width:100%; font-size: 0.875rem;">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center" style="min-width: 50px;">ID</th>
                                                <th style="min-width: 100px;">Agencia</th>
                                                <th style="min-width: 100px;">Terminal</th>
                                                <th style="min-width: 170px;">Horario AM</th>
                                                <th style="min-width: 170px;">Horario PM</th>
                                                <th style="min-width: 150px;">Nombre Agencia</th>
                                                <th style="min-width: 100px;">Sistema</th>
                                                <th style="min-width: 120px;">Ciudad</th>
                                                <th style="min-width: 150px;">Ruta</th>
                                                <th style="min-width: 120px;">Operador</th>
                                                <th style="min-width: 120px;">Coordinador</th>
                                                <th style="min-width: 120px;">Aplica incentivo</th>
                                                <th class="text-center" style="min-width: 100px;">Acciones</th>
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
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirmar eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    ¿Está seguro que desea eliminar esta agencia?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form id="deleteForm" method="POST" style="display:inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para importar -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
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
                        <div class="alert alert-info">
                            <strong>Formato del archivo:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Columna A: Agencia</li>
                                <li>Columna B: Terminal</li>
                                <li>Columna C: Horario AM (ej: 7:00 AM / 2:00 PM)</li>
                                <li>Columna D: Horario PM (ej: 2:00 PM / 9:00 PM)</li>
                                <li>Columna E: Nombre Agencia</li>
                                <li>Columna F: Sistema</li>
                                <li>Columna G: Ciudad</li>
                                <li>Columna H: Ruta</li>
                                <li>Columna I: Operador</li>
                                <li>Columna J: Coordinador</li>
                                <li>Columna K: Aplica Incentivo (SI/NO)</li>
                            </ul>
                        </div>
                        <a href="{{ route('agencias.template') }}" class="btn btn-sm btn-outline-primary">
                            <i class="ri-download-line me-1"></i> Descargar Plantilla
                        </a>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">
                            <i class="ri-upload-2-line me-1"></i> Importar
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
        var table = $('#tableAgencias').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route('agencias.list') }}',
            responsive: true,
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
                    data: 'aplica_incentivo',
                    name: 'aplica_incentivo',
                    className: 'text-center',
                    render: function(data) {
                        return data ? 'Sí' : 'No';
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

        // Manejar eliminación
        $('#tableAgencias').on('click', '.btn-delete', function() {
            var id = $(this).data('id');
            var form = $('#deleteForm');
            form.attr('action', '/agencias/' + id);
            $('#deleteModal').modal('show');
        });

        // Mostrar mensaje de éxito si existe
        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: '{{ session('success') }}',
                timer: 3000,
                showConfirmButton: false
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
    });
</script>
@endsection
