@extends('app')

@section('content')
    <div class="main-content"><div class="page-content"><div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h4>Rentabilidad de agencia V6</h4>
            <a href="{{ route('gerencia.rentabilidad-agencia-v5') }}" class="btn btn-outline-secondary">Ver reporte V5</a>
        </div>
        <div class="card"><div class="card-body">
            <p class="text-muted">Utilidad propia = ventas − premios propios pagados por esta terminal. Utilidad general resta además los premios ajenos que pagó esta terminal. Gastos incluye costos contables, gastos y R20 mensual, con las cuentas activas de Clasificación de cuentas de V5. Premios sacados es informativo y no se resta otra vez.</p>
            <form method="GET" action="{{ route('gerencia.rentabilidad-agencia-v6') }}" class="row g-3 align-items-end" id="filtrosRentabilidadV6">
                <div class="col-md-2"><label for="anioV6" class="form-label">Año</label><input id="anioV6" type="number" name="anio" min="1900" max="9998" class="form-control" value="{{ $filtros['anio'] }}" required></div>
                @foreach (['mes_inicio' => 'Mes inicio', 'mes_fin' => 'Mes fin'] as $campo => $etiqueta)
                    <div class="col-md-2"><label for="{{ $campo }}V6" class="form-label">{{ $etiqueta }}</label>
                        <select id="{{ $campo }}V6" name="{{ $campo }}" class="form-select" required>
                            @foreach (range(1, 12) as $mes)
                                <option value="{{ $mes }}" @selected((int) $filtros[$campo] === $mes)>{{ \Carbon\Carbon::create(2000, $mes, 1)->locale('es')->isoFormat('MMMM') }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <div class="col-md-2"><label class="form-check"><input type="checkbox" name="anio_completo" id="anioCompletoV6" value="1" class="form-check-input" @checked(!empty($filtros['anio_completo']))> <span class="form-check-label">Año completo</span></label></div>
                <div class="col-md-3"><label for="empresaV6" class="form-label">Empresa</label><select id="empresaV6" name="empresa" class="form-select"><option value="">Todas las empresas</option>@foreach ($empresas as $empresa)<option value="{{ $empresa }}" @selected($filtros['empresa'] === $empresa)>{{ $empresa }}</option>@endforeach</select></div>
                <div class="col-md-2"><label for="alcanceV6" class="form-label">Consultar</label><select id="alcanceV6" name="alcance" class="form-select"><option value="todas" @selected($filtros['alcance'] === 'todas')>Todas las terminales</option><option value="terminal" @selected($filtros['alcance'] === 'terminal')>Una terminal</option></select></div>
                <div class="col-md-3"><label for="terminalV6" class="form-label">Número de terminal</label><input id="terminalV6" name="terminal" class="form-control" value="{{ $filtros['terminal'] }}" placeholder="Ej.: 052232"></div>
                <div class="col-md-auto"><button class="btn btn-primary" type="submit">Consultar</button></div>
                <div class="col-12"><input type="hidden" name="productos_filtrados" value="1"><span class="form-label d-block">Productos incluidos</span><div class="d-flex gap-3 flex-wrap">
                    @foreach (\App\Services\RentabilidadAgenciaV6Service::PRODUCTOS as $producto)
                        <label class="form-check"><input type="checkbox" name="productos[]" value="{{ $producto }}" class="form-check-input" @checked(in_array($producto, $productos, true))><span class="form-check-label">{{ $producto }}</span></label>
                    @endforeach
                </div></div>
            </form>
            @if ($errors->any())<div class="alert alert-danger mt-3">{{ $errors->first() }}</div>@endif
        </div></div>
        @if ($resultado !== null)
            <div class="card" id="resumenClasificacionesV6">
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
                            <thead class="table-light"><tr><th>Categoría</th><th class="text-end">Clasificación propia</th><th class="text-end">Clasificación neta</th></tr></thead>
                            <tbody>
                                @foreach ($resultado['resumen_clasificaciones']['categorias'] as $categoria)
                                    <tr><td><span class="badge bg-{{ $categoria['color'] }} {{ $categoria['color'] === 'warning' ? 'text-dark' : '' }}">{{ $categoria['nombre'] }}</span></td><td class="text-end">{{ number_format($categoria['propia']) }}</td><td class="text-end">{{ number_format($categoria['neta']) }}</td></tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light fw-semibold"><tr><td>Total terminales</td><td class="text-end">{{ number_format($resultado['resumen_clasificaciones']['total']) }}</td><td class="text-end">{{ number_format($resultado['resumen_clasificaciones']['total']) }}</td></tr></tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="card"><div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">Rentabilidad por terminal · {{ $resultado['desde']->format('Y-m') }} a {{ $resultado['hasta']->format('Y-m') }}</h5>
                <a class="btn btn-outline-primary" target="_blank" rel="noopener" href="{{ route('gerencia.rentabilidad-agencia-v6.informe-ejecutivo', [...$filtros, 'productos_filtrados' => 1, 'productos' => $productos]) }}">Descargar informe ejecutivo PDF</a>
            </div><div class="card-body">
                <p class="text-muted small">Las clasificaciones consideran solo meses con ventas mayores que cero. Un balance de cero no es positivo. Los gastos de meses sin ventas sí forman parte de los totales del rango.</p>
                @if ($resultado['filas']->isEmpty())<div class="alert alert-info">No se encontraron terminales activas con los filtros seleccionados.</div>@else
                    <div id="cargandoV6" role="status" class="text-muted py-3">Preparando tabla...</div>
                    <div id="contenedorTablaV6" class="d-none">
                        <table id="tablaRentabilidadV6" class="table table-bordered table-striped align-middle w-100">
                            <thead><tr><th>Terminal</th><th>Agencia (nombre)</th><th>Ventas</th><th>Premios sacados</th><th>Premios pagados propios</th><th>Premios pagados no propios</th><th>Utilidad propia</th><th>Utilidad general</th><th>Gastos</th><th>Balance propio</th><th>Balance neto</th><th>Clasificación propia</th><th>Clasificación neta</th><th>Acción</th></tr></thead>
                            <tbody></tbody>
                            <tfoot class="table-light fw-semibold"><tr><td>Totales</td><td>{{ $resultado['filas']->count() }} terminales</td>@foreach (\App\Services\RentabilidadAgenciaV6Service::IMPORTES as $campo)<td class="text-end">RD$ {{ number_format($resultado['totales'][$campo], 2) }}</td>@endforeach<td>—</td><td>—</td><td></td></tr></tfoot>
                        </table>
                    </div>
                @endif
            </div></div>
        @endif
    </div></div></div>
    <div class="modal fade" id="modalRentabilidadV6" tabindex="-1" aria-labelledby="tituloModalV6" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="tituloModalV6">Resumen</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body" id="contenidoModalV6"></div></div></div></div>
@endsection

@section('script')
    <script>
        window.rentabilidadV6 = {
            datos: {{ Illuminate\Support\Js::from($datosTabla) }},
            filtros: {{ Illuminate\Support\Js::from([...$filtros, 'productos_filtrados' => 1, 'productos' => $productos]) }},
            urls: {{ Illuminate\Support\Js::from(['resumen' => route('gerencia.rentabilidad-agencia-v6.resumen'), 'ver' => route('gerencia.rentabilidad-agencia-v6.detalle'), 'cuentas' => route('gerencia.rentabilidad-agencia-v6.cuentas')]) }},
        };
    </script>
    <script src="{{ asset('js/pages/rentabilidad-agencia-v6.js') }}"></script>
@endsection
