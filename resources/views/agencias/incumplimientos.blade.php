@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">

                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Incumplimiento de Horario por Agencia</h4>

                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('agencias.index') }}">Agencias</a></li>
                                    <li class="breadcrumb-item active">Incumplimientos</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-3">
                                        <label for="fecha" class="form-label mb-1">Fecha</label>
                                        <input type="date" id="fecha" class="form-control" value="{{ now()->toDateString() }}">
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check form-switch mt-4">
                                            <input class="form-check-input" type="checkbox" id="soloIncumplidas" checked>
                                            <label class="form-check-label" for="soloIncumplidas">Solo incumplidas</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <button class="btn btn-primary" id="btnConsultar">
                                            <i class="ri-search-line me-1"></i> Consultar
                                        </button>
                                        <a href="{{ route('agencias.index') }}" class="btn btn-light">
                                            <i class="ri-arrow-left-line me-1"></i> Volver
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info mb-3" id="resumenBox">
                                    Selecciona una fecha para consultar.
                                </div>

                                <div class="table-responsive">
                                    <table id="tableIncumplimientos" class="table table-bordered table-striped align-middle" style="width:100%; font-size:0.85rem;">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Agencia</th>
                                                <th>Entrada AM</th>
                                                <th>Salida AM</th>
                                                <th>Entrada PM</th>
                                                <th>Salida PM</th>
                                                <th>Entrada  AM Real</th>
                                                <th>Salida AM Real</th>
                                                <th>Entrada PM Real</th>
                                                <th>Salida PM Real</th>
                                                <th>Min. Tarde</th>
                                                <th>Min. Salida Antes</th>
                                                <th>Fuente</th>
                                                <th>Estado</th>
                                                <th>Observaciones</th>
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

                <div class="modal fade" id="modalCrearTareaInc" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title"><i class="ri-task-line me-2"></i>Crear tarea desde incumplimiento</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="formTareaIncumplimiento">
                                    <input type="hidden" id="inc-row-data">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Título <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="inc-tarea-titulo" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Departamento <span class="text-danger">*</span></label>
                                            <select class="form-select" id="inc-tarea-departamento" required>
                                                <option value="">Cargando departamentos...</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Prioridad <span class="text-danger">*</span></label>
                                            <select class="form-select" id="inc-tarea-prioridad">
                                                <option value="baja">🟢 Baja</option>
                                                <option value="media" selected>🔵 Media</option>
                                                <option value="alta">🟡 Alta</option>
                                                <option value="critica">🔴 Crítica</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Asignar a</label>
                                            <select class="form-select" id="inc-tarea-asignado">
                                                <option value="">Cargando usuarios...</option>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Descripción</label>
                                            <textarea class="form-control" id="inc-tarea-descripcion" rows="3"></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Fecha inicio <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="inc-tarea-inicio" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Fecha fin <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="inc-tarea-fin" required>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-primary" id="btnGuardarTareaInc">
                                    <i class="ri-save-line me-1"></i> Guardar tarea
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <footer class="footer">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <script>document.write(new Date().getFullYear())</script> © CRM.
                    </div>
                </div>
            </div>
        </footer>
    </div>
@endsection

