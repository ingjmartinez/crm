@extends('app')

@section('content')
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0">Solicitud de Terminales</h4>
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('mantenimiento.index') }}">Mantenimiento</a></li>
                            <li class="breadcrumb-item active">Solicitud de Terminales</li>
                        </ol>
                    </div>
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            @endif
            @if (session('excelSolicitud'))
                <div class="alert alert-info d-flex justify-content-between align-items-center" role="alert">
                    <span>El Excel de la solicitud fue generado correctamente.</span>
                    <a class="btn btn-sm btn-success" href="{{ session('excelSolicitud') }}">
                        <i class="ri-file-excel-2-line me-1"></i>Descargar Excel
                    </a>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
            @endif

            <div class="row g-3 mb-4">
                <div class="col-md-6 col-xl-3">
                    <div class="card h-100 mb-0">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="avatar-sm"><span class="avatar-title rounded bg-primary-subtle text-primary fs-4"><i class="ri-computer-line"></i></span></div>
                            <div>
                                <p class="text-muted mb-1">Terminales creadas</p>
                                <h4 class="mb-0">{{ number_format($totalTerminales) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card h-100 mb-0">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="avatar-sm"><span class="avatar-title rounded bg-warning-subtle text-warning fs-4"><i class="ri-time-line"></i></span></div>
                            <div>
                                <p class="text-muted mb-1">Solicitudes por completar</p>
                                <h4 class="mb-0">{{ number_format($solicitudesPendientes) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-12 col-xl-6">
                    <div class="card h-100 mb-0">
                        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div>
                                <h5 class="mb-1">Tabla de agencias</h5>
                                <p class="text-muted mb-0">Consulta y administra las terminales que ya existen.</p>
                            </div>
                            <a class="btn btn-outline-primary" href="{{ route('agencias.index') }}">
                                <i class="ri-store-2-line me-1"></i>Ir a agencias
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Nueva solicitud para Loteka Central</h5>
                </div>
                <div class="card-body">
                    <form id="form-sugerencia" class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label" for="prefijo-seleccionado">Prefijo del código</label>
                            <div class="input-group">
                                <span class="input-group-text fw-semibold">05</span>
                                <select class="form-select" id="prefijo-seleccionado" required>
                                    @foreach ($prefijos as $prefijo)
                                        <option value="{{ $prefijo }}" @selected(old('prefijo_seleccionado', '33') === $prefijo)>{{ $prefijo }}</option>
                                    @endforeach
                                </select>
                                <span class="input-group-text text-muted">0000–9999</span>
                            </div>
                            <div class="form-text">05 es fijo; selecciona los dos dígitos siguientes.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="cantidad">Cantidad de códigos</label>
                            <input class="form-control" type="number" id="cantidad" min="1" max="500" value="{{ old('cantidad', 20) }}" required>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-primary w-100" type="submit" id="btn-sugerir">
                                <i class="ri-magic-line me-1"></i>Sugerir códigos
                            </button>
                        </div>
                    </form>

                    <div class="alert alert-danger mt-3 d-none" id="error-sugerencia" role="alert"></div>

                    <form method="POST" action="{{ route('mantenimiento.solicitudes-terminales.store') }}" id="form-confirmar" class="d-none mt-4">
                        @csrf
                        <input type="hidden" name="prefijo_seleccionado" id="confirmar-prefijo">
                        <input type="hidden" name="cantidad" id="confirmar-cantidad">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <div>
                                <h6 class="mb-1">Códigos sugeridos</h6>
                                <span class="text-muted">Revísalos antes de crear la solicitud formal.</span>
                            </div>
                            <button class="btn btn-success" type="submit" id="btn-confirmar-solicitud">
                                <i class="ri-checkbox-circle-line me-1"></i>Aprobar códigos y crear solicitud
                            </button>
                        </div>
                        <div class="border rounded p-3 bg-light">
                            <div class="d-flex flex-wrap gap-2" id="lista-sugerencias"></div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Historial de solicitudes</h5>
                    <span class="badge bg-light text-dark">{{ number_format($solicitudes->total()) }} solicitudes</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Solicitud</th>
                                    <th>Fecha</th>
                                    <th>Prefijo</th>
                                    <th>Solicitante</th>
                                    <th>Progreso</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($solicitudes as $solicitud)
                                    @php
                                        $colorEstado = match ($solicitud->estado) {
                                            'aprobada' => 'success',
                                            'parcial' => 'info',
                                            default => 'warning',
                                        };
                                        $porcentaje = $solicitud->codigos_count > 0
                                            ? round(($solicitud->aprobados_count / $solicitud->codigos_count) * 100)
                                            : 0;
                                    @endphp
                                    <tr>
                                        <td class="fw-semibold">{{ $solicitud->numero }}</td>
                                        <td>{{ $solicitud->created_at->format('d/m/Y h:i A') }}</td>
                                        <td><code>{{ $solicitud->prefijo_empresa }}{{ $solicitud->prefijo_seleccionado }}</code></td>
                                        <td>{{ $solicitud->solicitante?->name ?? 'Usuario no disponible' }}</td>
                                        <td style="min-width: 180px;">
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span>{{ $solicitud->aprobados_count }} de {{ $solicitud->codigos_count }}</span>
                                                <span>{{ $porcentaje }}%</span>
                                            </div>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-{{ $colorEstado }}" style="width: {{ $porcentaje }}%" role="progressbar" aria-label="Progreso de aprobación"></div>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-{{ $colorEstado }}-subtle text-{{ $colorEstado }} text-capitalize">{{ $solicitud->estado }}</span></td>
                                        <td class="text-end text-nowrap">
                                            <a class="btn btn-sm btn-danger" href="{{ route('mantenimiento.solicitudes-terminales.pdf', $solicitud) }}" title="Descargar solicitud en PDF">
                                                <i class="ri-file-pdf-2-line me-1"></i>PDF
                                            </a>
                                            <button
                                                class="btn btn-sm btn-info btn-ver-solicitud"
                                                type="button"
                                                data-datos-url="{{ route('mantenimiento.solicitudes-terminales.datos', $solicitud) }}"
                                                data-actualizar-url="{{ route('mantenimiento.solicitudes-terminales.datos.update', $solicitud) }}"
                                            >
                                                <i class="ri-eye-line me-1"></i>Ver
                                            </button>
                                            @if ($solicitud->codigos_count > 0)
                                                <a class="btn btn-sm btn-success" href="{{ route('mantenimiento.solicitudes-terminales.excel', $solicitud) }}" title="Descargar solicitud en Excel">
                                                    <i class="ri-file-excel-2-line me-1"></i>Excel
                                                </a>
                                            @endif
                                            <button
                                                class="btn btn-sm btn-success btn-enviar-correo"
                                                type="button"
                                                data-solicitud-id="{{ $solicitud->id }}"
                                                data-solicitud-numero="{{ $solicitud->numero }}"
                                                data-url="{{ route('mantenimiento.solicitudes-terminales.correo', $solicitud) }}"
                                            >
                                                <i class="ri-mail-send-line me-1"></i>Enviar por correo
                                            </button>
                                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#solicitud-{{ $solicitud->id }}" aria-expanded="false">
                                                <i class="ri-list-check-2"></i> Revisar
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="7" class="p-0 border-0">
                                            <div class="collapse bg-light p-3" id="solicitud-{{ $solicitud->id }}">
                                                <form method="POST" action="{{ route('mantenimiento.solicitudes-terminales.aprobaciones', $solicitud) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="page" value="{{ $solicitudes->currentPage() }}">
                                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                                        <div>
                                                            <h6 class="mb-1">Respuesta de Loteka Central</h6>
                                                            <span class="text-muted">Marca únicamente los códigos que fueron aprobados.</span>
                                                        </div>
                                                        <button class="btn btn-primary btn-sm" type="submit"><i class="ri-save-3-line me-1"></i>Guardar aprobaciones</button>
                                                    </div>
                                                    <div class="row g-2">
                                                        @foreach ($solicitud->codigos as $codigo)
                                                            <div class="col-sm-6 col-md-4 col-xl-3">
                                                                <label class="d-flex align-items-center gap-2 border rounded p-2 bg-white {{ $codigo->estado === 'aprobado' ? 'border-success' : '' }}">
                                                                    <input class="form-check-input mt-0" type="checkbox" name="codigos_aprobados[]" value="{{ $codigo->id }}" @checked($codigo->estado === 'aprobado')>
                                                                    <code class="fs-6">{{ $codigo->codigo }}</code>
                                                                    @if ($codigo->estado === 'aprobado')
                                                                        <span class="badge bg-success-subtle text-success ms-auto">Aprobado</span>
                                                                    @else
                                                                        <span class="badge bg-warning-subtle text-warning ms-auto">Pendiente</span>
                                                                    @endif
                                                                </label>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="ri-file-list-3-line fs-2 d-block mb-2"></i>
                                            Aún no se han creado solicitudes de terminales.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($solicitudes->hasPages())
                    <div class="card-footer">{{ $solicitudes->links() }}</div>
                @endif
            </div>

            <div class="modal fade" id="modal-datos-solicitud" tabindex="-1" aria-labelledby="modal-datos-solicitud-titulo" aria-hidden="true">
                <div class="modal-dialog modal-fullscreen">
                    <div class="modal-content">
                        <form method="POST" id="form-datos-solicitud">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="page" value="{{ $solicitudes->currentPage() }}">
                            <div class="modal-header">
                                <div>
                                    <h5 class="modal-title" id="modal-datos-solicitud-titulo">Datos de la solicitud</h5>
                                    <p class="text-muted mb-0 small">Seleccione la ubicaci&oacute;n respetando el orden territorial.</p>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-danger d-none" id="error-datos-solicitud"></div>
                                <div class="text-center py-5" id="cargando-datos-solicitud">
                                    <span class="spinner-border text-primary"></span>
                                    <p class="text-muted mt-2 mb-0">Cargando formulario...</p>
                                </div>
                                <div class="table-responsive d-none" id="contenedor-datos-solicitud">
                                    <table class="table table-bordered table-sm align-middle text-nowrap mb-0">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th>ID</th>
                                                <th>Estatus</th>
                                                <th>Consorcio</th>
                                                <th>Nombre banca</th>
                                                <th>C&oacute;digo terminal</th>
                                                <th>Regi&oacute;n</th>
                                                <th>Provincia</th>
                                                <th>Municipio</th>
                                                <th>Ciudad</th>
                                                <th>Sector</th>
                                                <th>Calle</th>
                                                <th>Direcci&oacute;n local</th>
                                                <th>Latitud</th>
                                                <th>Longitud</th>
                                                <th>RJA</th>
                                            </tr>
                                        </thead>
                                        <tbody id="filas-datos-solicitud"></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                                <button type="submit" class="btn btn-primary" id="btn-guardar-datos-solicitud">
                                    <i class="ri-save-3-line me-1"></i>Guardar formulario
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="modal-enviar-correo" tabindex="-1" aria-labelledby="modal-enviar-correo-titulo" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="POST" id="form-enviar-correo">
                            @csrf
                            <input type="hidden" name="solicitud_correo_id" id="solicitud-correo-id" value="{{ old('solicitud_correo_id') }}">
                            <div class="modal-header">
                                <h5 class="modal-title" id="modal-enviar-correo-titulo">Enviar solicitud por correo</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-muted">Se enviará la solicitud formal de Loteka con el PDF adjunto.</p>
                                <label class="form-label" for="correos-solicitud">Correo o correos destinatarios</label>
                                <textarea
                                    class="form-control @if ($errors->has('correos') || $errors->has('destinatarios') || $errors->has('destinatarios.*')) is-invalid @endif"
                                    id="correos-solicitud"
                                    name="correos"
                                    rows="4"
                                    placeholder="loteka@ejemplo.com, supervisor@ejemplo.com"
                                    required
                                >{{ old('correos') }}</textarea>
                                <div class="form-text">Puedes separar las direcciones con coma, punto y coma o salto de línea. Máximo 20.</div>
                                @if ($errors->has('correos') || $errors->has('destinatarios') || $errors->has('destinatarios.*'))
                                    <div class="invalid-feedback d-block">
                                        {{ $errors->first('correos') ?: ($errors->first('destinatarios') ?: $errors->first('destinatarios.*')) }}
                                    </div>
                                @endif
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-success" id="btn-confirmar-correo">
                                    <i class="ri-mail-send-line me-1"></i>Enviar solicitud
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const formSugerencia = document.getElementById('form-sugerencia');
    const formConfirmar = document.getElementById('form-confirmar');
    const botonSugerir = document.getElementById('btn-sugerir');
    const errorSugerencia = document.getElementById('error-sugerencia');
    const listaSugerencias = document.getElementById('lista-sugerencias');
    const botonConfirmar = document.getElementById('btn-confirmar-solicitud');
    const modalCorreoElemento = document.getElementById('modal-enviar-correo');
    const modalDatosElemento = document.getElementById('modal-datos-solicitud');

    document.body.appendChild(modalCorreoElemento);
    document.body.appendChild(modalDatosElemento);

    const modalCorreo = new bootstrap.Modal(modalCorreoElemento);
    const modalDatos = new bootstrap.Modal(modalDatosElemento);
    const formCorreo = document.getElementById('form-enviar-correo');
    const botonConfirmarCorreo = document.getElementById('btn-confirmar-correo');
    const formDatos = document.getElementById('form-datos-solicitud');
    const filasDatos = document.getElementById('filas-datos-solicitud');
    const cargandoDatos = document.getElementById('cargando-datos-solicitud');
    const contenedorDatos = document.getElementById('contenedor-datos-solicitud');
    const errorDatos = document.getElementById('error-datos-solicitud');
    const botonGuardarDatos = document.getElementById('btn-guardar-datos-solicitud');
    const urlOpcionesGeograficas = @json(route('mantenimiento.zonas-geograficas.opciones'));
    const ordenGeografico = ['region', 'provincia', 'municipio', 'ciudad', 'sector'];
    const nivelesApi = {
        region: 'region',
        provincia: 'provincia',
        municipio: 'municipio',
        ciudad: 'ciudad_seccion',
        sector: 'sector_barrio_paraje',
    };

    function crearInput(indice, campo, valor, ancho = '160px', tipo = 'text') {
        const input = document.createElement('input');
        input.type = ['coordenada', 'longitud'].includes(tipo) ? 'text' : tipo;
        input.name = `codigos[${indice}][${campo}]`;
        input.value = valor ?? '';
        input.className = 'form-control form-control-sm';
        input.style.minWidth = ancho;
        if (tipo === 'number') input.step = 'any';
        if (tipo === 'coordenada') {
            input.inputMode = 'decimal';
            input.maxLength = 11;
            input.placeholder = '00.0000000';
            input.pattern = '-?\\d{2}\\.\\d{1,7}';
            input.title = 'Digite dos numeros antes del punto. Ejemplo: 18.4763890 o -69.8933330';
            input.addEventListener('input', function () {
                const esNegativo = input.value.trim().startsWith('-');
                const digitos = input.value.replace(/\D/g, '').slice(0, 9);
                const entero = digitos.slice(0, 2);
                const decimales = digitos.slice(2);

                input.value = (esNegativo ? '-' : '') + entero + (digitos.length >= 2 ? '.' + decimales : '');
            });
        }
        if (tipo === 'longitud') {
            input.inputMode = 'decimal';
            input.maxLength = 12;
            input.placeholder = '-00.00000000';
            input.pattern = '-\\d{2}\\.\\d{1,8}';
            input.title = 'La longitud debe iniciar con menos. Ejemplo: -50.77777777';
            input.addEventListener('focus', function () {
                if (input.value === '') input.value = '-';
            });
            input.addEventListener('input', function () {
                const digitos = input.value.replace(/\D/g, '').slice(0, 10);
                const entero = digitos.slice(0, 2);
                const decimales = digitos.slice(2);

                input.value = '-' + entero + (digitos.length >= 2 ? '.' + decimales : '');
            });
        }
        return input;
    }

    function agregarOpcion(select, valor) {
        if (! valor || Array.from(select.options).some((opcion) => opcion.value === valor)) return;
        const opcion = document.createElement('option');
        opcion.value = valor;
        opcion.textContent = valor;
        select.appendChild(opcion);
    }

    function crearSelect(indice, campo, valor, regiones) {
        const select = document.createElement('select');
        select.name = `codigos[${indice}][${campo}]`;
        select.className = 'form-select form-select-sm selector-geografico';
        select.dataset.campo = campo;
        select.style.minWidth = '180px';

        const opcionVacia = document.createElement('option');
        opcionVacia.value = '';
        opcionVacia.textContent = 'Seleccione...';
        select.appendChild(opcionVacia);

        if (campo === 'region') {
            regiones.forEach((region) => agregarOpcion(select, region));
        }

        agregarOpcion(select, valor);
        select.value = valor ?? '';
        return select;
    }

    function celdaCon(elemento) {
        const celda = document.createElement('td');
        celda.appendChild(elemento);
        return celda;
    }

    async function cargarOpcionesGeograficas(fila, campo) {
        if (campo === 'region') return;

        const indiceCampo = ordenGeografico.indexOf(campo);
        const parametros = new URLSearchParams({ nivel: nivelesApi[campo] });

        for (const anterior of ordenGeografico.slice(0, indiceCampo)) {
            const selectAnterior = fila.querySelector(`[data-campo="${anterior}"]`);
            if (! selectAnterior?.value) return;
            parametros.set(nivelesApi[anterior], selectAnterior.value);
        }

        const select = fila.querySelector(`[data-campo="${campo}"]`);
        const valorActual = select.value;
        const respuesta = await fetch(`${urlOpcionesGeograficas}?${parametros.toString()}`, {
            headers: { 'Accept': 'application/json' },
        });

        if (! respuesta.ok) return;

        const opciones = await respuesta.json();
        select.replaceChildren();
        const opcionVacia = document.createElement('option');
        opcionVacia.value = '';
        opcionVacia.textContent = 'Seleccione...';
        select.appendChild(opcionVacia);
        opciones.forEach((opcion) => agregarOpcion(select, opcion));
        agregarOpcion(select, valorActual);
        select.value = valorActual;
    }

    function construirFila(codigo, indice, regiones) {
        const fila = document.createElement('tr');
        fila.dataset.indice = indice;

        const celdaId = document.createElement('td');
        celdaId.textContent = indice + 1;
        const id = document.createElement('input');
        id.type = 'hidden';
        id.name = `codigos[${indice}][id]`;
        id.value = codigo.id;
        celdaId.appendChild(id);
        fila.appendChild(celdaId);

        const estado = document.createElement('span');
        estado.className = codigo.estado === 'aprobado'
            ? 'badge bg-success-subtle text-success'
            : 'badge bg-warning-subtle text-warning';
        estado.textContent = codigo.estado === 'aprobado' ? 'Aprobada' : 'Pendiente';
        fila.appendChild(celdaCon(estado));

        const consorcio = document.createElement('span');
        consorcio.textContent = 'Grupo Joselito';
        fila.appendChild(celdaCon(consorcio));
        fila.appendChild(celdaCon(crearInput(indice, 'nombre_banca', codigo.nombre_banca, '190px')));

        const terminal = document.createElement('code');
        terminal.className = 'fs-6';
        terminal.textContent = codigo.codigo;
        fila.appendChild(celdaCon(terminal));

        ordenGeografico.forEach(function (campo) {
            fila.appendChild(celdaCon(crearSelect(indice, campo, codigo[campo], regiones)));
        });

        fila.appendChild(celdaCon(crearInput(indice, 'calle', codigo.calle, '180px')));
        fila.appendChild(celdaCon(crearInput(indice, 'direccion_local', codigo.direccion_local, '230px')));
        fila.appendChild(celdaCon(crearInput(indice, 'latitud', codigo.latitud, '140px', 'coordenada')));
        fila.appendChild(celdaCon(crearInput(indice, 'longitud', codigo.longitud, '150px', 'longitud')));
        fila.appendChild(celdaCon(crearInput(indice, 'rja', codigo.rja, '130px')));

        fila.querySelectorAll('.selector-geografico').forEach(function (select) {
            select.addEventListener('focus', function () {
                cargarOpcionesGeograficas(fila, select.dataset.campo);
            });
            select.addEventListener('change', function () {
                const posicion = ordenGeografico.indexOf(select.dataset.campo);
                ordenGeografico.slice(posicion + 1).forEach(function (dependiente) {
                    const siguiente = fila.querySelector(`[data-campo="${dependiente}"]`);
                    siguiente.replaceChildren(new Option('Seleccione...', ''));
                });

                const siguienteCampo = ordenGeografico[posicion + 1];
                if (siguienteCampo) cargarOpcionesGeograficas(fila, siguienteCampo);
            });
        });

        return fila;
    }

    document.querySelectorAll('.btn-ver-solicitud').forEach(function (boton) {
        boton.addEventListener('click', async function () {
            formDatos.action = boton.dataset.actualizarUrl;
            filasDatos.replaceChildren();
            errorDatos.classList.add('d-none');
            contenedorDatos.classList.add('d-none');
            cargandoDatos.classList.remove('d-none');
            modalDatos.show();

            try {
                const respuesta = await fetch(boton.dataset.datosUrl, {
                    headers: { 'Accept': 'application/json' },
                });
                const datos = await respuesta.json();

                if (! respuesta.ok) throw new Error(datos.message || 'No fue posible cargar el formulario.');

                document.getElementById('modal-datos-solicitud-titulo').textContent = `Formulario ${datos.numero}`;
                filasDatos.replaceChildren(...datos.codigos.map((codigo, indice) => construirFila(codigo, indice, datos.regiones)));
                contenedorDatos.classList.remove('d-none');
            } catch (error) {
                errorDatos.textContent = error.message;
                errorDatos.classList.remove('d-none');
            } finally {
                cargandoDatos.classList.add('d-none');
            }
        });
    });

    formDatos.addEventListener('submit', function () {
        botonGuardarDatos.disabled = true;
        botonGuardarDatos.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';
    });

    function configurarModalCorreo(boton) {
        formCorreo.action = boton.dataset.url;
        document.getElementById('solicitud-correo-id').value = boton.dataset.solicitudId;
        document.getElementById('modal-enviar-correo-titulo').textContent = 'Enviar ' + boton.dataset.solicitudNumero + ' por correo';
    }

    document.querySelectorAll('.btn-enviar-correo').forEach(function (boton) {
        boton.addEventListener('click', function () {
            configurarModalCorreo(boton);
            modalCorreo.show();
        });
    });

    formCorreo.addEventListener('submit', function () {
        botonConfirmarCorreo.disabled = true;
        botonConfirmarCorreo.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enviando...';
    });

    const solicitudCorreoAnterior = @json(old('solicitud_correo_id'));

    if (solicitudCorreoAnterior) {
        const botonAnterior = document.querySelector('[data-solicitud-id="' + solicitudCorreoAnterior + '"]');

        if (botonAnterior) {
            configurarModalCorreo(botonAnterior);
            modalCorreo.show();
        }
    }

    formConfirmar.addEventListener('submit', function () {
        botonConfirmar.disabled = true;
        botonConfirmar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creando solicitud...';
    });

    formSugerencia.addEventListener('submit', async function (event) {
        event.preventDefault();

        const prefijo = document.getElementById('prefijo-seleccionado').value;
        const cantidad = document.getElementById('cantidad').value;

        botonSugerir.disabled = true;
        botonSugerir.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generando...';
        errorSugerencia.classList.add('d-none');
        formConfirmar.classList.add('d-none');

        try {
            const response = await fetch(@json(route('mantenimiento.solicitudes-terminales.preview')), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': @json(csrf_token()),
                },
                body: JSON.stringify({
                    prefijo_seleccionado: prefijo,
                    cantidad: Number(cantidad),
                }),
            });
            const data = await response.json();

            if (! response.ok) {
                const primerError = data.errors ? Object.values(data.errors).flat()[0] : null;
                throw new Error(primerError || data.message || 'No fue posible generar los códigos.');
            }

            listaSugerencias.replaceChildren();
            data.codigos.forEach(function (codigo) {
                const contenedor = document.createElement('span');
                contenedor.className = 'badge bg-white text-primary border border-primary-subtle fs-6 px-3 py-2';
                contenedor.textContent = codigo;

                const campo = document.createElement('input');
                campo.type = 'hidden';
                campo.name = 'codigos[]';
                campo.value = codigo;

                listaSugerencias.append(contenedor, campo);
            });

            document.getElementById('confirmar-prefijo').value = prefijo;
            document.getElementById('confirmar-cantidad').value = cantidad;
            formConfirmar.classList.remove('d-none');
        } catch (error) {
            errorSugerencia.textContent = error.message;
            errorSugerencia.classList.remove('d-none');
        } finally {
            botonSugerir.disabled = false;
            botonSugerir.innerHTML = '<i class="ri-magic-line me-1"></i>Sugerir códigos';
        }
    });
});
</script>
@endsection
