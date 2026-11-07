<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Rentabilidad mensual V11</title>
    <style>
        @page { size: {{ $tamanoHoja === 'legal' ? '14in 8.5in' : '11in 8.5in' }}; margin: 12pt; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #243247; font-size: 9px; }
        h1 { font-size: 18pt; margin: 0 0 6pt; }
        .subtitulo { color: #576579; margin: 0 0 8pt; line-height: 1.4; }
        .bloque-nuevo { page-break-before: always; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #d6dfe9; padding: 5pt {{ \App\Services\RentabilidadAgenciaV11Pdf::margenCelda(count($bloquesMeses[0])) }}pt; vertical-align: middle; }
        th { background: #edf2f8; font-size: {{ $tamanoFuenteImportes }}pt; text-align: center; overflow-wrap: break-word; }
        .ruta { text-align: left; background: #203852; color: #fff; font-size: 12px; padding: 9px 7px; margin: 0; }
        .agencia { text-align: left; word-wrap: break-word; }
        .terminal { color: #687789; font-size: 7px; margin-top: 3px; }
        .clasificacion { margin-top: 5px; font-size: 7pt; line-height: 1.4; }
        .clasificacion .etiqueta { display: inline-block; padding: 2px 4px; font-weight: bold; }
        .importe { text-align: right; white-space: nowrap; font-size: {{ $tamanoFuenteImportes }}pt; font-weight: bold; color: #203852; font-variant-numeric: tabular-nums; }
        .porcentaje { margin-top: 3px; padding: 2px; font-size: {{ round($tamanoFuenteImportes * 0.85, 2) }}pt; font-weight: bold; }
        .porcentaje-premios { background: #fff3cd; color: #77520a; }
        .porcentaje-utilidad { background: #dcece4; color: #285541; }
        .importe-general { color: #0d6efd; margin-top: 3px; }
        .devolucion-terminal { font-size: {{ round($tamanoFuenteImportes * 0.85, 2) }}pt; white-space: nowrap; }
        .devolucion-terminal .porcentaje { display: inline-block; font-size: inherit; padding: 1px 2px; }
        .devolucion-azul { color: #0d6efd; }
        .monto-proyectado { color: #000; }
        .linea-devolucion { white-space: nowrap; padding: 2px 0; }
        .linea-devolucion .etiqueta-devolucion { display: block; text-align: right; white-space: nowrap; font-size: {{ round($tamanoFuenteImportes * 0.75, 2) }}pt; }
        .linea-devolucion .monto-devolucion { display: block; text-align: right; }
        .linea-devolucion .porcentaje { display: inline-block; font-size: inherit; padding: 1px 2px; }
        .resultado-bruto { border-left: 2px solid #8ba0b9; }
        .resultado-bruto .porcentaje { font-size: 6px; white-space: normal; }
        .positivo { color: #167047; }
        .negativo { color: #b32636; }
        .totales td { background: #edf2f8; font-weight: bold; border-top: 2px solid #8ba0b9; }
        .desglose-producto { padding: 3px 0; }
        .producto-etiqueta { display: block; color: #687789; font-size: {{ round($tamanoFuenteImportes * 0.8, 2) }}pt; font-weight: normal; }
        .barra { margin-bottom: 15px; }
        .barra button { padding: 8px 16px; cursor: pointer; }
        @media print { .barra { display: none; } }
    </style>
</head>
<body>
    @if ($esImpresion)<div class="barra"><button type="button" onclick="window.print()">Imprimir documento</button></div>@endif
    @php
        $campos = ['ventas' => 'Ventas', 'ingresos_calculados' => 'Ingresos', 'premios_sacados' => 'Premios', 'utilidad' => 'Utilidad', 'gastos' => 'Gastos', 'balance_general' => 'Neto'];
        $porcentajeGlobal = static fn (array $referencia, string $campo): string => ($referencia[$campo] ?? null) === null ? '—' : number_format($referencia[$campo], 2, '.', '').'%';

        $primero = true;
        $porRuta = ($filtros['agrupacion'] ?? 'terminal') === 'ruta';
    @endphp
    @foreach ($grupos as $ruta => $filas)
        @foreach ($bloquesMeses as $bloque)
            @php
                $anchoAgencia = \App\Services\RentabilidadAgenciaV11Pdf::ANCHO_AGENCIA * 100;
                $pesosColumnas = \App\Services\RentabilidadAgenciaV11Pdf::PESOS_COLUMNAS;
                $anchoMeses = 100 - $anchoAgencia;
            @endphp
            <div @class(['bloque-nuevo' => !$primero])>
                <h1>Rentabilidad mensual de agencias V11</h1>
                <p class="subtitulo">Premios sacados incluidos: {{ $productosPremios ? implode(', ', $productosPremios) : 'Ninguno' }}.</p>
                <p class="subtitulo">Ingresos calculado: tradicional 100%; recargas 7.5%; no tradicional según créditos globales guardados de las cuentas 400120001 / 200111001 de cada empresa y mes. El porcentaje no cambia al filtrar agencia o ruta. Sin porcentaje contable: faltan movimientos de las cuentas o una base de ventas positiva.</p>
                <p class="subtitulo">Empresa: {{ $filtros['empresa'] ?: 'Todas las empresas' }} | Año: {{ $filtros['anio'] }} | Productos: {{ $productos ? implode(', ', $productos) : 'Ninguno' }}<br>Hoja: {{ $tamanoHoja === 'legal' ? 'Legal (8.5 x 14)' : 'Carta (8.5 x 11)' }} horizontal. Importes en RD$, redondeados a enteros.</p>
                <h2 class="ruta">{{ $ruta }} | {{ $filas->count() }} {{ $porRuta ? 'rutas' : ($filas->count() === 1 ? 'agencia' : 'agencias') }}</h2>
                <p class="subtitulo">Premios sacados = ventas tradicionales por el porcentaje global del grupo. Utilidad = ventas tradicionales - premios. Neto = Utilidad - Gastos.</p>
                <table>
                    <colgroup><col style="width:{{ $anchoAgencia }}%">@foreach ($bloque as $mes)@foreach ($campos as $campo => $etiqueta)<col style="width:{{ $anchoMeses * $pesosColumnas[$campo] / (count($bloque) * array_sum($pesosColumnas)) }}%">@endforeach @endforeach</colgroup>
                    <thead>
                        <tr><th rowspan="2" class="agencia" style="width:{{ $anchoAgencia }}%">{{ $porRuta ? 'Ruta / Empresa' : 'Terminal / Agencia' }}<br>{{ $ruta }}</th>@foreach ($bloque as $mes)<th colspan="{{ count($campos) }}" style="width:{{ $anchoMeses / count($bloque) }}%">{{ $mes['nombre'] }}</th>@endforeach</tr>
                        <tr>@foreach ($bloque as $mes)@foreach ($campos as $campo => $etiqueta)<th style="width:{{ $anchoMeses * $pesosColumnas[$campo] / (count($bloque) * array_sum($pesosColumnas)) }}%">{{ $etiqueta }}</th>@endforeach @endforeach</tr>
                    </thead>
                    <tbody>
                        @foreach ($filas as $fila)
                            <tr>
                                <td class="agencia"><strong>{{ $fila['nombre'] }}</strong><div class="terminal">@if ($porRuta){{ $fila['empresa'] }} | {{ $fila['cantidad_terminales'] }} terminales @else Terminal: {{ $fila['terminal'] }} @endif</div>
                                    @if (isset($fila['clasificacion']))
                                        @php
                                            $clasificacion = $fila['clasificacion'];
                                        @endphp
                                        <div class="clasificacion">
                                            <span class="etiqueta" style="background-color:{{ $clasificacion['fondo'] }};color:{{ $clasificacion['color'] }}">{{ $clasificacion['etiqueta'] }}</span>
                                            <div>Margen estimado: {{ $clasificacion['margen'] === null ? '—' : number_format($clasificacion['margen'], 2).'%' }} · {{ $clasificacion['meses_ventas'] }} meses con ventas</div>
                                            @if ($clasificacion['provisional'])<div>Evaluación provisional</div>@endif
                                            @if ($clasificacion['deterioro'])<div style="color:#842029">En deterioro</div>@endif
                                        </div>
                                    @endif
                                </td>
                                @foreach ($bloque as $indiceMes => $mes)
                                    @foreach ($campos as $campo => $etiqueta)
                                        @php
                                            $valor = $fila['periodos'][$indiceMes][$campo];
                                        @endphp
                                        <td style="width:{{ $anchoMeses * $pesosColumnas[$campo] / (count($bloque) * array_sum($pesosColumnas)) }}%" @class(['importe', 'positivo' => $campo === 'balance_general' && $valor > 0, 'negativo' => $campo === 'balance_general' && $valor < 0, 'devolucion-azul' => $campo === 'utilidad'])>
                                            @if (in_array($campo, ['ventas', 'ingresos_calculados'], true))
                                                @foreach (['tradicionales', 'no_tradicionales', 'recargas'] as $producto)
                                                    @php
                                                        $importeProducto = $fila['periodos'][$indiceMes][($campo === 'ventas' ? 'ventas_' : 'ingresos_').$producto];
                                                    @endphp
                                                    <div class="desglose-producto">{{ $importeProducto === null ? 'Sin porcentaje' : number_format($importeProducto, 0) }}</div>
                                                @endforeach
                                            @endif
                                            @unless (in_array($campo, ['ventas', 'ingresos_calculados', 'premios_sacados'], true))
                                                <div>{{ $valor === null ? '—' : number_format($valor, 0) }}</div>
                                            @endunless
                                            @if ($campo === 'premios_sacados')
                                                @php
                                                    $referencia = $referenciasGlobales['empresas'][$fila['empresa']]['meses'][$mes['codigo']] ?? [];
                                                @endphp
                                                <div class="linea-devolucion"><span class="monto-devolucion">{{ $valor === null ? '—' : number_format($valor, 0) }}</span><span class="etiqueta-devolucion"><span class="porcentaje porcentaje-premios">{{ $porcentajeGlobal($referencia, 'premios_sacados') }}</span></span></div>
                                            @endif
                                        </td>
                                    @endforeach
                                @endforeach
                            </tr>
                        @endforeach
                        <tr class="totales"><td>{{ $porRuta ? 'Totales de rutas' : 'Totales de la ruta' }}</td>
                            @foreach ($bloque as $indiceMes => $mes)
                                @foreach ($campos as $campo => $etiqueta)
                                    @php
                                        $total = $filas->contains(fn ($fila) => $fila['periodos'][$indiceMes][$campo] === null) ? null : $filas->sum(fn ($fila) => $fila['periodos'][$indiceMes][$campo]);
                                        $incompleto = $campo === 'ingresos_calculados' && $filas->contains(fn ($fila) => $fila['periodos'][$indiceMes][$campo] === null);
                                    @endphp
                                    <td style="width:{{ $anchoMeses * $pesosColumnas[$campo] / (count($bloque) * array_sum($pesosColumnas)) }}%" @class(['importe', 'positivo' => $campo === 'balance_general' && $total > 0, 'negativo' => $campo === 'balance_general' && $total < 0, 'devolucion-azul' => $campo === 'utilidad'])>
                                        @if (in_array($campo, ['ventas', 'ingresos_calculados'], true))
                                            @foreach (['tradicionales', 'no_tradicionales', 'recargas'] as $producto)
                                                @php
                                                    $nombreCampo = ($campo === 'ventas' ? 'ventas_' : 'ingresos_').$producto;
                                                    $sinPorcentaje = $campo === 'ingresos_calculados' && $producto === 'no_tradicionales' && $incompleto;
                                                @endphp
                                                <div class="desglose-producto">{{ $sinPorcentaje ? 'Sin porcentaje' : number_format($filas->sum(fn ($fila) => $fila['periodos'][$indiceMes][$nombreCampo]), 0) }}</div>
                                            @endforeach
                                        @endif
                                        @unless (in_array($campo, ['ventas', 'ingresos_calculados', 'premios_sacados'], true))
                                            <div>{{ $total === null ? '—' : number_format($total, 0) }}</div>
                                        @endunless
                                        @if ($campo === 'premios_sacados')
                                            @php
                                                $ventasTradicionales = $filas->sum(fn ($fila) => $fila['periodos'][$indiceMes]['ventas_tradicionales']);
                                            @endphp
                                            <div class="linea-devolucion"><span class="monto-devolucion">{{ $total === null ? '—' : number_format($total, 0) }}</span><span class="etiqueta-devolucion"><span class="porcentaje porcentaje-premios">{{ $total === null || $ventasTradicionales == 0 ? '—' : number_format($total / $ventasTradicionales * 100, 2).'%' }}</span></span></div>
                                        @endif
                                    </td>
                                @endforeach
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
            @php
                $primero = false;
            @endphp
        @endforeach
    @endforeach
    @if ($esImpresion)<script>window.addEventListener('load', function () { window.print(); });</script>@endif
</body>
</html>
