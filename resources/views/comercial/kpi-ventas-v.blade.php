@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Reporte KPI Ventas V</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="/">Inicio</a></li>
                                    <li class="breadcrumb-item">Comercial</li>
                                    <li class="breadcrumb-item active">kpi-ventas-v</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="GET" action="{{ route('comercial.kpi-ventas-v') }}" class="row g-3 align-items-end" id="form-filtro-kpi-ventas-v">
                                    <div class="col-md-3">
                                        <label class="form-label">Desde</label>
                                        <input type="date" name="fecha_inicio" class="form-control" value="{{ $fechaInicio ?? now()->startOfMonth()->format('Y-m-d') }}" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Hasta</label>
                                        <input type="date" name="fecha_fin" class="form-control" value="{{ $fechaFin ?? now()->endOfMonth()->format('Y-m-d') }}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <button type="submit" class="btn btn-primary me-2" id="btn-filtrar-kpi-ventas-v">
                                            <i class="ri-filter-3-line me-1"></i>Filtrar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="alert alert-info py-2 mb-3" role="alert">
                            <strong>Rango aplicado:</strong>
                            {{ $fechaInicio ?? now()->startOfMonth()->format('Y-m-d') }}
                            al
                            {{ $fechaFin ?? now()->endOfMonth()->format('Y-m-d') }}
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Tradicional</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-end justify-content-between mt-4">
                                    <div>
                                        <h4 class="fs-22 fw-semibold mb-0">RD$ {{ number_format($kpis['tradicional'] ?? 0, 2) }}</h4>
                                    </div>
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-primary-subtle rounded fs-3">
                                            <i class="ri-line-chart-line text-primary"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <p class="text-uppercase fw-medium text-muted mb-0">No Tradicional</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-end justify-content-between mt-4">
                                    <div>
                                        <h4 class="fs-22 fw-semibold mb-0">RD$ {{ number_format($kpis['no_tradicional'] ?? 0, 2) }}</h4>
                                    </div>
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-info-subtle rounded fs-3">
                                            <i class="ri-bar-chart-grouped-line text-info"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Recargas</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-end justify-content-between mt-4">
                                    <div>
                                        <h4 class="fs-22 fw-semibold mb-0">RD$ {{ number_format($kpis['recargas'] ?? 0, 2) }}</h4>
                                    </div>
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-success-subtle rounded fs-3">
                                            <i class="ri-exchange-dollar-line text-success"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
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
        const formFiltro = document.getElementById('form-filtro-kpi-ventas-v');
        const btnFiltrar = document.getElementById('btn-filtrar-kpi-ventas-v');

        if (!formFiltro || !btnFiltrar) return;

        formFiltro.addEventListener('submit', function () {
            btnFiltrar.disabled = true;
            Swal.fire({
                title: 'Cargando...',
                text: 'Procesando filtro, por favor espera.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => Swal.showLoading(),
            });
        });
    });
</script>
@endsection
