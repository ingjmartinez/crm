@extends('app')

@section('content')
    @php
        $nombreAgencia = $agencia->nombre_agencia ?: ($agencia->nombre ?? 'Sin nombre registrado');
        $codigoAgencia = $agencia->agencia ?: ($agencia->codigo ?? '-');
        $contratosActivos = $agencia->legalContratos->where('estado', 'activo')->count();
        $pagosPendientes = $pagos->where('estado', 'pendiente');
        $pagosVencidos = $pagosPendientes->filter(fn ($pago) => $pago->fecha_vencimiento->lessThan(today()))->count();
        $compromisoMensual = $agencia->legalContratos
            ->flatMap->obligaciones
            ->where('activa', true)
            ->where('frecuencia', 'mensual')
            ->sum(fn ($obligacion) => (float) $obligacion->monto);
    @endphp

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <div>
                                <h4 class="mb-sm-0">{{ $nombreAgencia }}</h4>
                                <p class="text-muted mb-0">Terminal {{ $agencia->terminal ?: '-' }} · Código {{ $codigoAgencia }}</p>
                            </div>
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('legal.index') }}">Legal</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('legal.bitacora-agencias.index') }}">Bitácora</a></li>
                                <li class="breadcrumb-item active">{{ $agencia->terminal ?: $codigoAgencia }}</li>
                            </ol>
                        </div>
                    </div>
                </div>

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="card border-primary-subtle">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <span class="badge bg-primary-subtle text-primary">{{ $agencia->empresa ?: 'Sin empresa' }}</span>
                                <span class="badge bg-light text-dark">{{ $agencia->ciudad ?: 'Sin ciudad' }}</span>
                                <span class="badge bg-light text-dark">{{ $agencia->ruta ?: 'Sin ruta' }}</span>
                            </div>
                            <p class="text-muted mb-0">Expediente legal centralizado de la agencia y sus obligaciones.</p>
                        </div>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-nuevo-contrato">
                            <i class="ri-file-add-line me-1"></i>Nuevo contrato
                        </button>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    @foreach ([
                        ['Contratos activos', $contratosActivos, false, 'success'],
                        ['Pagos pendientes', $pagosPendientes->count(), false, 'warning'],
                        ['Pagos vencidos', $pagosVencidos, false, 'danger'],
                        ['Compromiso mensual', $compromisoMensual, true, 'primary'],
                    ] as [$titulo, $valor, $esMonto, $color])
                        <div class="col-xl-3 col-sm-6">
                            <div class="card mb-0 h-100">
                                <div class="card-body">
                                    <div class="text-muted text-uppercase fw-semibold small">{{ $titulo }}</div>
                                    <div class="fs-4 fw-bold text-{{ $color }} mt-1">
                                        {{ $esMonto ? 'RD$ '.number_format($valor, 2) : number_format($valor) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="row g-4">
                    <div class="col-xl-7">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-1">Contratos y obligaciones</h5>
                                <p class="text-muted mb-0">Documentos privados y compromisos asociados.</p>
                            </div>
                            <div class="card-body">
                                @forelse ($agencia->legalContratos as $contrato)
                                    <div class="border rounded p-3 mb-3">
                                        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                                            <div>
                                                <div class="d-flex align-items-center gap-2 mb-1">
                                                    <h5 class="mb-0">{{ $contrato->titulo }}</h5>
                                                    <span class="badge {{ $contrato->estado === 'activo' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                                        {{ ucfirst($contrato->estado) }}
                                                    </span>
                                                </div>
                                                <div class="text-muted small">
                                                    {{ $contrato->contraparte ?: 'Sin contraparte' }} ·
                                                    {{ $contrato->fecha_inicio->format('d/m/Y') }}
                                                    @if ($contrato->fecha_fin)
                                                        al {{ $contrato->fecha_fin->format('d/m/Y') }}
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <a href="{{ route('legal.contratos.documento', $contrato) }}" target="_blank" class="btn btn-sm btn-soft-danger">
                                                    <i class="ri-file-pdf-2-line me-1"></i>Ver PDF
                                                </a>
                                                <button type="button" class="btn btn-sm btn-soft-primary" data-bs-toggle="modal" data-bs-target="#modal-obligacion-{{ $contrato->id }}">
                                                    <i class="ri-add-line me-1"></i>Obligación
                                                </button>
                                            </div>
                                        </div>

                                        <div class="d-flex flex-column gap-2">
                                            @forelse ($contrato->obligaciones as $obligacion)
                                                <div class="bg-light rounded p-2 d-flex flex-wrap justify-content-between gap-2">
                                                    <div>
                                                        <span class="fw-semibold">{{ $tiposObligacion[$obligacion->tipo] ?? ucfirst($obligacion->tipo) }}</span>
                                                        @if ($obligacion->descripcion)
                                                            <span class="text-muted">· {{ $obligacion->descripcion }}</span>
                                                        @endif
                                                        <div class="small text-muted">
                                                            {{ $frecuencias[$obligacion->frecuencia] ?? ucfirst($obligacion->frecuencia) }} ·
                                                            {{ $obligacion->pagosProgramados->count() }} pago(s) programado(s)
                                                        </div>
                                                    </div>
                                                    <div class="fw-bold text-primary">RD$ {{ number_format((float) $obligacion->monto, 2) }}</div>
                                                </div>
                                            @empty
                                                <div class="text-muted small">Este contrato no tiene obligaciones.</div>
                                            @endforelse
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center text-muted py-5">
                                        <i class="ri-file-list-3-line fs-1 d-block mb-2"></i>
                                        Esta agencia todavía no tiene contratos registrados.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-5">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-1">Calendario de pagos</h5>
                                <p class="text-muted mb-0">Primeros 120 vencimientos del expediente.</p>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive" style="max-height: 620px;">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th>Vencimiento</th>
                                                <th>Concepto</th>
                                                <th class="text-end">Monto</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($pagos->take(120) as $pago)
                                                @php
                                                    $estaVencido = $pago->estado === 'pendiente' && $pago->fecha_vencimiento->lessThan(today());
                                                    $estadoPago = $estaVencido ? 'vencido' : $pago->estado;
                                                @endphp
                                                <tr>
                                                    <td>{{ $pago->fecha_vencimiento->format('d/m/Y') }}</td>
                                                    <td>{{ $tiposObligacion[$pago->obligacion->tipo] ?? ucfirst($pago->obligacion->tipo) }}</td>
                                                    <td class="text-end">RD$ {{ number_format((float) $pago->monto, 2) }}</td>
                                                    <td>
                                                        <span class="badge {{ $estaVencido ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning' }}">
                                                            {{ ucfirst($estadoPago) }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="text-center text-muted py-4">No hay pagos programados.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-nuevo-contrato" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <form method="POST" action="{{ route('legal.contratos.store', $agencia) }}" enctype="multipart/form-data" class="modal-content">
                @csrf
                <div class="modal-header">
                    <div><h5 class="modal-title">Nuevo contrato</h5><p class="text-muted mb-0">Registra el documento y su primera obligación.</p></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 class="text-uppercase text-muted mb-3">Datos del contrato</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6"><label class="form-label">Título</label><input type="text" name="titulo" class="form-control" value="{{ old('titulo') }}" required maxlength="180"></div>
                        <div class="col-md-3"><label class="form-label">Número de contrato</label><input type="text" name="numero_contrato" class="form-control" value="{{ old('numero_contrato') }}" maxlength="100"></div>
                        <div class="col-md-3"><label class="form-label">Estado</label><select name="estado" class="form-select" required><option value="activo">Activo</option><option value="borrador">Borrador</option></select></div>
                        <div class="col-md-6"><label class="form-label">Propietario o proveedor</label><input type="text" name="contraparte" class="form-control" value="{{ old('contraparte') }}" maxlength="180"></div>
                        <div class="col-md-3"><label class="form-label">Fecha inicial</label><input type="date" name="fecha_inicio" class="form-control" value="{{ old('fecha_inicio', now()->toDateString()) }}" required></div>
                        <div class="col-md-3"><label class="form-label">Fecha final</label><input type="date" name="fecha_fin" class="form-control" value="{{ old('fecha_fin') }}"></div>
                        <div class="col-md-8"><label class="form-label">Contrato PDF</label><input type="file" name="documento_pdf" class="form-control" accept="application/pdf,.pdf" required><div class="form-text">Máximo 15 MB. Se almacena de forma privada.</div></div>
                        <div class="col-md-4 d-flex align-items-end"><div class="form-check mb-2"><input type="checkbox" name="renovacion_automatica" value="1" class="form-check-input" id="renovacion-automatica"><label for="renovacion-automatica" class="form-check-label">Renovación automática</label></div></div>
                        <div class="col-12"><label class="form-label">Observaciones</label><textarea name="observaciones" class="form-control" rows="2" maxlength="2000">{{ old('observaciones') }}</textarea></div>
                    </div>

                    <h6 class="text-uppercase text-muted mb-3">Primera obligación</h6>
                    <div class="row g-3">
                        <div class="col-md-3"><label class="form-label">Tipo</label><select name="obligacion_tipo" class="form-select" required>@foreach ($tiposObligacion as $valor => $etiqueta)<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach</select></div>
                        <div class="col-md-5"><label class="form-label">Descripción</label><input type="text" name="obligacion_descripcion" class="form-control" maxlength="180" placeholder="Ej.: Alquiler mensual del local"></div>
                        <div class="col-md-4"><label class="form-label">Monto</label><div class="input-group"><span class="input-group-text">RD$</span><input type="number" name="monto" class="form-control" min="0.01" step="0.01" required></div></div>
                        <div class="col-md-4"><label class="form-label">Frecuencia</label><select name="frecuencia" class="form-select" required>@foreach ($frecuencias as $valor => $etiqueta)<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach</select></div>
                        <div class="col-md-4"><label class="form-label">Primer pago</label><input type="date" name="fecha_primer_pago" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label">Fin de pagos</label><input type="date" name="fecha_fin_pagos" class="form-control"><div class="form-text">Si queda vacío se generan 12 meses.</div></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar contrato</button></div>
            </form>
        </div>
    </div>

    @foreach ($agencia->legalContratos as $contrato)
        <div class="modal fade" id="modal-obligacion-{{ $contrato->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form method="POST" action="{{ route('legal.obligaciones.store', $contrato) }}" class="modal-content">
                    @csrf
                    <div class="modal-header"><div><h5 class="modal-title">Agregar obligación</h5><p class="text-muted mb-0">{{ $contrato->titulo }}</p></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body"><div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Tipo</label><select name="tipo" class="form-select" required>@foreach ($tiposObligacion as $valor => $etiqueta)<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach</select></div>
                        <div class="col-md-8"><label class="form-label">Descripción</label><input type="text" name="descripcion" class="form-control" maxlength="180"></div>
                        <div class="col-md-4"><label class="form-label">Monto</label><div class="input-group"><span class="input-group-text">RD$</span><input type="number" name="monto" class="form-control" min="0.01" step="0.01" required></div></div>
                        <div class="col-md-4"><label class="form-label">Frecuencia</label><select name="frecuencia" class="form-select" required>@foreach ($frecuencias as $valor => $etiqueta)<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach</select></div>
                        <div class="col-md-4"><label class="form-label">Primer pago</label><input type="date" name="fecha_primer_pago" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label">Fin de pagos</label><input type="date" name="fecha_fin" class="form-control"></div>
                    </div></div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Programar pagos</button></div>
                </form>
            </div>
        </div>
    @endforeach
@endsection
