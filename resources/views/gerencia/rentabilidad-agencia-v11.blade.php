@extends('app')

@section('content')
    <style>
        #tablaRentabilidadV11 th, #tablaRentabilidadV11 td { white-space: nowrap; }
        #tablaRentabilidadV11 .inicio-mes { border-left: 2px solid var(--vz-border-color, #ced4da); }
        #tablaRentabilidadV11 th.grupo-mes { background: var(--vz-light, #f3f6f9); text-transform: capitalize; }
        #tablaRentabilidadV11 .porcentaje { display: inline-block; margin-top: 3px; padding: 2px 6px; border-radius: 3px; font-weight: 600; }
        #tablaRentabilidadV11 .porcentaje-premios { background: #fff3cd; color: #77520a; }
        #tablaRentabilidadV11 .devolucion-azul { color: #0d6efd; }
        #tablaRentabilidadV11 .linea-devolucion { display: flex; flex-direction: column; align-items: flex-end; gap: 3px; padding: 3px 0; }
        #tablaRentabilidadV11 .desglose-productos { min-width: 110px; font-weight: 500; }
        #tablaRentabilidadV11 .linea-producto { display: flex; justify-content: flex-end; padding: 5px 0; }
        #tablaRentabilidadV11 .clasificacion-terminal { margin-top: 7px; text-align: left; line-height: 1.5; }
    </style>
    <div class="main-content"><div class="page-content"><div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div><h4 class="mb-1">Rentabilidad de agencia V11</h4><p class="text-muted mb-0">Compara el comportamiento por terminal o por ruta, mes a mes.</p></div>
            <a href="{{ route('gerencia.index') }}" class="btn btn-outline-secondary">Volver a Gerencia</a>
        </div>
        <div class="card"><div class="card-body">
            <form method="GET" action="{{ route('gerencia.rentabilidad-agencia-v11') }}" class="row g-3 align-items-end" id="filtrosRentabilidadV11">
                <div class="col-md-2"><label for="anioV11" class="form-label">Año</label><input id="anioV11" type="number" name="anio" min="1900" max="9998" class="form-control" value="{{ $filtros['anio'] }}" required></div>
                @foreach (['mes_inicio' => 'Mes inicio', 'mes_fin' => 'Mes fin'] as $campo => $etiqueta)
                    <div class="col-md-2"><label for="{{ $campo }}V11" class="form-label">{{ $etiqueta }}</label>
                        <select id="{{ $campo }}V11" name="{{ $campo }}" class="form-select" required>
                            @foreach (range(1, 12) as $mes)
                                <option value="{{ $mes }}" @selected((int) $filtros[$campo] === $mes)>{{ \Carbon\Carbon::create(2000, $mes, 1)->locale('es')->isoFormat('MMMM') }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <div class="col-md-2"><label class="form-check"><input type="checkbox" name="anio_completo" id="anioCompletoV11" value="1" class="form-check-input" @checked(!empty($filtros['anio_completo']))> <span class="form-check-label">Año completo</span></label></div>
                <div class="col-md-3"><label for="empresaV11" class="form-label">Empresa</label><select id="empresaV11" name="empresa" class="form-select"><option value="">Todas las empresas</option>@foreach ($empresas as $empresa)<option value="{{ $empresa }}" @selected($filtros['empresa'] === $empresa)>{{ $empresa }}</option>@endforeach</select></div>
                <div class="col-md-3"><label for="rutaV11" class="form-label">Ruta</label><select id="rutaV11" name="ruta" class="form-select"><option value="">Todas las rutas</option>@foreach ($rutas as $ruta)<option value="{{ $ruta }}" @selected($filtros['ruta'] === $ruta)>{{ $ruta }}</option>@endforeach</select></div>
                <div class="col-md-2"><label for="agrupacionV11" class="form-label">Agrupar por</label><select id="agrupacionV11" name="agrupacion" class="form-select"><option value="terminal" @selected($filtros['agrupacion'] === 'terminal')>Terminal</option><option value="ruta" @selected($filtros['agrupacion'] === 'ruta')>Ruta</option></select></div>
                <div class="col-md-2"><label for="alcanceV11" class="form-label">Consultar</label><select id="alcanceV11" name="alcance" class="form-select"><option value="todas" @selected($filtros['alcance'] === 'todas')>Todas las terminales</option><option value="terminal" @selected($filtros['alcance'] === 'terminal')>Una terminal</option></select></div>
                <div class="col-md-3"><label for="terminalV11" class="form-label">Número de terminal</label><input id="terminalV11" name="terminal" class="form-control" value="{{ $filtros['terminal'] }}" placeholder="Ej.: 052232"></div>
                <div class="col-md-auto"><button class="btn btn-primary" type="submit">Consultar</button></div>
                <div class="col-12"><input type="hidden" name="productos_filtrados" value="1"><span class="form-label d-block">Productos en ventas</span><div class="d-flex gap-3 flex-wrap">
                    @foreach (\App\Services\RentabilidadAgenciaV10Service::PRODUCTOS as $producto)
                        <label class="form-check"><input type="checkbox" name="productos[]" value="{{ $producto }}" class="form-check-input" @checked(in_array($producto, $productos, true))><span class="form-check-label">{{ $producto }}</span></label>
                    @endforeach
                </div></div>
                <div class="col-12">
                    <fieldset class="border rounded p-3">
                        <legend class="float-none w-auto px-2 fs-6">Premios sacados incluidos</legend>
                        <input type="hidden" name="premios_filtrados" value="1">
                        <div class="d-flex gap-3 flex-wrap">
                            @foreach (\App\Services\RentabilidadAgenciaV10Service::PRODUCTOS_PREMIOS as $producto)
                                <label class="form-check" for="premiosProductoV11-{{ $loop->index }}"><input type="checkbox" id="premiosProductoV11-{{ $loop->index }}" name="premios_productos[]" value="{{ $producto }}" class="form-check-input" @checked(in_array($producto, $productosPremios, true))><span class="form-check-label">{{ $producto }}</span></label>
                            @endforeach
                        </div>
                        <p class="form-text mb-0">Ambos marcados incluyen todos los premios. Puedes incluir solo uno o desmarcar ambos para excluir los premios. Esta selección es independiente de ventas y actualiza utilidad, balance y sus porcentajes al pulsar Consultar.</p>
                    </fieldset>
                </div>
            </form>
            @if ($errors->any())<div class="alert alert-danger mt-3" role="alert">{{ $errors->first() }}</div>@endif
        </div></div>
        @if ($resultado !== null)
            <div class="card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div><h5 class="mb-1">Comparación mensual · {{ $resultado['desde']->format('Y-m') }} a {{ $resultado['hasta']->format('Y-m') }}</h5><p class="text-muted mb-0">Cada mes muestra ventas, premios sacados, utilidad, gastos y balance. Los importes corresponden exclusivamente a ese mes.</p></div>
                    @if ($informeId)<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalImpresionV11">Impresión personalizada</button>@endif
                </div>
                <div class="card-body">
                    <section aria-label="Plan de mejoras de terminales" class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Plan de mejoras de terminales</h6>
                            <span class="text-muted small">Total de esta consulta: <strong>{{ array_sum(array_column($resumenCategorias, 'cantidad')) }}</strong></span>
                        </div>
                        <div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3">
                            @foreach ($resumenCategorias as $categoria)
                                <div class="col">
                                    <div class="rounded p-3 h-100" style="background-color:{{ $categoria['fondo'] }};color:{{ $categoria['color'] }}">
                                        <div class="small fw-semibold mb-1">{{ $categoria['etiqueta'] }}</div>
                                        <div class="fs-2 fw-bold lh-1" data-categoria="{{ $categoria['etiqueta'] }}">{{ number_format($categoria['cantidad'], 0) }}</div>
                                        <small>terminales</small>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                    @if ($resultado['filas']->isEmpty())
                        <div class="alert alert-info">No se encontraron terminales activas con los filtros seleccionados.</div>
                    @else
                        <div id="cargandoV11" class="text-muted py-3" role="status">Preparando comparación mensual...</div>
                        <div id="contenedorTablaV11" class="table-responsive d-none">
                            <p class="text-muted small">Desplaza la tabla horizontalmente para comparar los meses. Los totales y las exportaciones respetan la búsqueda aplicada.</p>
                            <table id="tablaRentabilidadV11" class="table table-bordered table-striped align-middle w-100">
                                <thead>
                                    <tr>
                                        <th rowspan="2">{{ $filtros['agrupacion'] === 'ruta' ? 'Ruta / Empresa' : 'Terminal / Agencia' }}</th>
                                        @foreach ($meses as $mes)<th colspan="6" class="text-center grupo-mes">{{ $mes['nombre'] }}</th>@endforeach

                                    </tr>
                                    <tr>
                                        @foreach ($meses as $mes)
                                            @foreach (['ventas' => 'Ventas', 'ingresos_calculados' => 'Ingresos', 'premios_sacados' => 'Premios', 'utilidad' => 'Utilidad', 'gastos' => 'Gastos', 'balance_general' => 'Neto'] as $campo => $etiqueta)
                                                <th @class(['inicio-mes' => $loop->first])>{{ $etiqueta }} <span class="d-block small text-muted">{{ $mes['codigo'] }}</span></th>
                                            @endforeach
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody></tbody>
                                <tfoot class="table-light fw-semibold"><tr><td>Totales</td>@foreach ($meses as $mes)@foreach (range(1, 6) as $columna)<td class="text-end"></td>@endforeach @endforeach</tr></tfoot>
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
        <div class="modal fade" id="modalImpresionV11" tabindex="-1" aria-labelledby="tituloImpresionV11" aria-hidden="true">
            <div class="modal-dialog"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="tituloImpresionV11">Impresión personalizada</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <form method="GET" action="{{ route('gerencia.rentabilidad-agencia-v11.impresion') }}" target="_blank" rel="noopener" id="formImpresionV11">
                    <input type="hidden" name="informe_id" value="{{ $informeId }}">
                    <div class="modal-body">
                        <p class="text-muted">Período: {{ $resultado['desde']->format('Y-m') }} a {{ $resultado['hasta']->format('Y-m') }}. Empresa: {{ $filtros['empresa'] ?: 'Todas las empresas' }}.</p>
                        <label for="rutaImpresionV11" class="form-label">Ruta a imprimir</label>
                        <select id="rutaImpresionV11" name="ruta_impresion" class="form-select">
                            <option value="">Todas las rutas de esta consulta</option>
                            @foreach ($resultado['filas']->pluck('ruta')->filter()->unique()->sort()->values() as $ruta)
                                <option value="{{ $ruta }}">{{ $ruta }}</option>
                            @endforeach
                        </select>
                        <label for="tamanoHojaImpresionV11" class="form-label mt-3">Tamaño de hoja</label>
                        <select id="tamanoHojaImpresionV11" name="tamano_hoja" class="form-select">
                            <option value="letter">Carta (8.5 x 11 pulgadas)</option>
                            <option value="legal">Legal (8.5 x 14 pulgadas)</option>
                        </select>
                        <p class="text-muted small mt-3 mb-0">Agrupación: {{ $filtros['agrupacion'] === 'ruta' ? 'Por ruta y empresa' : 'Por terminal' }}. Impresión horizontal, con todos los meses consultados juntos, con importes redondeados a enteros y las columnas visibles del reporte. El tamaño de los montos se ajusta a la hoja seleccionada. La búsqueda de la tabla no limita la impresión. La consulta permanece disponible durante 10 minutos.</p>
                        <div id="estadoImpresionV11" class="mt-3" role="status" aria-live="polite"></div>
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
        window.rentabilidadV11 = {
            datos: {{ Illuminate\Support\Js::from($datosTabla) }},
            referenciasGlobales: {{ Illuminate\Support\Js::from($referenciasGlobales) }},
            agrupacion: {{ Illuminate\Support\Js::from($filtros['agrupacion']) }},
            meses: {{ Illuminate\Support\Js::from($meses) }},
            rutasDisponibles: {{ Illuminate\Support\Js::from($rutasDisponibles) }},
        };
    </script>
    <script src="{{ asset('js/pages/rentabilidad-agencia-v11-impresion.js') }}?v={{ hash_file('sha256', public_path('js/pages/rentabilidad-agencia-v11-impresion.js')) }}"></script>
    <script src="{{ asset('js/pages/rentabilidad-agencia-v11.js') }}?v={{ hash_file('sha256', public_path('js/pages/rentabilidad-agencia-v11.js')) }}"></script>
@endsection
