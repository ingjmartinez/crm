@extends('app')

@section('content')
    <style>
        #tablaRentabilidadV8 th, #tablaRentabilidadV8 td { white-space: nowrap; }
        #tablaRentabilidadV8 .inicio-mes { border-left: 2px solid var(--vz-border-color, #ced4da); }
        #tablaRentabilidadV8 th.grupo-mes { background: var(--vz-light, #f3f6f9); text-transform: capitalize; }
        #tablaRentabilidadV8 .porcentaje { display: inline-block; margin-top: 3px; padding: 2px 6px; border-radius: 3px; font-weight: 600; }
        #tablaRentabilidadV8 .porcentaje-premios { background: #fff3cd; color: #77520a; }
        #tablaRentabilidadV8 .porcentaje-balance { background: #dcece4; color: #285541; }
    </style>
    <div class="main-content"><div class="page-content"><div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div><h4 class="mb-1">Rentabilidad de agencia V8</h4><p class="text-muted mb-0">Compara el comportamiento por terminal o por ruta, mes a mes.</p></div>
            <a href="{{ route('gerencia.rentabilidad-agencia-v7') }}" class="btn btn-outline-secondary">Ver reporte V7</a>
        </div>
        <div class="card"><div class="card-body">
            <form method="GET" action="{{ route('gerencia.rentabilidad-agencia-v8') }}" class="row g-3 align-items-end" id="filtrosRentabilidadV8">
                <div class="col-md-2"><label for="anioV8" class="form-label">Año</label><input id="anioV8" type="number" name="anio" min="1900" max="9998" class="form-control" value="{{ $filtros['anio'] }}" required></div>
                @foreach (['mes_inicio' => 'Mes inicio', 'mes_fin' => 'Mes fin'] as $campo => $etiqueta)
                    <div class="col-md-2"><label for="{{ $campo }}V8" class="form-label">{{ $etiqueta }}</label>
                        <select id="{{ $campo }}V8" name="{{ $campo }}" class="form-select" required>
                            @foreach (range(1, 12) as $mes)
                                <option value="{{ $mes }}" @selected((int) $filtros[$campo] === $mes)>{{ \Carbon\Carbon::create(2000, $mes, 1)->locale('es')->isoFormat('MMMM') }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <div class="col-md-2"><label class="form-check"><input type="checkbox" name="anio_completo" id="anioCompletoV8" value="1" class="form-check-input" @checked(!empty($filtros['anio_completo']))> <span class="form-check-label">Año completo</span></label></div>
                <div class="col-md-3"><label for="empresaV8" class="form-label">Empresa</label><select id="empresaV8" name="empresa" class="form-select"><option value="">Todas las empresas</option>@foreach ($empresas as $empresa)<option value="{{ $empresa }}" @selected($filtros['empresa'] === $empresa)>{{ $empresa }}</option>@endforeach</select></div>
                <div class="col-md-3"><label for="rutaV8" class="form-label">Ruta</label><select id="rutaV8" name="ruta" class="form-select"><option value="">Todas las rutas</option>@foreach ($rutas as $ruta)<option value="{{ $ruta }}" @selected($filtros['ruta'] === $ruta)>{{ $ruta }}</option>@endforeach</select></div>
                <div class="col-md-2"><label for="agrupacionV8" class="form-label">Agrupar por</label><select id="agrupacionV8" name="agrupacion" class="form-select"><option value="terminal" @selected($filtros['agrupacion'] === 'terminal')>Terminal</option><option value="ruta" @selected($filtros['agrupacion'] === 'ruta')>Ruta</option></select></div>
                <div class="col-md-2"><label for="alcanceV8" class="form-label">Consultar</label><select id="alcanceV8" name="alcance" class="form-select"><option value="todas" @selected($filtros['alcance'] === 'todas')>Todas las terminales</option><option value="terminal" @selected($filtros['alcance'] === 'terminal')>Una terminal</option></select></div>
                <div class="col-md-3"><label for="terminalV8" class="form-label">Número de terminal</label><input id="terminalV8" name="terminal" class="form-control" value="{{ $filtros['terminal'] }}" placeholder="Ej.: 052232"></div>
                <div class="col-md-auto"><button class="btn btn-primary" type="submit">Consultar</button></div>
                <div class="col-12"><input type="hidden" name="productos_filtrados" value="1"><span class="form-label d-block">Productos incluidos</span><div class="d-flex gap-3 flex-wrap">
                    @foreach (\App\Services\RentabilidadAgenciaV7Service::PRODUCTOS as $producto)
                        <label class="form-check"><input type="checkbox" name="productos[]" value="{{ $producto }}" class="form-check-input" @checked(in_array($producto, $productos, true))><span class="form-check-label">{{ $producto }}</span></label>
                    @endforeach
                </div></div>
            </form>
            @if ($errors->any())<div class="alert alert-danger mt-3" role="alert">{{ $errors->first() }}</div>@endif
        </div></div>
        @if ($resultado !== null)
            <div class="card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div><h5 class="mb-1">Comparación mensual · {{ $resultado['desde']->format('Y-m') }} a {{ $resultado['hasta']->format('Y-m') }}</h5><p class="text-muted mb-0">Cada mes muestra ventas, premios sacados, utilidad, gastos y balance. Los importes corresponden exclusivamente a ese mes.</p></div>
                    @if ($informeId)<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalImpresionV8">Impresión personalizada</button>@endif
                </div>
                <div class="card-body">
                    <p class="text-muted small">Utilidad = ventas − premios sacados. Balance = utilidad − gastos. Gastos incluye costos, gastos contables y R20; R20 se aplica en los meses con ventas. El porcentaje de premios usa solo ventas Tradicionales y No Tradicionales; el de balance usa las ventas totales del mes, según los productos seleccionados. Sin ventas en la base correspondiente se muestra —.</p>
                    @if ($resultado['filas']->isEmpty())
                        <div class="alert alert-info">No se encontraron terminales activas con los filtros seleccionados.</div>
                    @else
                        <div id="cargandoV8" class="text-muted py-3" role="status">Preparando comparación mensual...</div>
                        <div id="contenedorTablaV8" class="d-none">
                            <p class="text-muted small">Desplaza la tabla horizontalmente para comparar los meses. Los totales y las exportaciones respetan la búsqueda aplicada.</p>
                            <table id="tablaRentabilidadV8" class="table table-bordered table-striped align-middle w-100">
                                <thead>
                                    <tr>
                                        <th rowspan="2">{{ $filtros['agrupacion'] === 'ruta' ? 'Ruta / Empresa' : 'Terminal / Agencia' }}</th>
                                        @foreach ($meses as $mes)<th colspan="5" class="text-center grupo-mes">{{ $mes['nombre'] }}</th>@endforeach
                                    </tr>
                                    <tr>
                                        @foreach ($meses as $mes)
                                            @foreach (['ventas' => 'Ventas', 'premios_sacados' => 'Premios sacados', 'utilidad' => 'Utilidad', 'gastos' => 'Gastos', 'balance_general' => 'Balance general'] as $campo => $etiqueta)
                                                <th @class(['inicio-mes' => $loop->first])>{{ $etiqueta }} <span class="d-block small text-muted">{{ $mes['codigo'] }}</span></th>
                                            @endforeach
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody></tbody>
                                <tfoot class="table-light fw-semibold"><tr><td>Totales</td>@foreach ($meses as $mes)@foreach (range(1, 5) as $columna)<td class="text-end"></td>@endforeach @endforeach</tr></tfoot>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div class="alert alert-info">Elige el año y el rango de meses, y pulsa Consultar para ver la evolución mensual.</div>
        @endif
    </div></div></div>
    @if ($informeId)
        <div class="modal fade" id="modalImpresionV8" tabindex="-1" aria-labelledby="tituloImpresionV8" aria-hidden="true">
            <div class="modal-dialog"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="tituloImpresionV8">Impresión personalizada</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <form method="GET" action="{{ route('gerencia.rentabilidad-agencia-v8.impresion') }}" target="_blank" rel="noopener" id="formImpresionV8">
                    <input type="hidden" name="informe_id" value="{{ $informeId }}">
                    <div class="modal-body">
                        <p class="text-muted">Período: {{ $resultado['desde']->format('Y-m') }} a {{ $resultado['hasta']->format('Y-m') }}. Empresa: {{ $filtros['empresa'] ?: 'Todas las empresas' }}.</p>
                        <label for="rutaImpresionV8" class="form-label">Ruta a imprimir</label>
                        <select id="rutaImpresionV8" name="ruta_impresion" class="form-select">
                            <option value="">Todas las rutas de esta consulta</option>
                            @foreach ($resultado['filas']->pluck('ruta')->filter()->unique()->sort()->values() as $ruta)
                                <option value="{{ $ruta }}">{{ $ruta }}</option>
                            @endforeach
                        </select>
                        <p class="text-muted small mt-3 mb-0">Agrupación: {{ $filtros['agrupacion'] === 'ruta' ? 'Por ruta y empresa' : 'Por terminal' }}. Se incluyen los resultados consultados con importes y totales por mes, en bloques de tres meses y sin decimales. La búsqueda de la tabla no limita la impresión. La consulta permanece disponible durante 10 minutos.</p>
                        <div id="estadoImpresionV8" class="mt-3" role="status" aria-live="polite"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="salida" value="imprimir" class="btn btn-outline-primary">Imprimir</button>
                        <button type="submit" name="salida" value="pdf" class="btn btn-primary">Generar PDF</button>
                    </div>
                </form>
            </div></div>
        </div>
    @endif
@endsection

@section('script')
    <script>
        window.rentabilidadV8 = {
            datos: {{ Illuminate\Support\Js::from($datosTabla) }},
            agrupacion: {{ Illuminate\Support\Js::from($filtros['agrupacion']) }},
            meses: {{ Illuminate\Support\Js::from($meses) }},
            rutasDisponibles: {{ Illuminate\Support\Js::from($rutasDisponibles) }},
        };
    </script>
    <script src="{{ asset('js/pages/rentabilidad-agencia-v8-impresion.js') }}?v={{ hash_file('sha256', public_path('js/pages/rentabilidad-agencia-v8-impresion.js')) }}"></script>
    <script src="{{ asset('js/pages/rentabilidad-agencia-v8.js') }}?v={{ hash_file('sha256', public_path('js/pages/rentabilidad-agencia-v8.js')) }}"></script>
@endsection
