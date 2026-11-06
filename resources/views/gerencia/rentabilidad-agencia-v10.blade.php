@extends('app')

@section('content')
    <style>
        #tablaRentabilidadV10 th, #tablaRentabilidadV10 td { white-space: nowrap; }
        #tablaRentabilidadV10 .inicio-mes { border-left: 2px solid var(--vz-border-color, #ced4da); }
        #tablaRentabilidadV10 th.grupo-mes { background: var(--vz-light, #f3f6f9); text-transform: capitalize; }
        #tablaRentabilidadV10 .porcentaje { display: inline-block; margin-top: 3px; padding: 2px 6px; border-radius: 3px; font-weight: 600; }
        #tablaRentabilidadV10 .porcentaje-premios { background: #fff3cd; color: #77520a; }
        #tablaRentabilidadV10 .porcentaje-utilidad { background: #dcece4; color: #285541; }
        #tablaRentabilidadV10 .importe-general { color: #0d6efd; margin-top: 3px; }
        #tablaRentabilidadV10 .devolucion-azul { color: #0d6efd; }
        #tablaRentabilidadV10 .monto-proyectado { color: #000; }
        #tablaRentabilidadV10 .desglose-devoluciones { min-width: 245px; }
        #tablaRentabilidadV10 .linea-devolucion { display: flex; justify-content: space-between; align-items: baseline; gap: 16px; padding: 3px 0; }
        #tablaRentabilidadV10 .desglose-productos { min-width: 215px; font-weight: 500; }
        #tablaRentabilidadV10 .desglose-porcentajes { min-width: 170px; }
        #tablaRentabilidadV10 .linea-producto { display: flex; justify-content: space-between; gap: 18px; padding: 5px 0; }
        #tablaRentabilidadV10 .producto-etiqueta { color: var(--vz-secondary-color, #687789); font-size: 12px; }
    </style>
    <div class="main-content"><div class="page-content"><div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div><h4 class="mb-1">Rentabilidad de agencia V10</h4><p class="text-muted mb-0">Compara el comportamiento por terminal o por ruta, mes a mes.</p></div>
            <a href="{{ route('gerencia.index') }}" class="btn btn-outline-secondary">Volver a Gerencia</a>
        </div>
        <div class="card"><div class="card-body">
            <form method="GET" action="{{ route('gerencia.rentabilidad-agencia-v10') }}" class="row g-3 align-items-end" id="filtrosRentabilidadV10">
                <div class="col-md-2"><label for="anioV10" class="form-label">Año</label><input id="anioV10" type="number" name="anio" min="1900" max="9998" class="form-control" value="{{ $filtros['anio'] }}" required></div>
                @foreach (['mes_inicio' => 'Mes inicio', 'mes_fin' => 'Mes fin'] as $campo => $etiqueta)
                    <div class="col-md-2"><label for="{{ $campo }}V10" class="form-label">{{ $etiqueta }}</label>
                        <select id="{{ $campo }}V10" name="{{ $campo }}" class="form-select" required>
                            @foreach (range(1, 12) as $mes)
                                <option value="{{ $mes }}" @selected((int) $filtros[$campo] === $mes)>{{ \Carbon\Carbon::create(2000, $mes, 1)->locale('es')->isoFormat('MMMM') }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <div class="col-md-2"><label class="form-check"><input type="checkbox" name="anio_completo" id="anioCompletoV10" value="1" class="form-check-input" @checked(!empty($filtros['anio_completo']))> <span class="form-check-label">Año completo</span></label></div>
                <div class="col-md-3"><label for="empresaV10" class="form-label">Empresa</label><select id="empresaV10" name="empresa" class="form-select"><option value="">Todas las empresas</option>@foreach ($empresas as $empresa)<option value="{{ $empresa }}" @selected($filtros['empresa'] === $empresa)>{{ $empresa }}</option>@endforeach</select></div>
                <div class="col-md-3"><label for="rutaV10" class="form-label">Ruta</label><select id="rutaV10" name="ruta" class="form-select"><option value="">Todas las rutas</option>@foreach ($rutas as $ruta)<option value="{{ $ruta }}" @selected($filtros['ruta'] === $ruta)>{{ $ruta }}</option>@endforeach</select></div>
                <div class="col-md-2"><label for="agrupacionV10" class="form-label">Agrupar por</label><select id="agrupacionV10" name="agrupacion" class="form-select"><option value="terminal" @selected($filtros['agrupacion'] === 'terminal')>Terminal</option><option value="ruta" @selected($filtros['agrupacion'] === 'ruta')>Ruta</option></select></div>
                <div class="col-md-2"><label for="alcanceV10" class="form-label">Consultar</label><select id="alcanceV10" name="alcance" class="form-select"><option value="todas" @selected($filtros['alcance'] === 'todas')>Todas las terminales</option><option value="terminal" @selected($filtros['alcance'] === 'terminal')>Una terminal</option></select></div>
                <div class="col-md-3"><label for="terminalV10" class="form-label">Número de terminal</label><input id="terminalV10" name="terminal" class="form-control" value="{{ $filtros['terminal'] }}" placeholder="Ej.: 052232"></div>
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
                                <label class="form-check" for="premiosProductoV10-{{ $loop->index }}"><input type="checkbox" id="premiosProductoV10-{{ $loop->index }}" name="premios_productos[]" value="{{ $producto }}" class="form-check-input" @checked(in_array($producto, $productosPremios, true))><span class="form-check-label">{{ $producto }}</span></label>
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
                    @if ($informeId)<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalImpresionV10">Impresión personalizada</button>@endif
                </div>
                <div class="card-body">
                    <p class="text-muted small">% ing muestra la división directa de ventas no tradicionales globales de la empresa y mes / créditos de la cuenta 400120001, con dos decimales sin redondear. Solo es informativo: no modifica los ingresos calculados. Sin ingresos contables o con importe cero se muestra —.</p>
                    <p class="text-muted small">Ingresos calculado = ventas tradicionales al 100% + ventas no tradicionales por su porcentaje contable global de los datos guardados (créditos 400120001 / créditos 200111001, de la empresa y mes) + recargas al 7.5%. El porcentaje contable aplicado a ingresos no cambia al filtrar agencia, ruta o productos. Esta columna no modifica la utilidad ni el balance.</p>
                    <p class="text-muted small">Resultado Bruto de cada mes = Utilidad - gastos. % Premios = premios sacados tradicionales de la empresa / ventas tradicionales de la empresa × 100, para el mismo mes. No incluye ventas ni premios no tradicionales ni recargas. Si se excluyen los premios tradicionales, el porcentaje de premios es cero. Sin ventas tradicionales se muestra —.</p>
                    <p class="text-muted small">Utilidad = ventas tradicionales - premios sacados tradicionales. Resultado Bruto = Utilidad - gastos. En Premios sacados: Dev T muestra la devolución y los premios reales de la terminal; Dev G muestra el porcentaje global del grupo y el monto de ventas tradicionales por ese porcentaje; Dev Proy muestra el 63% y su monto proyectado. Los importes respetan los productos seleccionados y los checks de premios.</p>
                    @if ($resultado['filas']->isEmpty())
                        <div class="alert alert-info">No se encontraron terminales activas con los filtros seleccionados.</div>
                    @else
                        <div id="cargandoV10" class="text-muted py-3" role="status">Preparando comparación mensual...</div>
                        <div id="contenedorTablaV10" class="table-responsive d-none">
                            <p class="text-muted small">Desplaza la tabla horizontalmente para comparar los meses. Los totales y las exportaciones respetan la búsqueda aplicada.</p>
                            <table id="tablaRentabilidadV10" class="table table-bordered table-striped align-middle w-100">
                                <thead>
                                    <tr>
                                        <th rowspan="2">{{ $filtros['agrupacion'] === 'ruta' ? 'Ruta / Empresa' : 'Terminal / Agencia' }}</th>
                                        @foreach ($meses as $mes)<th colspan="10" class="text-center grupo-mes">{{ $mes['nombre'] }}</th>@endforeach

                                    </tr>
                                    <tr>
                                        @foreach ($meses as $mes)
                                            @foreach (['ventas' => 'Ventas', 'ingresos_calculados' => 'Ingresos calculado', 'porcentaje_ingresos' => '% ing', 'porcentaje_ingresos_2' => '% ing 2', 'premios_sacados' => 'Premios sacados', 'premios_sacados_2' => 'Premios sacados 2', 'utilidad' => 'Utilidad', 'utilidad_2' => 'Utilidad 2', 'gastos' => 'Gastos', 'balance_general' => 'Resultado Bruto'] as $campo => $etiqueta)
                                                <th @class(['inicio-mes' => $loop->first])>{{ $etiqueta }} <span class="d-block small text-muted">{{ $mes['codigo'] }}</span></th>
                                            @endforeach
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody></tbody>
                                <tfoot class="table-light fw-semibold"><tr><td>Totales</td>@foreach ($meses as $mes)@foreach (range(1, 10) as $columna)<td class="text-end"></td>@endforeach @endforeach</tr></tfoot>
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
        <div class="modal fade" id="modalImpresionV10" tabindex="-1" aria-labelledby="tituloImpresionV10" aria-hidden="true">
            <div class="modal-dialog"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="tituloImpresionV10">Impresión personalizada</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <form method="GET" action="{{ route('gerencia.rentabilidad-agencia-v10.impresion') }}" target="_blank" rel="noopener" id="formImpresionV10">
                    <input type="hidden" name="informe_id" value="{{ $informeId }}">
                    <div class="modal-body">
                        <p class="text-muted">Período: {{ $resultado['desde']->format('Y-m') }} a {{ $resultado['hasta']->format('Y-m') }}. Empresa: {{ $filtros['empresa'] ?: 'Todas las empresas' }}.</p>
                        <label for="rutaImpresionV10" class="form-label">Ruta a imprimir</label>
                        <select id="rutaImpresionV10" name="ruta_impresion" class="form-select">
                            <option value="">Todas las rutas de esta consulta</option>
                            @foreach ($resultado['filas']->pluck('ruta')->filter()->unique()->sort()->values() as $ruta)
                                <option value="{{ $ruta }}">{{ $ruta }}</option>
                            @endforeach
                        </select>
                        <label for="tamanoHojaImpresionV10" class="form-label mt-3">Tamaño de hoja</label>
                        <select id="tamanoHojaImpresionV10" name="tamano_hoja" class="form-select">
                            <option value="letter">Carta (8.5 x 11 pulgadas)</option>
                            <option value="legal">Legal (8.5 x 14 pulgadas)</option>
                        </select>
                        <p class="text-muted small mt-3 mb-0">Agrupación: {{ $filtros['agrupacion'] === 'ruta' ? 'Por ruta y empresa' : 'Por terminal' }}. Impresión horizontal, en bloques de dos meses, con importes redondeados a enteros y las columnas visibles del reporte. El tamaño de los montos se ajusta a la hoja seleccionada. La búsqueda de la tabla no limita la impresión. La consulta permanece disponible durante 10 minutos.</p>
                        <div id="estadoImpresionV10" class="mt-3" role="status" aria-live="polite"></div>
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
        window.rentabilidadV10 = {
            datos: {{ Illuminate\Support\Js::from($datosTabla) }},
            referenciasGlobales: {{ Illuminate\Support\Js::from($referenciasGlobales) }},
            agrupacion: {{ Illuminate\Support\Js::from($filtros['agrupacion']) }},
            meses: {{ Illuminate\Support\Js::from($meses) }},
            rutasDisponibles: {{ Illuminate\Support\Js::from($rutasDisponibles) }},
        };
    </script>
    <script src="{{ asset('js/pages/rentabilidad-agencia-v10-impresion.js') }}?v={{ hash_file('sha256', public_path('js/pages/rentabilidad-agencia-v10-impresion.js')) }}"></script>
    <script src="{{ asset('js/pages/rentabilidad-agencia-v10.js') }}?v={{ hash_file('sha256', public_path('js/pages/rentabilidad-agencia-v10.js')) }}"></script>
@endsection
