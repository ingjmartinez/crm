@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Comercial</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="/">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('comercial.index') }}">Comercial</a></li>
                                    <li class="breadcrumb-item active">KPI Ventas</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="GET" action="{{ route('comercial.kpi-ventas') }}" class="row g-3 align-items-end" id="form-filtro-mes-kpi">
                                    <input type="hidden" name="meta_tradicional" id="meta-tradicional-hidden" value="{{ $metasDiarias['tradicional'] ?? 0 }}">
                                    <input type="hidden" name="meta_no_tradicional" id="meta-no-tradicional-hidden" value="{{ $metasDiarias['no_tradicional'] ?? 0 }}">
                                    <input type="hidden" name="meta_recargas" id="meta-recargas-hidden" value="{{ $metasDiarias['recargas'] ?? 0 }}">
                                    <div class="col-md-3">
                                        <label class="form-label">Mes</label>
                                        <input type="month" name="mes" class="form-control" value="{{ $mesSeleccionado ?? now()->format('Y-m') }}">
                                    </div>
                                    <div class="col-md-9">
                                        <button type="submit" class="btn btn-primary me-2" id="btn-filtrar-kpi">
                                            <i class="ri-filter-3-line me-1"></i>Filtrar
                                        </button>
                                        <a href="{{ route('comercial.kpi-ventas') }}" class="btn btn-light">Mes actual</a>
                                        <button type="button" class="btn btn-soft-info ms-2" data-bs-toggle="modal" data-bs-target="#modalMetaDiaria">
                                            <i class="ri-settings-3-line me-1"></i>Configurar meta diaria
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Tradicional Acumulado</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-end justify-content-between mt-4">
                                    <div>
                                        <h4 class="fs-22 fw-semibold mb-0">RD$ {{ number_format($kpis['tradicional'] ?? 0, 2) }}</h4>
                                        <small class="text-muted d-block mt-1">Meta diaria: <span id="meta-tradicional">RD$ 0.00</span></small>
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
                                        <p class="text-uppercase fw-medium text-muted mb-0">No Tradicional Acumulado</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-end justify-content-between mt-4">
                                    <div>
                                        <h4 class="fs-22 fw-semibold mb-0">RD$ {{ number_format($kpis['no_tradicional'] ?? 0, 2) }}</h4>
                                        <small class="text-muted d-block mt-1">Meta diaria: <span id="meta-no-tradicional">RD$ 0.00</span></small>
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
                                        <p class="text-uppercase fw-medium text-muted mb-0">Recargas Acumulado</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-end justify-content-between mt-4">
                                    <div>
                                        <h4 class="fs-22 fw-semibold mb-0">RD$ {{ number_format($kpis['recargas'] ?? 0, 2) }}</h4>
                                        <small class="text-muted d-block mt-1">Meta diaria: <span id="meta-recargas">RD$ 0.00</span></small>
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

                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <p class="text-muted mb-0">Fase 1: acumulado por mes.</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6">
                        <div class="card border-primary border-opacity-25">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="mb-0 text-primary">Meta mensual Tradicional</h6>
                                    <span class="badge bg-primary-subtle text-primary" id="pct-faltante-tradicional">{{ number_format($cumplimiento['tradicional']['pct_faltante'] ?? 0, 2) }}%</span>
                                </div>
                                <h5 class="fw-semibold mb-2" id="meta-mensual-tradicional">RD$ {{ number_format($cumplimiento['tradicional']['meta_mensual'] ?? 0, 2) }}</h5>
                                <small class="text-muted d-block">Faltante</small>
                                <small class="fw-medium text-danger" id="monto-faltante-tradicional">RD$ {{ number_format($cumplimiento['tradicional']['faltante'] ?? 0, 2) }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6">
                        <div class="card border-info border-opacity-25">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="mb-0 text-info">Meta mensual No Tradicional</h6>
                                    <span class="badge bg-info-subtle text-info" id="pct-faltante-no-tradicional">{{ number_format($cumplimiento['no_tradicional']['pct_faltante'] ?? 0, 2) }}%</span>
                                </div>
                                <h5 class="fw-semibold mb-2" id="meta-mensual-no-tradicional">RD$ {{ number_format($cumplimiento['no_tradicional']['meta_mensual'] ?? 0, 2) }}</h5>
                                <small class="text-muted d-block">Faltante</small>
                                <small class="fw-medium text-danger" id="monto-faltante-no-tradicional">RD$ {{ number_format($cumplimiento['no_tradicional']['faltante'] ?? 0, 2) }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6">
                        <div class="card border-success border-opacity-25">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="mb-0 text-success">Meta mensual Recargas</h6>
                                    <span class="badge bg-success-subtle text-success" id="pct-faltante-recargas">{{ number_format($cumplimiento['recargas']['pct_faltante'] ?? 0, 2) }}%</span>
                                </div>
                                <h5 class="fw-semibold mb-2" id="meta-mensual-recargas">RD$ {{ number_format($cumplimiento['recargas']['meta_mensual'] ?? 0, 2) }}</h5>
                                <small class="text-muted d-block">Faltante</small>
                                <small class="fw-medium text-danger" id="monto-faltante-recargas">RD$ {{ number_format($cumplimiento['recargas']['faltante'] ?? 0, 2) }}</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="modalMetaDiaria" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="ri-settings-3-line me-2"></i>Configurar Meta Diaria por Producto</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Tradicional (RD$)</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="input-meta-tradicional" placeholder="0.00">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">No Tradicional (RD$)</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="input-meta-no-tradicional" placeholder="0.00">
                                </div>
                                <div class="mb-0">
                                    <label class="form-label">Recargas (RD$)</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="input-meta-recargas" placeholder="0.00">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-primary" id="btn-guardar-meta-diaria">
                                    <i class="ri-save-line me-1"></i>Guardar configuración
                                </button>
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
        const STORAGE_KEY = 'comercial_kpi_meta_diaria_v1';
        const formFiltro = document.getElementById('form-filtro-mes-kpi');
        const botonFiltrar = document.getElementById('btn-filtrar-kpi');
        const btnGuardarMeta = document.getElementById('btn-guardar-meta-diaria');

        const inputTrad = document.getElementById('input-meta-tradicional');
        const inputNoTrad = document.getElementById('input-meta-no-tradicional');
        const inputRec = document.getElementById('input-meta-recargas');

        const inputTradHidden = document.getElementById('meta-tradicional-hidden');
        const inputNoTradHidden = document.getElementById('meta-no-tradicional-hidden');
        const inputRecHidden = document.getElementById('meta-recargas-hidden');

        const lblTrad = document.getElementById('meta-tradicional');
        const lblNoTrad = document.getElementById('meta-no-tradicional');
        const lblRec = document.getElementById('meta-recargas');

        const lblMetaMensualTrad = document.getElementById('meta-mensual-tradicional');
        const lblMetaMensualNoTrad = document.getElementById('meta-mensual-no-tradicional');
        const lblMetaMensualRec = document.getElementById('meta-mensual-recargas');

        const lblFaltanteTrad = document.getElementById('monto-faltante-tradicional');
        const lblFaltanteNoTrad = document.getElementById('monto-faltante-no-tradicional');
        const lblFaltanteRec = document.getElementById('monto-faltante-recargas');

        const lblPctTrad = document.getElementById('pct-faltante-tradicional');
        const lblPctNoTrad = document.getElementById('pct-faltante-no-tradicional');
        const lblPctRec = document.getElementById('pct-faltante-recargas');

        const acumulados = {
            tradicional: Number(@json($kpis['tradicional'] ?? 0)),
            no_tradicional: Number(@json($kpis['no_tradicional'] ?? 0)),
            recargas: Number(@json($kpis['recargas'] ?? 0)),
        };

        function formatCurrency(value) {
            return 'RD$ ' + Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function getConfig() {
            try {
                const raw = localStorage.getItem(STORAGE_KEY);
                if (!raw) {
                    return { tradicional: 0, no_tradicional: 0, recargas: 0 };
                }

                const parsed = JSON.parse(raw);
                return {
                    tradicional: Number(parsed.tradicional || 0),
                    no_tradicional: Number(parsed.no_tradicional || 0),
                    recargas: Number(parsed.recargas || 0),
                };
            } catch (_) {
                return { tradicional: 0, no_tradicional: 0, recargas: 0 };
            }
        }

        function setConfig(config) {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(config));
        }

        function renderMetas(config) {
            if (lblTrad) lblTrad.textContent = formatCurrency(config.tradicional);
            if (lblNoTrad) lblNoTrad.textContent = formatCurrency(config.no_tradicional);
            if (lblRec) lblRec.textContent = formatCurrency(config.recargas);

            if (inputTrad) inputTrad.value = config.tradicional;
            if (inputNoTrad) inputNoTrad.value = config.no_tradicional;
            if (inputRec) inputRec.value = config.recargas;

            if (inputTradHidden) inputTradHidden.value = config.tradicional;
            if (inputNoTradHidden) inputNoTradHidden.value = config.no_tradicional;
            if (inputRecHidden) inputRecHidden.value = config.recargas;

            renderCumplimiento(config);
        }

        function renderCumplimiento(config) {
            const metasMensuales = {
                tradicional: Number(config.tradicional || 0) * 30,
                no_tradicional: Number(config.no_tradicional || 0) * 30,
                recargas: Number(config.recargas || 0) * 30,
            };

            const faltantes = {
                tradicional: Math.max(0, metasMensuales.tradicional - acumulados.tradicional),
                no_tradicional: Math.max(0, metasMensuales.no_tradicional - acumulados.no_tradicional),
                recargas: Math.max(0, metasMensuales.recargas - acumulados.recargas),
            };

            const pctFaltantes = {
                tradicional: metasMensuales.tradicional > 0 ? (faltantes.tradicional / metasMensuales.tradicional) * 100 : 0,
                no_tradicional: metasMensuales.no_tradicional > 0 ? (faltantes.no_tradicional / metasMensuales.no_tradicional) * 100 : 0,
                recargas: metasMensuales.recargas > 0 ? (faltantes.recargas / metasMensuales.recargas) * 100 : 0,
            };

            if (lblMetaMensualTrad) lblMetaMensualTrad.textContent = formatCurrency(metasMensuales.tradicional);
            if (lblMetaMensualNoTrad) lblMetaMensualNoTrad.textContent = formatCurrency(metasMensuales.no_tradicional);
            if (lblMetaMensualRec) lblMetaMensualRec.textContent = formatCurrency(metasMensuales.recargas);

            if (lblFaltanteTrad) lblFaltanteTrad.textContent = formatCurrency(faltantes.tradicional);
            if (lblFaltanteNoTrad) lblFaltanteNoTrad.textContent = formatCurrency(faltantes.no_tradicional);
            if (lblFaltanteRec) lblFaltanteRec.textContent = formatCurrency(faltantes.recargas);

            if (lblPctTrad) lblPctTrad.textContent = pctFaltantes.tradicional.toFixed(2) + '%';
            if (lblPctNoTrad) lblPctNoTrad.textContent = pctFaltantes.no_tradicional.toFixed(2) + '%';
            if (lblPctRec) lblPctRec.textContent = pctFaltantes.recargas.toFixed(2) + '%';
        }

        const configInicial = getConfig();
        renderMetas(configInicial);

        if (!formFiltro || !botonFiltrar) return;

        formFiltro.addEventListener('submit', function () {
            botonFiltrar.disabled = true;

            Swal.fire({
                title: 'Cargando...',
                text: 'Procesando filtro, por favor espera.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        });

        if (btnGuardarMeta) {
            btnGuardarMeta.addEventListener('click', function () {
                const config = {
                    tradicional: Math.max(0, Number(inputTrad?.value || 0)),
                    no_tradicional: Math.max(0, Number(inputNoTrad?.value || 0)),
                    recargas: Math.max(0, Number(inputRec?.value || 0)),
                };

                setConfig(config);
                renderMetas(config);

                const modalEl = document.getElementById('modalMetaDiaria');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                Swal.fire({
                    icon: 'success',
                    title: 'Configuración guardada',
                    text: 'Las metas diarias por producto fueron actualizadas.',
                    timer: 1700,
                    showConfirmButton: false
                });
            });
        }
    });
</script>
@endsection
