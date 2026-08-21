@extends('app')

@section('content')
    <style>
        .estado-motivo-column {
            min-width: 260px;
            width: 260px;
        }

        .estado-motivo-column .badge {
            font-size: 0.8rem;
            padding: 0.48rem 0.65rem;
            margin: 0.15rem 0.2rem 0.15rem 0;
            white-space: normal;
        }

        .detalle-validacion-card {
            margin-top: 1.5rem;
        }
    </style>

    @php
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        $motivosLabels = [
            'faltante' => 'Faltante',
            'desvinculado' => 'Desvinculado',
            'agencia_excluida' => 'Agencia excluida',
            'meta_no_alcanzada' => 'Meta no alcanzada',
        ];
        $motivosClases = [
            'faltante' => 'bg-danger-subtle text-danger',
            'desvinculado' => 'bg-dark-subtle text-dark',
            'agencia_excluida' => 'bg-warning-subtle text-warning',
            'meta_no_alcanzada' => 'bg-secondary-subtle text-secondary',
        ];
    @endphp

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Validador de Incentivos</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('recursos-humanos.index') }}">Recursos Humanos</a></li>
                                    <li class="breadcrumb-item active">Validador de Incentivos</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                @if ($periodo === null)
                    <div class="card border-warning">
                        <div class="card-body text-center py-5">
                            <div class="avatar-lg mx-auto mb-3">
                                <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-1">
                                    <i class="ri-calendar-close-line"></i>
                                </span>
                            </div>
                            <h5>No hay períodos guardados</h5>
                            <p class="text-muted mb-0">
                                Primero genera el informe de incentivos V6 y utiliza la opción <strong>Guardar período</strong>.
                            </p>
                        </div>
                    </div>
                @else
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div>
                                    <h5 class="card-title mb-1">Consulta del cierre mensual</h5>
                                    <p class="text-muted mb-0">
                                        Datos guardados del {{ $periodo->fecha_inicio->format('d/m/Y') }} al
                                        {{ $periodo->fecha_fin->format('d/m/Y') }} · Revisión {{ $periodo->revision }}
                                    </p>
                                </div>
                                @if ($consultado)
                                    <a href="{{ route('recursos-humanos.validador-incentivos.export', request()->query()) }}"
                                        class="btn btn-success">
                                        <i class="ri-file-excel-2-line me-1"></i> Exportar detalle
                                    </a>
                                @endif
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="{{ route('recursos-humanos.validador-incentivos.index') }}">
                                <input type="hidden" name="consultar" value="1">
                                <div class="row g-3 align-items-end">
                                    <div class="col-xl-2 col-md-4">
                                        <label for="periodo_id" class="form-label">Período</label>
                                        <select id="periodo_id" name="periodo_id" class="form-select">
                                            @foreach ($periodos as $itemPeriodo)
                                                <option value="{{ $itemPeriodo->id }}" @selected($itemPeriodo->id === $periodo->id)>
                                                    {{ $meses[$itemPeriodo->mes] }} {{ $itemPeriodo->anio }}
                                                    (Rev. {{ $itemPeriodo->revision }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-xl-3 col-md-8">
                                        <label for="buscar" class="form-label">Cédula, nombre o ID</label>
                                        <input id="buscar" name="buscar" type="search" class="form-control"
                                            value="{{ $filtros['buscar'] ?? '' }}" placeholder="Ej. 40238509620">
                                    </div>
                                    <div class="col-xl-2 col-md-4">
                                        <label for="estado" class="form-label">Estado</label>
                                        <select id="estado" name="estado" class="form-select">
                                            <option value="">Todos</option>
                                            <option value="pagado" @selected(($filtros['estado'] ?? '') === 'pagado')>Pagado</option>
                                            <option value="pagado_parcial" @selected(($filtros['estado'] ?? '') === 'pagado_parcial')>Pagado parcial</option>
                                            <option value="no_pagado" @selected(($filtros['estado'] ?? '') === 'no_pagado')>No pagado</option>
                                            <option value="no_califica" @selected(($filtros['estado'] ?? '') === 'no_califica')>No calificó</option>
                                            <option value="sin_idempleado" @selected(($filtros['estado'] ?? '') === 'sin_idempleado')>Sin IdEmpleado</option>
                                        </select>
                                    </div>
                                    <div class="col-xl-2 col-md-4">
                                        <label for="motivo" class="form-label">Motivo</label>
                                        <select id="motivo" name="motivo" class="form-select">
                                            <option value="">Todos</option>
                                            @foreach ($motivosLabels as $valor => $label)
                                                <option value="{{ $valor }}" @selected(($filtros['motivo'] ?? '') === $valor)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-xl-2 col-md-4">
                                        <label for="empresa" class="form-label">Empresa</label>
                                        <select id="empresa" name="empresa" class="form-select">
                                            <option value="">Todas</option>
                                            @foreach ($empresas as $empresa)
                                                <option value="{{ $empresa }}" @selected(($filtros['empresa'] ?? '') === $empresa)>{{ $empresa }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-xl-1 col-md-4">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="ri-search-line me-1"></i> Generar
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    @if (! $consultado)
                        <div class="card border-info">
                            <div class="card-body text-center py-5">
                                <div class="avatar-lg mx-auto mb-3">
                                    <span class="avatar-title bg-info-subtle text-info rounded-circle fs-1">
                                        <i class="ri-search-eye-line"></i>
                                    </span>
                                </div>
                                <h5>Genera la consulta del período seleccionado</h5>
                                <p class="text-muted mb-0">
                                    El último mes guardado está seleccionado. Presiona <strong>Generar</strong> para visualizar sus datos.
                                </p>
                            </div>
                        </div>
                    @else
                    <div class="row g-3">
                        <div class="col-xl col-md-4 col-6">
                            <div class="card card-animate h-100"><div class="card-body">
                                <p class="text-uppercase fw-medium text-muted mb-1">Cumplieron</p>
                                <h4 class="mb-0">{{ number_format((int) $resumen['califican']) }}</h4>
                            </div></div>
                        </div>
                        <div class="col-xl col-md-4 col-6">
                            <div class="card card-animate h-100"><div class="card-body">
                                <p class="text-uppercase fw-medium text-muted mb-1">No cumplieron</p>
                                <h4 class="mb-0 text-secondary">{{ number_format((int) $resumen['no_califican']) }}</h4>
                            </div></div>
                        </div>
                        <div class="col-xl col-md-4 col-6">
                            <div class="card card-animate h-100"><div class="card-body">
                                <p class="text-uppercase fw-medium text-muted mb-1">Pagados</p>
                                <h4 class="mb-0 text-success">{{ number_format((int) $resumen['pagados']) }}</h4>
                            </div></div>
                        </div>
                        <div class="col-xl col-md-4 col-6">
                            <div class="card card-animate h-100"><div class="card-body">
                                <p class="text-uppercase fw-medium text-muted mb-1">Parciales</p>
                                <h4 class="mb-0 text-warning">{{ number_format((int) $resumen['pagados_parciales']) }}</h4>
                            </div></div>
                        </div>
                        <div class="col-xl col-md-4 col-6">
                            <div class="card card-animate h-100"><div class="card-body">
                                <p class="text-uppercase fw-medium text-muted mb-1">No pagados</p>
                                <h4 class="mb-0 text-danger">{{ number_format((int) $resumen['no_pagados']) }}</h4>
                            </div></div>
                        </div>
                        <div class="col-xl col-md-4 col-6">
                            <div class="card card-animate h-100"><div class="card-body">
                                <p class="text-uppercase fw-medium text-muted mb-1">Sin IdEmpleado</p>
                                <h4 class="mb-0 text-info">{{ number_format((int) $resumen['sin_idempleado']) }}</h4>
                            </div></div>
                        </div>
                        <div class="col-xl-3 col-md-8">
                            <div class="card card-animate h-100"><div class="card-body">
                                <p class="text-uppercase fw-medium text-muted mb-1">Pagado / Retenido</p>
                                <h5 class="mb-1 text-success">RD$ {{ number_format((int) $resumen['monto_pagado']) }}</h5>
                                <span class="text-danger">RD$ {{ number_format((int) $resumen['monto_no_pagado']) }}</span>
                            </div></div>
                        </div>
                    </div>

                    <div class="card detalle-validacion-card">
                        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <h5 class="card-title mb-0">Detalle de validación</h5>
                            <span class="text-muted">Los totales responden a los filtros aplicados.</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Cédula</th>
                                            <th>Empleado</th>
                                            <th>Empresa / Agencia</th>
                                            <th class="text-end">Ventas</th>
                                            <th class="text-center">Días / Horas</th>
                                            <th class="text-end">Generado</th>
                                            <th class="text-end">Pagado</th>
                                            <th class="text-end">No pagado</th>
                                            <th class="estado-motivo-column">Estado y motivos</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($detalles as $detalle)
                                            <tr>
                                                <td class="fw-semibold">{{ $detalle->cedula }}</td>
                                                <td>
                                                    <div class="fw-medium">{{ $detalle->nombre }}</div>
                                                    <small class="text-muted">ID: {{ $detalle->empleadoid ?: 'N/D' }}</small>
                                                </td>
                                                <td>
                                                    <div>{{ $detalle->empresa }}</div>
                                                    <small class="text-muted">
                                                        {{ $detalle->ultima_terminal ?: 'Sin terminal' }} ·
                                                        {{ $detalle->ultima_agencia_nombre ?: 'Sin agencia' }}
                                                    </small>
                                                </td>
                                                <td class="text-end">RD$ {{ number_format($detalle->ventas_mes_actual) }}</td>
                                                <td class="text-center">
                                                    {{ $detalle->dias_ventas }} / {{ number_format((float) $detalle->horas_total, 2) }}
                                                </td>
                                                <td class="text-end">RD$ {{ number_format($detalle->incentivo_generado) }}</td>
                                                <td class="text-end text-success fw-medium">RD$ {{ number_format($detalle->monto_pagado) }}</td>
                                                <td class="text-end text-danger fw-medium">RD$ {{ number_format($detalle->monto_no_pagado) }}</td>
                                                <td class="estado-motivo-column">
                                                    @if (blank($detalle->empleadoid) && $detalle->monto_pagado > 0)
                                                        <span class="badge bg-info-subtle text-info">Sin IdEmpleado</span>
                                                    @endif
                                                    @if ($detalle->estado === 'pagado')
                                                        <span class="badge bg-success-subtle text-success">Pagado</span>
                                                    @elseif ($detalle->estado === 'pagado_parcial')
                                                        <span class="badge bg-warning-subtle text-warning">Pagado parcial</span>
                                                    @elseif ($detalle->estado === 'no_califica')
                                                        <span class="badge bg-secondary-subtle text-secondary">No calificó</span>
                                                    @else
                                                        <span class="badge bg-danger-subtle text-danger">No pagado</span>
                                                    @endif
                                                    @foreach ($detalle->motivos ?? [] as $motivo)
                                                        <span class="badge {{ $motivosClases[$motivo] ?? 'bg-light text-muted' }} mt-1">
                                                            {{ $motivosLabels[$motivo] ?? $motivo }}
                                                        </span>
                                                    @endforeach
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9" class="text-center text-muted py-5">
                                                    No hay personas que coincidan con los filtros seleccionados.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if ($detalles->hasPages())
                                <div class="mt-3">{{ $detalles->links() }}</div>
                            @endif
                        </div>
                    </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endsection
