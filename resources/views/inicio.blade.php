@extends('app')

@section('content')
    <link href="{{ asset('libs/swiper/swiper-bundle.min.css') }}" rel="stylesheet" />
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">TABLERO DE INDICADORES CLAVES</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="/">Inicio</a></li>
                                    <li class="breadcrumb-item active">Tablero</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                @php
                    $ventasTipos = $ventasInicio['tipos'] ?? [];
                    $ventaTradicional = (float) ($ventasTipos['tradicional']['total'] ?? 0);
                    $ventaNoTradicional = (float) ($ventasTipos['no_tradicional']['total'] ?? 0);
                    $ventaRecargas = (float) ($ventasTipos['recargas']['total'] ?? 0);
                    $agenciasConVenta = (int) ($ventasInicio['agencias_con_venta'] ?? 0);
                @endphp

                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="GET" action="{{ route('inicio.index') }}" class="row g-3 align-items-end">
                                    <div class="col-12 col-md-4 col-lg-3">
                                        <label for="fecha" class="form-label">Fecha de ventas</label>
                                        <input
                                            type="date"
                                            id="fecha"
                                            name="fecha"
                                            class="form-control"
                                            value="{{ $fechaSeleccionadaVentas ?? now()->subDay()->format('Y-m-d') }}"
                                            max="{{ now()->format('Y-m-d') }}">
                                    </div>
                                    <div class="col-12 col-md-8 col-lg-4 d-grid d-md-flex gap-2">
                                        <button type="submit" class="btn btn-primary">Filtrar</button>
                                        <a href="{{ route('inicio.index') }}" class="btn btn-light">Ayer</a>
                                    </div>
                                    <div class="col-12 col-lg-5">
                                        <div class="alert alert-info mb-0 py-2">
                                            Mostrando ventas del dia <strong>{{ \Carbon\Carbon::parse($fechaSeleccionadaVentas ?? now()->subDay()->toDateString())->format('d/m/Y') }}</strong>.
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-12">
                        <div class="card crm-widget">
                            <div class="card-body p-0">
                                <div class="row row-cols-xxl-5 row-cols-md-3 row-cols-1 g-0">
                                    <div class="col">
                                        <div class="py-4 px-3">
                                            <h5 class="text-muted text-uppercase fs-13">Agencias con ventas <i class="ri-arrow-up-circle-line text-success fs-18 float-end align-middle"></i></h5>
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0">
                                                    <i class="ri-store-2-line display-6 text-muted"></i>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <h2 class="mb-0"><span class="counter-value" data-target="{{ $agenciasConVenta }}">0</span></h2>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="mt-3 mt-md-0 py-4 px-3">
                                            <h5 class="text-muted text-uppercase fs-13">Agencias sin ventas <i class="ri-arrow-up-circle-line text-success fs-18 float-end align-middle"></i></h5>
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0">
                                                    <i class="ri-store-3-line display-6 text-muted"></i>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <h2 class="mb-0">$<span class="counter-value" data-target="489.4">0</span>k</h2>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="mt-3 mt-md-0 py-4 px-3">
                                            <h5 class="text-muted text-uppercase fs-13">Tradicional <i class="ri-arrow-down-circle-line text-danger fs-18 float-end align-middle"></i></h5>
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0">
                                                    <i class="ri-line-chart-line display-6 text-muted"></i>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <h2 class="mb-0">RD$ <span class="counter-value" data-target="{{ round($ventaTradicional, 2) }}">0</span></h2>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="mt-3 mt-lg-0 py-4 px-3">
                                            <h5 class="text-muted text-uppercase fs-13">No Tradicional <i class="ri-arrow-up-circle-line text-success fs-18 float-end align-middle"></i></h5>
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0">
                                                    <i class="ri-money-dollar-circle-line display-6 text-muted"></i>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <h2 class="mb-0">RD$ <span class="counter-value" data-target="{{ round($ventaNoTradicional, 2) }}">0</span></h2>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="mt-3 mt-lg-0 py-4 px-3">
                                            <h5 class="text-muted text-uppercase fs-13">Recargas <i class="ri-arrow-down-circle-line text-danger fs-18 float-end align-middle"></i></h5>
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0">
                                                    <i class="ri-shopping-bag-3-line display-6 text-muted"></i>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <h2 class="mb-0">RD$ <span class="counter-value" data-target="{{ round($ventaRecargas, 2) }}">0</span></h2>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xxl-3 col-md-6">
                        <div class="card">
                            <div class="card-header align-items-center d-flex">
                                <h4 class="card-title mb-0 flex-grow-1">Ventas de Productos</h4>
                            </div>
                            <div class="card-body pb-0">
                                <div id="sales-forecast-chart" data-colors='["--vz-primary", "--vz-success", "--vz-warning"]' class="apex-charts" dir="ltr"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xxl-3 col-md-6">
                        <div class="card card-height-100">
                            <div class="card-header align-items-center d-flex">
                                <h4 class="card-title mb-0 flex-grow-1">Tipo de venta</h4>
                            </div>
                            <div class="card-body pb-0">
                                <div id="deal-type-charts" data-colors='["--vz-warning", "--vz-danger", "--vz-success"]' class="apex-charts" dir="ltr"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xxl-6">
                        <div class="card card-height-100">
                            <div class="card-header align-items-center d-flex">
                                <h4 class="card-title mb-0 flex-grow-1">Resumen de balance</h4>
                            </div>
                            <div class="card-body px-0">
                                <div id="revenue-expenses-charts" data-colors='["--vz-success", "--vz-danger", "--vz-warning"]' class="apex-charts" dir="ltr"></div>

                                <ul class="list-inline main-chart text-center mb-0 mt-2">
                                    <li class="list-inline-item chart-border-left me-0 border-0">
                                        <h4 class="text-primary">$584k <span class="text-muted d-inline-block fs-13 align-middle ms-2"><i class="ri-checkbox-blank-circle-fill text-success me-1"></i>Ingresos</span></h4>
                                    </li>
                                    <li class="list-inline-item chart-border-left me-0">
                                        <h4>$497k<span class="text-muted d-inline-block fs-13 align-middle ms-2"><i class="ri-checkbox-blank-circle-fill text-danger me-1"></i>Gastos</span></h4>
                                    </li>
                                    <li class="list-inline-item chart-border-left me-0">
                                        <h4><span data-plugin="counterup">3.6</span>%<span class="text-muted d-inline-block fs-13 align-middle ms-2"><i class="ri-checkbox-blank-circle-fill text-warning me-1"></i>Margen de ganancia</span></h4>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-12">
                        <h4 class="mb-3">Productos No Tradicionales</h4>
                    </div>
                    <div class="col-lg-12">
                        <div class="swiper cryptoSlider">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="float-end">
                                                <span class="text-muted fs-18"><i class="mdi mdi-dots-horizontal"></i></span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <h6 class="mb-0 fs-14">Mega Chance</h6>
                                            </div>
                                            <div class="row align-items-end g-0">
                                                <div class="col-6">
                                                    <h5 class="mb-1 mt-4">$1,523,647</h5>
                                                    <p class="text-success fs-13 fw-medium mb-0">+13.11%<span class="text-muted ms-2 fs-10 text-uppercase">(btc)</span></p>
                                                </div>
                                                <div class="col-6">
                                                    <div class="apex-charts crypto-widget" data-colors='["--vz-success", "--vz-transparent"]' dir="ltr" id="bitcoin_sparkline_charts"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="swiper-slide">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="float-end">
                                                <span class="text-muted fs-18"><i class="mdi mdi-dots-horizontal"></i></span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <h6 class="mb-0 fs-14">Chance Exp</h6>
                                            </div>
                                            <div class="row align-items-end g-0">
                                                <div class="col-6">
                                                    <h5 class="mb-1 mt-4">$2,145,687</h5>
                                                    <p class="text-success fs-13 fw-medium mb-0">+15.08%<span class="text-muted ms-2 fs-10 text-uppercase">(ltc)</span></p>
                                                </div>
                                                <div class="col-6">
                                                    <div class="apex-charts crypto-widget" data-colors='["--vz-success", "--vz-transparent"]' dir="ltr" id="litecoin_sparkline_charts"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="swiper-slide">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="float-end">
                                                <span class="text-muted fs-18"><i class="mdi mdi-dots-horizontal"></i></span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <h6 class="mb-0 fs-14">Chance Exp Ext</h6>
                                            </div>
                                            <div class="row align-items-end g-0">
                                                <div class="col-6">
                                                    <h5 class="mb-1 mt-4">$3,312,870</h5>
                                                    <p class="text-success fs-13 fw-medium mb-0">+08.57%<span class="text-muted ms-2 fs-10 text-uppercase">(etc)</span></p>
                                                </div>
                                                <div class="col-6">
                                                    <div class="apex-charts crypto-widget" data-colors='["--vz-success", "--vz-transparent"]' dir="ltr" id="eathereum_sparkline_charts"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="swiper-slide">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="float-end">
                                                <span class="text-muted fs-18"><i class="mdi mdi-dots-horizontal"></i></span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <h6 class="mb-0 fs-14">ruleta express</h6>
                                            </div>
                                            <div class="row align-items-end g-0">
                                                <div class="col-6">
                                                    <h5 class="mb-1 mt-4">$1,820,045</h5>
                                                    <p class="text-danger fs-13 fw-medium mb-0">-09.21%<span class="text-muted ms-2 fs-10 text-uppercase">(bnb)</span></p>
                                                </div>
                                                <div class="col-6">
                                                    <div class="apex-charts crypto-widget" data-colors='["--vz-danger", "--vz-transparent"]' dir="ltr" id="binance_sparkline_charts"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="swiper-slide">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="float-end">
                                                <span class="text-muted fs-18"><i class="mdi mdi-dots-horizontal"></i></span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <h6 class="mb-0 fs-14">color ruleta express</h6>
                                            </div>
                                            <div class="row align-items-end g-0">
                                                <div class="col-6">
                                                    <h5 class="mb-1 mt-4">$9,458,153</h5>
                                                    <p class="text-success fs-13 fw-medium mb-0">+12.07%<span class="text-muted ms-2 fs-10 text-uppercase">(dash)</span></p>
                                                </div>
                                                <div class="col-6">
                                                    <div class="apex-charts crypto-widget" data-colors='["--vz-success", "--vz-transparent"]' dir="ltr" id="dash_sparkline_charts"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="swiper-slide">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="float-end">
                                                <span class="text-muted fs-18"><i class="mdi mdi-dots-horizontal"></i></span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <h6 class="mb-0 fs-14">doble chance exp</h6>
                                            </div>
                                            <div class="row align-items-end g-0">
                                                <div class="col-6">
                                                    <h5 class="mb-1 mt-4">$5,201,458</h5>
                                                    <p class="text-success fs-13 fw-medium mb-0">+14.99%<span class="text-muted ms-2 fs-10 text-uppercase">(usdt)</span></p>
                                                </div>
                                                <div class="col-6">
                                                    <div class="apex-charts crypto-widget" data-colors='["--vz-success", "--vz-transparent"]' dir="ltr" id="tether_sparkline_charts"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="swiper-slide">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="float-end">
                                                <span class="text-muted fs-18"><i class="mdi mdi-dots-horizontal"></i></span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <h6 class="mb-0 fs-14">doble chance express extraordinario</h6>
                                            </div>
                                            <div class="row align-items-end g-0">
                                                <div class="col-6">
                                                    <h5 class="mb-1 mt-4">$1,523,647</h5>
                                                    <p class="text-success fs-13 fw-medium mb-0">+13.11%<span class="text-muted ms-2 fs-10 text-uppercase">(nt1)</span></p>
                                                </div>
                                                <div class="col-6">
                                                    <div class="apex-charts crypto-widget" data-colors='["--vz-success", "--vz-transparent"]' dir="ltr" id="nueva_sparkline_charts_1"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="swiper-slide">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="float-end">
                                                <span class="text-muted fs-18"><i class="mdi mdi-dots-horizontal"></i></span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <h6 class="mb-0 fs-14">triple chance express extraordinario</h6>
                                            </div>
                                            <div class="row align-items-end g-0">
                                                <div class="col-6">
                                                    <h5 class="mb-1 mt-4">$2,145,687</h5>
                                                    <p class="text-success fs-13 fw-medium mb-0">+15.08%<span class="text-muted ms-2 fs-10 text-uppercase">(nt2)</span></p>
                                                </div>
                                                <div class="col-6">
                                                    <div class="apex-charts crypto-widget" data-colors='["--vz-success", "--vz-transparent"]' dir="ltr" id="nueva_sparkline_charts_2"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="swiper-slide">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="float-end">
                                                <span class="text-muted fs-18"><i class="mdi mdi-dots-horizontal"></i></span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <h6 class="mb-0 fs-14">extra lotto</h6>
                                            </div>
                                            <div class="row align-items-end g-0">
                                                <div class="col-6">
                                                    <h5 class="mb-1 mt-4">$3,312,870</h5>
                                                    <p class="text-success fs-13 fw-medium mb-0">+08.57%<span class="text-muted ms-2 fs-10 text-uppercase">(nt3)</span></p>
                                                </div>
                                                <div class="col-6">
                                                    <div class="apex-charts crypto-widget" data-colors='["--vz-success", "--vz-transparent"]' dir="ltr" id="nueva_sparkline_charts_3"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="swiper-slide">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="float-end">
                                                <span class="text-muted fs-18"><i class="mdi mdi-dots-horizontal"></i></span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <h6 class="mb-0 fs-14">power lotto</h6>
                                            </div>
                                            <div class="row align-items-end g-0">
                                                <div class="col-6">
                                                    <h5 class="mb-1 mt-4">$1,820,045</h5>
                                                    <p class="text-danger fs-13 fw-medium mb-0">-09.21%<span class="text-muted ms-2 fs-10 text-uppercase">(nt4)</span></p>
                                                </div>
                                                <div class="col-6">
                                                    <div class="apex-charts crypto-widget" data-colors='["--vz-danger", "--vz-transparent"]' dir="ltr" id="nueva_sparkline_charts_4"></div>
                                                </div>
                                            </div>
                                        </div>
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
    <script src="{{ asset('libs/apexcharts/apexcharts.min.js') }}"></script>
    <script src="{{ asset('libs/swiper/swiper-bundle.min.js') }}"></script>
    <script src="{{ asset('js/pages/dashboard-crm.init.js') }}"></script>
    <script src="{{ asset('js/pages/dashboard-crypto.init.js') }}"></script>
    <script>
        (function () {
            const bootExtraCards = () => {
                if (typeof ApexCharts === 'undefined' || typeof getChartColorsArray !== 'function') return;

                const renderExtraSparkline = (id, name, data) => {
                    const colors = getChartColorsArray(id);
                    if (!colors || !document.querySelector(`#${id}`)) return;

                    const options = {
                        series: [{ name, data }],
                        chart: {
                            width: 130,
                            height: 46,
                            type: 'area',
                            sparkline: { enabled: true },
                            toolbar: { show: false }
                        },
                        dataLabels: { enabled: false },
                        stroke: { curve: 'smooth', width: 1.5 },
                        fill: {
                            type: 'gradient',
                            gradient: {
                                shadeIntensity: 1,
                                inverseColors: false,
                                opacityFrom: 0.45,
                                opacityTo: 0.05,
                                stops: [50, 100, 100, 100]
                            }
                        },
                        colors
                    };

                    new ApexCharts(document.querySelector(`#${id}`), options).render();
                };

                renderExtraSparkline('nueva_sparkline_charts_1', 'doble chance express extraordinario', [85, 68, 35, 90, 8, 11, 26, 54]);
                renderExtraSparkline('nueva_sparkline_charts_2', 'triple chance express extraordinario', [25, 50, 41, 87, 12, 36, 9, 54]);
                renderExtraSparkline('nueva_sparkline_charts_3', 'extra lotto', [36, 21, 65, 22, 35, 50, 29, 44]);
                renderExtraSparkline('nueva_sparkline_charts_4', 'power lotto', [30, 58, 29, 89, 12, 36, 9, 54]);
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bootExtraCards);
            } else {
                bootExtraCards();
            }
        })();
    </script>
@endsection
