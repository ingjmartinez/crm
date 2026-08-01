@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Validador de Agencia</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('contabilidad.index') }}">Contabilidad</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('contabilidad.centro-costo') }}">Centro de Costo</a></li>
                                    <li class="breadcrumb-item active">Validador de Agencia</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-1">Cargar archivo de terminales</h5>
                        <p class="text-muted mb-0">
                            Se usan únicamente Textbox40, Banca, Grupo y Ruta. La carga analiza los datos, pero no modifica Centros de Costo.
                        </p>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('contabilidad.validador-agencia.procesar') }}"
                            enctype="multipart/form-data" class="row g-3 align-items-end" id="form-validador-agencia">
                            @csrf
                            <div class="col-lg-8">
                                <label for="archivo_csv" class="form-label">Documento CSV</label>
                                <input type="file" class="form-control" id="archivo_csv" name="archivo_csv"
                                    accept=".csv,.txt,text/csv,text/plain" required>
                                <div class="form-text">Tamaño máximo: 50 MB.</div>
                            </div>
                            <div class="col-lg-4">
                                <button type="submit" class="btn btn-primary w-100" id="btn-analizar">
                                    <i class="ri-file-search-line align-bottom me-1"></i>
                                    Cargar y validar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                @if ($carga)
                    <div class="alert alert-info d-flex flex-wrap justify-content-between gap-2">
                        <span><i class="ri-file-list-3-line me-1"></i>Archivo: <strong>{{ $carga->nombre_archivo }}</strong></span>
                        <span>Cargado: <strong>{{ $carga->created_at?->format('d/m/Y h:i A') }}</strong></span>
                    </div>

                    <div class="row g-3 mb-4">
                        @foreach ([
                            ['Filas válidas', $carga->filas_validas, 'text-primary', 'ri-file-list-3-line'],
                            ['Correctas', $carga->correctas, 'text-success', 'ri-checkbox-circle-line'],
                            ['Terminales nuevas', $carga->nuevas, 'text-info', 'ri-add-circle-line'],
                            ['Nombres diferentes', $carga->nombres_diferentes, 'text-warning', 'ri-alert-line'],
                            ['Rutas diferentes', $carga->rutas_diferentes, 'text-warning', 'ri-route-line'],
                            ['Sociedades diferentes', $carga->sociedades_diferentes, 'text-warning', 'ri-building-line'],
                            ['Conflictos', $carga->conflictos, 'text-danger', 'ri-error-warning-line'],
                        ] as [$titulo, $valor, $color, $icono])
                            <div class="col-xl col-md-4 col-sm-6">
                                <div class="card h-100 mb-0">
                                    <div class="card-body d-flex justify-content-between">
                                        <div>
                                            <p class="text-muted mb-2">{{ $titulo }}</p>
                                            <h4 class="mb-0 {{ $color }}">{{ number_format($valor) }}</h4>
                                        </div>
                                        <i class="{{ $icono }} fs-22 {{ $color }}"></i>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-1">Resultado de la validación</h5>
                        <p class="text-muted mb-0">
                            Las diferencias se resaltan. Usa los botones de cada fila para aplicar el cambio de forma controlada.
                        </p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle w-100" id="tabla-validador-agencia">
                                <thead class="table-light">
                                    <tr>
                                        <th>Terminal</th>
                                        <th>Nombre de la agencia</th>
                                        <th>Ruta</th>
                                        <th>Sociedad</th>
                                        <th>Nombre actual</th>
                                        <th>Ruta actual</th>
                                        <th>Sociedad actual</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($detalles as $detalle)
                                        @php
                                            $tieneDiferencias = str_contains($detalle->estado, 'diferente');
                                            $claseFila = $tieneDiferencias
                                                ? 'table-warning'
                                                : match ($detalle->estado) {
                                                    'nuevo' => 'table-info',
                                                    'conflicto_archivo', 'conflicto_centro' => 'table-danger',
                                                    default => '',
                                                };
                                            if ($tieneDiferencias) {
                                                $camposDiferentes = collect([
                                                    'nombre_' => 'Nombre',
                                                    'ruta_' => 'Ruta',
                                                    'sociedad_' => 'Sociedad',
                                                ])->filter(fn ($texto, $clave) => str_contains($detalle->estado, $clave))->values();
                                                $estadoTexto = $camposDiferentes->count() === 1
                                                    ? $camposDiferentes->first().' diferente'
                                                    : $camposDiferentes->join(', ', ' y ').' diferentes';
                                                $estadoClase = 'bg-warning text-dark';
                                            } else {
                                                [$estadoTexto, $estadoClase] = match ($detalle->estado) {
                                                    'correcto' => ['Correcto', 'bg-success'],
                                                    'nuevo' => ['Crear terminal', 'bg-info'],
                                                    'conflicto_archivo' => ['Conflicto en archivo', 'bg-danger'],
                                                    'conflicto_centro' => ['Conflicto en Centro de Costo', 'bg-danger'],
                                                    default => [$detalle->estado, 'bg-secondary'],
                                                };
                                            }
                                        @endphp
                                        <tr class="{{ $claseFila }}">
                                            <td>{{ $detalle->terminal }}</td>
                                            <td>{{ $detalle->nombre_agencia }}</td>
                                            <td>{{ $detalle->ruta }}</td>
                                            <td>{{ $detalle->sociedad }}</td>
                                            <td>{{ $detalle->nombre_centro_costo ?: '—' }}</td>
                                            <td>{{ $detalle->ruta_centro_costo ?: '—' }}</td>
                                            <td>{{ $detalle->sociedad_centro_costo ?: '—' }}</td>
                                            <td>
                                                @if ($tieneDiferencias)
                                                    <button type="button" class="btn btn-danger btn-incidencias text-nowrap"
                                                        data-template="incidencias-{{ $detalle->id }}"
                                                        data-terminal="{{ $detalle->terminal }}">
                                                        <i class="ri-refresh-line me-1"></i>
                                                        Datos por actualizar
                                                    </button>
                                                    <div class="d-none" id="incidencias-{{ $detalle->id }}">
                                                        @if (str_contains($detalle->estado, 'nombre_'))
                                                            <button type="button" class="btn btn-warning btn-conflicto w-100"
                                                                data-titulo="Conflicto en Nombre"
                                                                data-terminal="{{ $detalle->terminal }}"
                                                                data-esperado="{{ $detalle->nombre_agencia }}"
                                                                data-actual="{{ $detalle->nombre_centro_costo ?: 'Sin valor registrado' }}"
                                                                data-detalle="El nombre del archivo Terminales no coincide con el nombre registrado en Contabilidad.">
                                                                Conflicto en Nombre
                                                            </button>
                                                        @endif
                                                        @if (str_contains($detalle->estado, 'ruta_'))
                                                            <button type="button" class="btn btn-warning btn-conflicto w-100"
                                                                data-titulo="Conflicto en Ruta"
                                                                data-terminal="{{ $detalle->terminal }}"
                                                                data-esperado="{{ $detalle->ruta }}"
                                                                data-actual="{{ $detalle->ruta_centro_costo ?: 'Sin valor registrado' }}"
                                                                data-detalle="La ruta del archivo Terminales no coincide con la ruta registrada en Contabilidad.">
                                                                Conflicto en Ruta
                                                            </button>
                                                        @endif
                                                        @if (str_contains($detalle->estado, 'sociedad_'))
                                                            <button type="button" class="btn btn-warning btn-conflicto w-100"
                                                                data-titulo="Conflicto en Sociedad"
                                                                data-terminal="{{ $detalle->terminal }}"
                                                                data-esperado="{{ $detalle->sociedad }}"
                                                                data-actual="{{ $detalle->sociedad_centro_costo ?: 'Sin valor registrado' }}"
                                                                data-detalle="La sociedad del archivo Terminales no coincide con la sociedad registrada en Contabilidad.">
                                                                Conflicto en Sociedad
                                                            </button>
                                                        @endif
                                                    </div>
                                                @elseif ($detalle->estado === 'conflicto_centro')
                                                    <button type="button" class="btn btn-sm btn-danger btn-conflicto"
                                                        data-titulo="Conflicto en Centro de Costo"
                                                        data-terminal="{{ $detalle->terminal }}"
                                                        data-esperado="Un único Centro de Costo dentro de la compañía {{ $detalle->company_id }}"
                                                        data-actual="Nombre: {{ $detalle->nombre_centro_costo ?: 'Sin valor' }} | Ruta: {{ $detalle->ruta_centro_costo ?: 'Sin valor' }} | Sociedad: {{ $detalle->sociedad_centro_costo ?: 'Sin valor' }}"
                                                        data-detalle="{{ $detalle->observacion }}">
                                                        Conflicto en Centro de Costo
                                                    </button>
                                                @elseif ($detalle->estado === 'conflicto_archivo')
                                                    <button type="button" class="btn btn-sm btn-danger btn-conflicto"
                                                        data-titulo="Conflicto en Archivo"
                                                        data-terminal="{{ $detalle->terminal }}"
                                                        data-esperado="Una sola definición por terminal y compañía"
                                                        data-actual="La terminal aparece repetida con datos diferentes"
                                                        data-detalle="{{ $detalle->observacion }}">
                                                        Conflicto en Archivo
                                                    </button>
                                                @else
                                                    <span class="badge {{ $estadoClase }}">{{ $estadoTexto }}</span>
                                                @endif
                                            </td>
                                            <td class="text-nowrap">
                                                @if ($detalle->estado === 'nuevo' || str_contains($detalle->estado, 'diferente'))
                                                    <button type="button"
                                                        class="btn btn-sm {{ $detalle->estado === 'nuevo' ? 'btn-info' : 'btn-warning' }} btn-aplicar"
                                                        data-url="{{ route('contabilidad.validador-agencia.aplicar', $detalle) }}"
                                                        data-terminal="{{ $detalle->terminal }}"
                                                        data-nombre="{{ $detalle->nombre_agencia }}"
                                                        data-ruta="{{ $detalle->ruta }}"
                                                        data-sociedad="{{ $detalle->sociedad }}"
                                                        data-accion="{{ $detalle->estado === 'nuevo' ? 'crear' : 'actualizar' }}">
                                                        <i class="{{ $detalle->estado === 'nuevo' ? 'ri-add-line' : 'ri-edit-line' }} me-1"></i>
                                                        {{ $detalle->estado === 'nuevo' ? 'Crear' : 'Actualizar' }}
                                                    </button>
                                                @endif
                                                <button type="button" class="btn btn-sm btn-soft-secondary btn-historial"
                                                    data-terminal="{{ $detalle->terminal }}"
                                                    data-company="{{ $detalle->company_id }}"
                                                    data-url="{{ route('contabilidad.validador-agencia.historial', [$detalle->company_id, $detalle->terminal_normalizada]) }}">
                                                    <i class="ri-history-line me-1"></i>Historial
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-historial-agencia" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Historial de la terminal <span id="historial-terminal"></span></h5>
                        <p class="text-muted mb-0" id="historial-resumen"></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Acción</th>
                                    <th>Campo</th>
                                    <th>Valor anterior</th>
                                    <th>Valor nuevo</th>
                                    <th>Usuario</th>
                                    <th>Archivo</th>
                                    <th>Observación</th>
                                </tr>
                            </thead>
                            <tbody id="historial-body"></tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <button type="button" class="btn btn-soft-primary d-none" id="btn-historial-completo">
                            Ver historial completo
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-conflicto-agencia" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="conflicto-titulo">Detalle del conflicto</h5>
                        <p class="text-muted mb-0">Terminal <strong id="conflicto-terminal"></strong></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <p class="text-muted mb-1">Valor esperado — Archivo Terminales</p>
                                <div class="fw-semibold" id="conflicto-esperado"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <p class="text-muted mb-1">Valor actual — Contabilidad</p>
                                <div class="fw-semibold" id="conflicto-actual"></div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-warning mb-0" id="conflicto-detalle"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-incidencias-agencia" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Datos por actualizar</h5>
                        <p class="text-muted mb-0">Terminal <strong id="incidencias-terminal"></strong></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Selecciona una incidencia para ver la comparación:</p>
                    <div class="d-grid gap-2" id="incidencias-contenido"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const csrfToken = @json(csrf_token());
            const form = document.getElementById('form-validador-agencia');
            let historialActual = [];
            let historialCompleto = false;

            if ($.fn.DataTable && document.querySelector('#tabla-validador-agencia')) {
                $('#tabla-validador-agencia').DataTable({
                    pageLength: 25,
                    order: [[7, 'asc'], [0, 'asc']],
                    dom: 'Bfrtip',
                    buttons: [
                        {
                            extend: 'excel',
                            text: '<i class="ri-file-excel-2-line me-1"></i>Descargar Excel',
                            title: 'Validador de Agencia',
                            exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] }
                        }
                    ],
                    language: {
                        search: 'Buscar:',
                        lengthMenu: 'Mostrar _MENU_ filas',
                        info: 'Mostrando _START_ a _END_ de _TOTAL_ filas',
                        infoEmpty: 'Sin resultados',
                        zeroRecords: 'No se encontraron coincidencias',
                        paginate: { previous: 'Anterior', next: 'Siguiente' }
                    }
                });
            }

            form?.addEventListener('submit', async function (event) {
                event.preventDefault();
                const archivo = document.getElementById('archivo_csv').files[0];
                if (!archivo) {
                    Swal.fire('Archivo requerido', 'Selecciona el CSV que deseas validar.', 'warning');
                    return;
                }

                const resultado = await Swal.fire({
                    title: '¿Analizar este archivo?',
                    text: 'Se compararán sus terminales con los Centros de Costo. Todavía no se aplicarán cambios.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, analizar',
                    cancelButtonText: 'Cancelar'
                });

                if (resultado.isConfirmed) {
                    Swal.fire({
                        title: 'Analizando terminales',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => Swal.showLoading()
                    });
                    form.submit();
                }
            });

            document.addEventListener('click', async function (event) {
                const botonIncidencias = event.target.closest('.btn-incidencias');
                if (botonIncidencias) {
                    mostrarIncidencias(botonIncidencias);
                    return;
                }

                const botonConflicto = event.target.closest('.btn-conflicto');
                if (botonConflicto) {
                    mostrarConflicto(botonConflicto);
                    return;
                }

                const botonAplicar = event.target.closest('.btn-aplicar');
                if (botonAplicar) {
                    await aplicarCambio(botonAplicar);
                    return;
                }

                const botonHistorial = event.target.closest('.btn-historial');
                if (botonHistorial) {
                    await cargarHistorial(botonHistorial);
                }
            });

            document.getElementById('btn-historial-completo')?.addEventListener('click', function () {
                historialCompleto = !historialCompleto;
                renderHistorial();
            });

            function mostrarConflicto(boton) {
                document.getElementById('conflicto-titulo').textContent = boton.dataset.titulo;
                document.getElementById('conflicto-terminal').textContent = boton.dataset.terminal;
                document.getElementById('conflicto-esperado').textContent = boton.dataset.esperado;
                document.getElementById('conflicto-actual').textContent = boton.dataset.actual;
                document.getElementById('conflicto-detalle').textContent = boton.dataset.detalle;
                const modalIncidenciasElement = document.getElementById('modal-incidencias-agencia');
                const modalIncidencias = bootstrap.Modal.getInstance(modalIncidenciasElement);
                const abrirDetalle = () => bootstrap.Modal
                    .getOrCreateInstance(document.getElementById('modal-conflicto-agencia'))
                    .show();

                if (modalIncidenciasElement.classList.contains('show') && modalIncidencias) {
                    modalIncidenciasElement.addEventListener('hidden.bs.modal', abrirDetalle, { once: true });
                    modalIncidencias.hide();
                } else {
                    abrirDetalle();
                }
            }

            function mostrarIncidencias(boton) {
                const template = document.getElementById(boton.dataset.template);
                document.getElementById('incidencias-terminal').textContent = boton.dataset.terminal;
                document.getElementById('incidencias-contenido').innerHTML = template?.innerHTML || '';
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-incidencias-agencia')).show();
            }

            async function aplicarCambio(boton) {
                const esCreacion = boton.dataset.accion === 'crear';
                const resultado = await Swal.fire({
                    title: esCreacion ? '¿Crear esta terminal?' : '¿Actualizar los datos?',
                    html: `<strong>${escapeHtml(boton.dataset.terminal)}</strong><br>${escapeHtml(boton.dataset.nombre)}<br>Ruta: ${escapeHtml(boton.dataset.ruta)}<br>Sociedad: ${escapeHtml(boton.dataset.sociedad)}`,
                    input: 'textarea',
                    inputLabel: 'Observación opcional',
                    inputPlaceholder: 'Motivo o referencia del cambio',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: esCreacion ? 'Crear terminal' : 'Actualizar nombre',
                    cancelButtonText: 'Cancelar'
                });

                if (!resultado.isConfirmed) {
                    return;
                }

                Swal.fire({
                    title: 'Aplicando cambio',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });

                try {
                    const response = await fetch(boton.dataset.url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({ observacion: resultado.value || null })
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'No se pudo aplicar el cambio.');
                    }

                    await Swal.fire('Cambio aplicado', data.message, 'success');
                    window.location.reload();
                } catch (error) {
                    Swal.fire('Error', error.message, 'error');
                }
            }

            async function cargarHistorial(boton) {
                document.getElementById('historial-terminal').textContent = boton.dataset.terminal;
                document.getElementById('historial-resumen').textContent = `Empresa ${boton.dataset.company}`;
                document.getElementById('historial-body').innerHTML =
                        '<tr><td colspan="8" class="text-center py-4">Cargando historial...</td></tr>';
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-historial-agencia')).show();

                try {
                    const response = await fetch(boton.dataset.url, { headers: { 'Accept': 'application/json' } });
                    const data = await response.json();
                    if (!response.ok) {
                        throw new Error(data.message || 'No se pudo consultar el historial.');
                    }

                    historialActual = data.data || [];
                    historialCompleto = false;
                    renderHistorial();
                } catch (error) {
                    document.getElementById('historial-body').innerHTML =
                        `<tr><td colspan="8" class="text-center text-danger py-4">${escapeHtml(error.message)}</td></tr>`;
                }
            }

            function renderHistorial() {
                const body = document.getElementById('historial-body');
                const botonCompleto = document.getElementById('btn-historial-completo');
                const cambios = historialCompleto ? historialActual : historialActual.slice(0, 3);

                if (!cambios.length) {
                    body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Esta terminal aún no tiene cambios registrados.</td></tr>';
                } else {
                    body.innerHTML = cambios.map(cambio => `
                        <tr>
                            <td>${escapeHtml(cambio.fecha || '')}</td>
                            <td>${escapeHtml(cambio.accion || '')}</td>
                            <td>${escapeHtml(cambio.campo === 'id_grupo' ? 'Ruta' : (cambio.campo === 'id_sociedad' ? 'Sociedad' : 'Nombre de agencia'))}</td>
                            <td>${escapeHtml(cambio.valor_anterior || '—')}</td>
                            <td>${escapeHtml(cambio.valor_nuevo || '—')}</td>
                            <td>${escapeHtml(cambio.usuario || '')}</td>
                            <td>${escapeHtml(cambio.archivo_origen || '')}</td>
                            <td>${escapeHtml(cambio.observacion || '—')}</td>
                        </tr>
                    `).join('');
                }

                botonCompleto.classList.toggle('d-none', historialActual.length <= 3);
                botonCompleto.textContent = historialCompleto ? 'Mostrar solo los últimos 3' : `Ver historial completo (${historialActual.length})`;
            }

            function escapeHtml(value) {
                const div = document.createElement('div');
                div.textContent = String(value ?? '');
                return div.innerHTML;
            }
        });
    </script>
@endsection
