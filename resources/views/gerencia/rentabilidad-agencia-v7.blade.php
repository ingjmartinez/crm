@extends('app')

@section('content')
    <div class="main-content"><div class="page-content"><div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h4>Rentabilidad de agencia V7</h4>
            <a href="{{ route('gerencia.rentabilidad-agencia-v6') }}" class="btn btn-outline-secondary">Ver reporte V6</a>
        </div>
        <div class="card"><div class="card-body">
            <p class="text-muted">Utilidad = ventas − premios sacados. Balance general = utilidad − gastos. Gastos incluye costos contables, gastos y R20 mensual, con las mismas cuentas activas que V6. La clasificación se calcula con el balance general de los meses con ventas.</p>
            <form method="GET" action="{{ route('gerencia.rentabilidad-agencia-v7') }}" class="row g-3 align-items-end" id="filtrosRentabilidadV7">
                <div class="col-md-2"><label for="anioV7" class="form-label">Año</label><input id="anioV7" type="number" name="anio" min="1900" max="9998" class="form-control" value="{{ $filtros['anio'] }}" required></div>
                @foreach (['mes_inicio' => 'Mes inicio', 'mes_fin' => 'Mes fin'] as $campo => $etiqueta)
                    <div class="col-md-2"><label for="{{ $campo }}V7" class="form-label">{{ $etiqueta }}</label>
                        <select id="{{ $campo }}V7" name="{{ $campo }}" class="form-select" required>
                            @foreach (range(1, 12) as $mes)
                                <option value="{{ $mes }}" @selected((int) $filtros[$campo] === $mes)>{{ \Carbon\Carbon::create(2000, $mes, 1)->locale('es')->isoFormat('MMMM') }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <div class="col-md-2"><label class="form-check"><input type="checkbox" name="anio_completo" id="anioCompletoV7" value="1" class="form-check-input" @checked(!empty($filtros['anio_completo']))> <span class="form-check-label">Año completo</span></label></div>
                <div class="col-md-3"><label for="empresaV7" class="form-label">Empresa</label><select id="empresaV7" name="empresa" class="form-select"><option value="">Todas las empresas</option>@foreach ($empresas as $empresa)<option value="{{ $empresa }}" @selected($filtros['empresa'] === $empresa)>{{ $empresa }}</option>@endforeach</select></div>
                <div class="col-md-2"><label for="alcanceV7" class="form-label">Consultar</label><select id="alcanceV7" name="alcance" class="form-select"><option value="todas" @selected($filtros['alcance'] === 'todas')>Todas las terminales</option><option value="terminal" @selected($filtros['alcance'] === 'terminal')>Una terminal</option></select></div>
                <div class="col-md-3"><label for="terminalV7" class="form-label">Número de terminal</label><input id="terminalV7" name="terminal" class="form-control" value="{{ $filtros['terminal'] }}" placeholder="Ej.: 052232"></div>
                <div class="col-md-auto"><button class="btn btn-primary" type="submit">Consultar</button></div>
                <div class="col-12"><input type="hidden" name="productos_filtrados" value="1"><span class="form-label d-block">Productos incluidos</span><div class="d-flex gap-3 flex-wrap">
                    @foreach (\App\Services\RentabilidadAgenciaV7Service::PRODUCTOS as $producto)
                        <label class="form-check"><input type="checkbox" name="productos[]" value="{{ $producto }}" class="form-check-input" @checked(in_array($producto, $productos, true))><span class="form-check-label">{{ $producto }}</span></label>
                    @endforeach
                </div></div>
            </form>
            @if ($errors->any())<div class="alert alert-danger mt-3">{{ $errors->first() }}</div>@endif
        </div></div>
        @if ($resultado !== null)
            <div class="card" id="resumenClasificacionesV7">
                <div class="card-header"><h5 class="mb-0">Resumen de terminales · Año {{ $resultado['desde']->year }}</h5></div>
                <div class="card-body row g-4">
                    <div class="col-md-4">
                        <p class="text-muted mb-1">Terminales evaluadas</p>
                        <div class="fs-2 fw-bold">{{ number_format($resultado['resumen_clasificaciones']['total']) }}</div>
                        <p class="mb-1">Con ventas: <strong>{{ number_format($resultado['resumen_clasificaciones']['con_ventas']) }}</strong> · Sin ventas: <strong>{{ number_format($resultado['resumen_clasificaciones']['sin_ventas']) }}</strong></p>
                        <p class="text-muted small mb-0">Período: {{ $resultado['desde']->format('Y-m') }} a {{ $resultado['hasta']->format('Y-m') }}. Conteos según los filtros consultados.</p>
                    </div>
                    <div class="col-md-8 table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light"><tr><th>Categoría</th><th class="text-end">Terminales</th></tr></thead>
                            <tbody>
                                @foreach ($resultado['resumen_clasificaciones']['categorias'] as $categoria)
                                    <tr><td><span class="badge bg-{{ $categoria['color'] }} {{ $categoria['color'] === 'warning' ? 'text-dark' : '' }}">{{ $categoria['nombre'] }}</span></td><td class="text-end">{{ number_format($categoria['cantidad']) }}</td></tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light fw-semibold"><tr><td>Total terminales</td><td class="text-end">{{ number_format($resultado['resumen_clasificaciones']['total']) }}</td></tr></tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="card"><div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">Rentabilidad por terminal · {{ $resultado['desde']->format('Y-m') }} a {{ $resultado['hasta']->format('Y-m') }}</h5>
                <a class="btn btn-outline-primary" target="_blank" rel="noopener" href="{{ route('gerencia.rentabilidad-agencia-v7.informe-ejecutivo', [...$filtros, 'productos_filtrados' => 1, 'productos' => $productos]) }}">Descargar informe ejecutivo PDF</a>
            </div><div class="card-body">
                <p class="text-muted small">Las clasificaciones consideran solo meses con ventas mayores que cero. Un balance de cero no es positivo. Los gastos de meses sin ventas sí forman parte de los totales del rango.</p>
                @if ($resultado['filas']->isEmpty())<div class="alert alert-info">No se encontraron terminales activas con los filtros seleccionados.</div>@else
                    <div id="cargandoV7" role="status" class="text-muted py-3">Preparando tabla...</div>
                    <div id="contenedorTablaV7" class="d-none">
                        <div class="row g-3 mb-3 align-items-end">
                            <div class="col-md-3">
                                <label for="categoriaTablaV7" class="form-label">Categoría</label>
                                <select id="categoriaTablaV7" class="form-select">
                                    <option value="">Todas las categorías</option>
                                    @foreach (\App\Services\ClasificadorRentabilidadMensual::UMBRALES as $categoria)
                                        <option value="{{ $categoria['nombre'] }}">{{ $categoria['nombre'] }}</option>
                                    @endforeach
                                    <option value="Sin ventas">Sin ventas</option>
                                </select>
                            </div>
                            <div class="col-md-6"><p class="text-muted small mb-0">Filtra las terminales ya consultadas. Los totales al pie y las exportaciones de la tabla se ajustan a las filas filtradas.</p></div>
                        </div>
                        <table id="tablaRentabilidadV7" class="table table-bordered table-striped align-middle w-100">
                            <thead><tr><th>Terminal</th><th>Agencia (nombre)</th><th>Ventas</th><th>Premios sacados</th><th>Utilidad</th><th>Gastos</th><th>Balance general</th><th>Clasificación</th><th>Acción</th></tr></thead>
                            <tbody></tbody>
                            <tfoot class="table-light fw-semibold"><tr><td>Totales</td><td>{{ $resultado['filas']->count() }} terminales</td>@foreach (\App\Services\RentabilidadAgenciaV7Service::IMPORTES as $campo)<td class="text-end">RD$ {{ number_format($resultado['totales'][$campo], 2) }}</td>@endforeach<td>—</td><td></td></tr></tfoot>
                        </table>
                    </div>
                @endif
            </div></div>
        @endif
    </div></div></div>
    <div class="modal fade" id="modalRentabilidadV7" tabindex="-1" aria-labelledby="tituloModalV7" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="tituloModalV7">Resumen</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body" id="contenidoModalV7"></div></div></div></div>
@endsection

@section('script')
    <script>
        window.rentabilidadV7 = {
            datos: {{ Illuminate\Support\Js::from($datosTabla) }},
            filtros: {{ Illuminate\Support\Js::from([...$filtros, 'productos_filtrados' => 1, 'productos' => $productos]) }},
            urls: {{ Illuminate\Support\Js::from(['resumen' => route('gerencia.rentabilidad-agencia-v7.resumen'), 'ver' => route('gerencia.rentabilidad-agencia-v7.detalle'), 'cuentas' => route('gerencia.rentabilidad-agencia-v7.cuentas')]) }},
        };
    </script>
    <script src="{{ asset('js/pages/rentabilidad-agencia-v7.js') }}?v={{ hash_file('sha256', public_path('js/pages/rentabilidad-agencia-v7.js')) }}"></script>
@endsection
