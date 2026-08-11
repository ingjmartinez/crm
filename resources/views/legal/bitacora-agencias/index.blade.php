@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <div>
                                <h4 class="mb-sm-0">Bitácora de agencia</h4>
                                <p class="text-muted mb-0">Expedientes legales conectados al catálogo general de terminales.</p>
                            </div>
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('legal.index') }}">Legal</a></li>
                                <li class="breadcrumb-item active">Bitácora de agencia</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    @foreach ([
                        ['Agencias', $resumen['agencias'], 'ri-store-2-line', 'primary'],
                        ['Contratos activos', $resumen['contratos_activos'], 'ri-file-list-3-line', 'success'],
                        ['Pagos pendientes', $resumen['pagos_pendientes'], 'ri-calendar-check-line', 'warning'],
                        ['Pagos vencidos', $resumen['pagos_vencidos'], 'ri-alarm-warning-line', 'danger'],
                    ] as [$titulo, $valor, $icono, $color])
                        <div class="col-xl-3 col-sm-6">
                            <div class="card mb-0 h-100">
                                <div class="card-body d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="text-muted text-uppercase fw-semibold small">{{ $titulo }}</div>
                                        <div class="fs-3 fw-bold mt-1">{{ number_format($valor) }}</div>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title rounded bg-{{ $color }}-subtle text-{{ $color }} fs-4">
                                            <i class="{{ $icono }}"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="card">
                    <div class="card-header">
                        <form method="GET" action="{{ route('legal.bitacora-agencias.index') }}" class="row g-2 align-items-end">
                            <div class="col-lg-9">
                                <label for="buscar-agencia-legal" class="form-label">Buscar terminal o agencia</label>
                                <input type="search" name="buscar" id="buscar-agencia-legal" class="form-control"
                                    value="{{ $buscar }}" placeholder="Terminal, código, nombre, empresa, ciudad o ruta">
                            </div>
                            <div class="col-lg-3 d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ri-search-line me-1"></i>Buscar
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Terminal</th>
                                        <th>Agencia</th>
                                        <th>Empresa</th>
                                        <th>Ciudad</th>
                                        <th>Ruta</th>
                                        <th class="text-center">Contratos</th>
                                        <th class="text-end">Expediente</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($agencias as $agencia)
                                        @php
                                            $codigoAgencia = $agencia->agencia ?: ($agencia->codigo ?? '-');
                                            $nombreAgencia = $agencia->nombre_agencia ?: ($agencia->nombre ?? 'Sin nombre registrado');
                                        @endphp
                                        <tr>
                                            <td><span class="badge bg-primary-subtle text-primary fs-6">{{ $agencia->terminal ?: '-' }}</span></td>
                                            <td>
                                                <div class="fw-semibold">{{ $nombreAgencia }}</div>
                                                <small class="text-muted">Código: {{ $codigoAgencia }}</small>
                                            </td>
                                            <td>{{ $agencia->empresa ?: '-' }}</td>
                                            <td>{{ $agencia->ciudad ?: '-' }}</td>
                                            <td>{{ $agencia->ruta ?: '-' }}</td>
                                            <td class="text-center">
                                                <span class="badge {{ $agencia->legal_contratos_count > 0 ? 'bg-success-subtle text-success' : 'bg-light text-muted' }}">
                                                    {{ number_format($agencia->legal_contratos_count) }}
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('legal.bitacora-agencias.show', $agencia) }}" class="btn btn-sm btn-soft-primary">
                                                    <i class="ri-folder-open-line me-1"></i>Abrir bitácora
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-5">No se encontraron agencias.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @if ($agencias->hasPages())
                        <div class="card-footer">{{ $agencias->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
