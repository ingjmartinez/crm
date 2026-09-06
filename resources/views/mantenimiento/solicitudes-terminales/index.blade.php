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

    document.body.appendChild(modalCorreoElemento);

    const modalCorreo = new bootstrap.Modal(modalCorreoElemento);
    const formCorreo = document.getElementById('form-enviar-correo');
    const botonConfirmarCorreo = document.getElementById('btn-confirmar-correo');

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