@section('script')
<script>
    let table;
    const CSRF = '{{ csrf_token() }}';
    const URL_TAREAS = '{{ url('/tareas') }}';
    const URL_DEPTOS = '{{ url('/tareas/departamentos') }}';
    const URL_USUARIOS_TAREAS = '{{ url('/tareas/usuarios') }}';

    function cargarDepartamentosTareas() {
        $.getJSON(URL_DEPTOS, function(data) {
            let options = '<option value="">Seleccionar...</option>';
            (data || []).forEach(function(d) {
                options += `<option value="${d.id}">${d.nombre}</option>`;
            });
            $('#inc-tarea-departamento').html(options);
        }).fail(function() {
            $('#inc-tarea-departamento').html('<option value="">No se pudieron cargar</option>');
        });
    }

    function cargarUsuariosTareas() {
        $.getJSON(URL_USUARIOS_TAREAS, function(data) {
            let options = '<option value="">Sin asignar</option>';
            (data || []).forEach(function(u) {
                options += `<option value="${u.id}">${u.name}</option>`;
            });
            $('#inc-tarea-asignado').html(options);
        }).fail(function() {
            $('#inc-tarea-asignado').html('<option value="">No se pudieron cargar</option>');
        });
    }

    function abrirModalCrearTareaDesdeFila(row) {
        const fecha = $('#fecha').val() || '{{ now()->toDateString() }}';
        const tituloDefault = `Seguimiento incumplimiento - Agencia ${row.agencia || '-'} (${row.nombre_agencia || '-'})`;
        const descripcionDefault = `Se crea tarea de seguimiento por incumplimiento de horario.\n\n` +
            `Fecha: ${fecha}\n` +
            `Terminal: ${row.terminal || '-'}\n` +
            `Agencia: ${row.agencia || '-'}\n` +
            `Nombre: ${row.nombre_agencia || '-'}\n` +
            `Estado: ${row.estado || '-'}\n` +
            `Observaciones: ${row.observaciones || '-'}\n` +
            `Min. tarde: ${row.minutos_tarde || 0}\n` +
            `Min. salida antes: ${row.minutos_salida_antes || 0}`;

        $('#inc-row-data').val(JSON.stringify(row));
        $('#inc-tarea-titulo').val(tituloDefault);
        $('#inc-tarea-prioridad').val((row.estado === 'INCUMPLE') ? 'alta' : 'media');
        $('#inc-tarea-descripcion').val(descripcionDefault);
        $('#inc-tarea-inicio').val(fecha);
        $('#inc-tarea-fin').val(fecha);
        $('#inc-tarea-departamento').val('');
        $('#inc-tarea-asignado').val('');

        $('#modalCrearTareaInc').modal('show');
    }

    function guardarTareaDesdeIncumplimiento() {
        const payload = {
            titulo: $('#inc-tarea-titulo').val(),
            descripcion: $('#inc-tarea-descripcion').val(),
            departamento_id: $('#inc-tarea-departamento').val(),
            prioridad: $('#inc-tarea-prioridad').val(),
            fecha_inicio: $('#inc-tarea-inicio').val(),
            fecha_fin: $('#inc-tarea-fin').val(),
            asignado_id: $('#inc-tarea-asignado').val() || null,
        };

        if (!payload.titulo || !payload.departamento_id || !payload.fecha_inicio || !payload.fecha_fin) {
            Swal.fire('Campos requeridos', 'Completa título, departamento y fechas para crear la tarea.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Guardando tarea...',
            text: 'Por favor espera.',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        $.ajax({
            url: URL_TAREAS,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF },
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: function(resp) {
                $('#modalCrearTareaInc').modal('hide');
                Swal.fire('Tarea creada', resp.message || 'La tarea fue creada correctamente.', 'success');
            },
            error: function(xhr) {
                const errors = xhr?.responseJSON?.errors;
                if (errors) {
                    const msg = Object.values(errors).flat().join('<br>');
                    Swal.fire({ icon: 'error', title: 'Error de validación', html: msg });
                    return;
                }
                const errorMsg = xhr?.responseJSON?.message || 'No se pudo crear la tarea.';
                Swal.fire('Error', errorMsg, 'error');
            }
        });
    }

    async function solicitarCorreoYEnviar(row) {
        const result = await Swal.fire({
            title: 'Enviar mini reporte',
            text: 'Indica el correo destino para esta fila.',
            input: 'email',
            inputLabel: 'Correo destino',
            inputPlaceholder: 'ejemplo@correo.com',
            showCancelButton: true,
            confirmButtonText: 'Enviar correo',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#405189',
            inputValidator: (value) => {
                if (!value) {
                    return 'Debes indicar un correo destino';
                }
            }
        });

        if (!result.isConfirmed || !result.value) {
            return;
        }

        Swal.fire({
            title: 'Enviando...',
            text: 'Por favor espera un momento.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: '{{ route('agencias.incumplimientos.send-mail') }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                email: result.value,
                fecha: $('#fecha').val(),
                registro: row
            },
            success: function(resp) {
                Swal.fire('Correo enviado', resp.message || 'Mini reporte enviado correctamente.', 'success');
            },
            error: function(xhr) {
                const errorMsg = xhr?.responseJSON?.message || 'No se pudo enviar el correo.';
                Swal.fire('Error', errorMsg, 'error');
            }
        });
    }

    function cargarData() {
        const fecha = $('#fecha').val();
        const soloIncumplidas = $('#soloIncumplidas').is(':checked') ? 1 : 0;

        $.ajax({
            url: '{{ route('agencias.incumplimientos.list') }}',
            method: 'GET',
            data: {
                fecha: fecha,
                solo_incumplidas: soloIncumplidas
            },
            success: function(resp) {
                const data = resp.data || [];

                if (table) {
                    table.clear().rows.add(data).draw();
                }

                $('#resumenBox').html(
                    `<strong>Fecha:</strong> ${resp.fecha} | <strong>Total:</strong> ${resp.total} | <strong>Incumplidas:</strong> ${resp.incumplidas}`
                );
            },
            error: function() {
                Swal.fire('Error', 'No se pudo cargar la información.', 'error');
            }
        });
    }

    $(document).ready(function() {
        table = $('#tableIncumplimientos').DataTable({
            data: [],
            responsive: true,
            scrollX: true,
            columns: [
                { data: 'nombre_agencia', render: function(data, type, row) {
                    return (data || row.agencia || '-') + `<div class="text-muted fs-11">Cod: ${row.agencia || '-'}</div>`;
                }},
                { data: 'entrada_am_programada', defaultContent: '-', className: 'text-center', render: function(data) {
                    return data || '-';
                }},
                { data: 'salida_am_programada', defaultContent: '-', className: 'text-center', render: function(data) {
                    return data || '-';
                }},
                { data: 'entrada_pm_programada', defaultContent: '-', className: 'text-center', render: function(data) {
                    return data || '-';
                }},
                { data: 'salida_pm_programada', defaultContent: '-', className: 'text-center', render: function(data) {
                    return data || '-';
                }},
                { data: 'entrada_real', className: 'text-center' },
                { data: 'salida_am_real', className: 'text-center' },
                { data: 'entrada_pm_real', className: 'text-center' },
                { data: 'salida_real', className: 'text-center' },
                { data: 'minutos_tarde', className: 'text-center', render: function(data) {
                    const minutos = Math.round(parseFloat(data || 0));
                    return minutos > 0 ? `<span class="badge bg-danger">${minutos}</span>` : '<span class="badge bg-success">0</span>';
                }},
                { data: 'minutos_salida_antes', className: 'text-center', render: function(data) {
                    const minutos = Math.round(parseFloat(data || 0));
                    return minutos > 0 ? `<span class="badge bg-warning text-dark">${minutos}</span>` : '<span class="badge bg-success">0</span>';
                }},
                { data: 'fuente', className: 'text-center' },
                { data: 'estado', className: 'text-center', render: function(data) {
                    if (data === 'INCUMPLE') {
                        return '<span class="badge bg-danger">INCUMPLE</span>';
                    }
                    return '<span class="badge bg-success">CUMPLE</span>';
                }},
                { data: 'observaciones' },
                { data: null, orderable: false, searchable: false, className: 'text-center', render: function(data, type, row) {
                    const rowData = encodeURIComponent(JSON.stringify(row));
                    return `<div class="d-flex gap-1 justify-content-center">
                                <button type="button" class="btn btn-sm btn-soft-primary btnEnviarCorreo" data-row="${rowData}" title="Enviar mini reporte por correo">
                                    <i class="ri-mail-send-line me-1"></i> Enviar
                                </button>
                                <button type="button" class="btn btn-sm btn-soft-info btnCrearTarea" data-row="${rowData}" title="Crear tarea de seguimiento">
                                    <i class="ri-task-line me-1"></i> Tarea
                                </button>
                            </div>`;
                }},
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
            },
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
        });

        $('#tableIncumplimientos').on('click', '.btnEnviarCorreo', function() {
            const encoded = $(this).attr('data-row');

            if (!encoded) {
                Swal.fire('Error', 'No se encontró información para enviar.', 'error');
                return;
            }

            try {
                const row = JSON.parse(decodeURIComponent(encoded));
                solicitarCorreoYEnviar(row);
            } catch (e) {
                Swal.fire('Error', 'No se pudo procesar la fila seleccionada.', 'error');
            }
        });

        $('#tableIncumplimientos').on('click', '.btnCrearTarea', function() {
            const encoded = $(this).attr('data-row');

            if (!encoded) {
                Swal.fire('Error', 'No se encontró información para crear la tarea.', 'error');
                return;
            }

            try {
                const row = JSON.parse(decodeURIComponent(encoded));
                abrirModalCrearTareaDesdeFila(row);
            } catch (e) {
                Swal.fire('Error', 'No se pudo procesar la fila seleccionada.', 'error');
            }
        });

        $('#btnGuardarTareaInc').on('click', guardarTareaDesdeIncumplimiento);

        cargarDepartamentosTareas();
        cargarUsuariosTareas();

        $('#btnConsultar').on('click', cargarData);

        cargarData();
    });
</script>
@endsection
