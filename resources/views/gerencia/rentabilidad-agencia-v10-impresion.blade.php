<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Rentabilidad mensual V10</title>
    <style>
        @page { size: {{ $tamanoHoja === 'legal' ? '14in 8.5in' : '11in 8.5in' }}; margin: 26pt 24pt 35pt; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #243247; font-size: 9px; }
        h1 { font-size: 15pt; margin: 0 0 6pt; }
        .subtitulo { color: #576579; margin: 0 0 8pt; line-height: 1.4; }
        .bloque-nuevo { page-break-before: always; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #d6dfe9; padding: 5pt 2pt; vertical-align: middle; }
        th { background: #edf2f8; font-size: {{ $tamanoFuenteImportes }}pt; text-align: center; overflow-wrap: break-word; }
        .ruta { text-align: left; background: #203852; color: #fff; font-size: 12px; padding: 9px 7px; margin: 0; }
        .agencia { text-align: left; word-wrap: break-word; }
        .terminal { color: #687789; font-size: 7px; margin-top: 3px; }
        .importe { text-align: right; white-space: nowrap; font-size: {{ $tamanoFuenteImportes }}pt; font-weight: bold; color: #203852; font-variant-numeric: tabular-nums; }
        .porcentaje { margin-top: 3px; padding: 2px; font-size: {{ round($tamanoFuenteImportes * 0.85, 2) }}pt; font-weight: bold; }
        .porcentaje-premios { background: #fff3cd; color: #77520a; }
        .porcentaje-utilidad { background: #dcece4; color: #285541; }
        .importe-general { color: #0d6efd; margin-top: 3px; }
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
        $campos = ['ventas' => 'Ventas', 'ingresos_calculados' => 'Ingresos calculado', 'premios_sacados_2' => 'Premios sacados 2', 'utilidad_2' => 'Utilidad 2', 'gastos' => 'Gastos', 'balance_general' => 'Balance'];

        $primero = true;
        $porRuta = ($filtros['agrupacion'] ?? 'terminal') === 'ruta';
    @endphp
    @foreach ($grupos as $ruta => $filas)
        @foreach ($bloquesMeses as $bloque)
            @php
                $anchoAgencia = 14;
                $anchoResultado = 14;
                $anchoMeses = 100 - $anchoAgencia - $anchoResultado;
            @endphp
            <div @class(['bloque-nuevo' => !$primero])>
                <h1>Rentabilidad mensual de agencias V10</h1>
                <p class="subtitulo">Premios sacados incluidos: {{ $productosPremios ? implode(', ', $productosPremios) : 'Ninguno' }}.</p>
                <p class="subtitulo">Ingresos calculado: tradicional 100%; recargas 7.5%; no tradicional según créditos globales guardados de las cuentas 400120001 / 200111001 de cada empresa y mes. El porcentaje no cambia al filtrar agencia o ruta. Sin porcentaje contable: faltan movimientos de las cuentas o una base de ventas positiva.</p>
                <p class="subtitulo">Empresa: {{ $filtros['empresa'] ?: 'Todas las empresas' }} | Año: {{ $filtros['anio'] }} | Productos: {{ $productos ? implode(', ', $productos) : 'Ninguno' }}<br>Hoja: {{ $tamanoHoja === 'legal' ? 'Legal (8.5 x 14)' : 'Carta (8.5 x 11)' }} horizontal. Importes en RD$, con dos decimales.</p>
                <h2 class="ruta">{{ $ruta }} | {{ $filas->count() }} {{ $porRuta ? 'rutas' : ($filas->count() === 1 ? 'agencia' : 'agencias') }}</h2>
                <p class="subtitulo">Balance general = Utilidad 2 - Gastos. Resultado Bruto = suma de los balances de todo el período. Sus tarjetas muestran los totales de Premios sacados 2 y Utilidad 2, con porcentajes fijos de 63% y 37% sobre las ventas tradicionales.</p>
                <table>
                    <colgroup><col style="width:{{ $anchoAgencia }}%">@foreach ($bloque as $mes)@foreach ($campos as $campo => $etiqueta)<col style="width:{{ $anchoMeses / (count($bloque) * count($campos)) }}%">@endforeach @endforeach<col style="width:{{ $anchoResultado }}%"></colgroup>
                    <thead>
                        <tr><th rowspan="2" class="agencia" style="width:{{ $anchoAgencia }}%">{{ $porRuta ? 'Ruta / Empresa' : 'Terminal / Agencia' }}<br>{{ $ruta }}</th>@foreach ($bloque as $mes)<th colspan="{{ count($campos) }}" style="width:{{ $anchoMeses / count($bloque) }}%">{{ $mes['nombre'] }}</th>@endforeach<th rowspan="2" class="resultado-bruto" style="width:{{ $anchoResultado }}%">Resultado Bruto<br>Todo el período</th></tr>
                        <tr>@foreach ($bloque as $mes)@foreach ($campos as $campo => $etiqueta)<th>{{ $etiqueta }}</th>@endforeach @endforeach</tr>
                    </thead>
                    <tbody>
                        @foreach ($filas as $fila)
                            <tr>
                                <td class="agencia"><strong>{{ $fila['nombre'] }}</strong><div class="terminal">@if ($porRuta){{ $fila['empresa'] }} | {{ $fila['cantidad_terminales'] }} terminales @else Terminal: {{ $fila['terminal'] }} @endif</div></td>
                                @foreach ($bloque as $indiceMes => $mes)
                                    @foreach ($campos as $campo => $etiqueta)
                                        @php
                                            $valor = match ($campo) {
                                                'porcentaje_ingresos' => null,
                                                'premios_sacados_2' => $fila['periodos'][$indiceMes]['premios_sacados_2'],
                                                'utilidad_2' => $fila['periodos'][$indiceMes]['utilidad_2'],
                                                default => $fila['periodos'][$indiceMes][$campo],
                                            };
                                        @endphp
                                        <td @class(['importe', 'positivo' => $campo === 'balance_general' && $valor > 0, 'negativo' => $campo === 'balance_general' && $valor < 0])>
                                            @if (in_array($campo, ['ventas', 'ingresos_calculados'], true))
                                                @foreach (['tradicionales' => 'Tradicional', 'no_tradicionales' => 'No tradicional', 'recargas' => 'Recargas'] as $producto => $nombre)
                                                    @php
                                                        $importeProducto = $fila['periodos'][$indiceMes][($campo === 'ventas' ? 'ventas_' : 'ingresos_').$producto];
                                                    @endphp
                                                    <div class="desglose-producto"><span class="producto-etiqueta">{{ $nombre }}</span>{{ $importeProducto === null ? 'Sin porcentaje' : number_format($importeProducto, 2) }}</div>
                                                @endforeach
                                            @endif
                                            @unless (in_array($campo, ['ventas', 'ingresos_calculados'], true))
                                                <div>{{ number_format($valor, 2) }}</div>
                                            @endunless
                                            @if (in_array($campo, ['premios_sacados_2', 'utilidad_2'], true))
                                                <div @class(['porcentaje', 'porcentaje-premios' => $campo === 'premios_sacados_2', 'porcentaje-utilidad' => $campo === 'utilidad_2'])>{{ $campo === 'premios_sacados_2' ? '63%' : '37%' }}</div>
                                            @endif
                                        </td>
                                    @endforeach
                                @endforeach
                                @php
                                    $periodos = collect($fila['periodos']);
                                    $resultadoBruto = round($periodos->sum('balance_general'), 2);
                                @endphp
                                <td @class(['importe', 'resultado-bruto', 'positivo' => $resultadoBruto > 0, 'negativo' => $resultadoBruto < 0])>
                                    <div>{{ number_format($resultadoBruto, 2) }}</div>
                                    <div class="importe-general">{{ number_format($periodos->sum('premios_sacados_2'), 2) }}</div>
                                    <div class="importe-general">{{ number_format($periodos->sum('utilidad_2'), 2) }}</div>
                                    @foreach (['premios_sacados' => '% Premios', 'utilidad' => '% Utilidad'] as $campo => $etiqueta)
                                        <div @class(['porcentaje', 'porcentaje-premios' => $campo === 'premios_sacados', 'porcentaje-utilidad' => $campo === 'utilidad'])>{{ $etiqueta }}: {{ $campo === 'premios_sacados' ? '63%' : '37%' }}</div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                        <tr class="totales"><td>{{ $porRuta ? 'Totales de rutas' : 'Totales de la ruta' }}</td>
                            @foreach ($bloque as $indiceMes => $mes)
                                @foreach ($campos as $campo => $etiqueta)
                                    @php
                                        $total = match ($campo) {
                                            'porcentaje_ingresos' => null,
                                            'premios_sacados_2' => $filas->sum(fn ($fila) => $fila['periodos'][$indiceMes]['premios_sacados_2']),
                                            'utilidad_2' => $filas->sum(fn ($fila) => $fila['periodos'][$indiceMes]['utilidad_2']),
                                            default => $filas->sum(fn ($fila) => $fila['periodos'][$indiceMes][$campo]),
                                        };
                                        $incompleto = $campo === 'ingresos_calculados' && $filas->contains(fn ($fila) => $fila['periodos'][$indiceMes][$campo] === null);
                                    @endphp
                                    <td @class(['importe', 'positivo' => $campo === 'balance_general' && $total > 0, 'negativo' => $campo === 'balance_general' && $total < 0])>
                                        @if (in_array($campo, ['ventas', 'ingresos_calculados'], true))
                                            @foreach (['tradicionales' => 'Tradicional', 'no_tradicionales' => 'No tradicional', 'recargas' => 'Recargas'] as $producto => $nombre)
                                                @php
                                                    $nombreCampo = ($campo === 'ventas' ? 'ventas_' : 'ingresos_').$producto;
                                                    $sinPorcentaje = $campo === 'ingresos_calculados' && $producto === 'no_tradicionales' && $incompleto;
                                                @endphp
                                                <div class="desglose-producto"><span class="producto-etiqueta">{{ $nombre }}</span>{{ $sinPorcentaje ? 'Sin porcentaje' : number_format($filas->sum(fn ($fila) => $fila['periodos'][$indiceMes][$nombreCampo]), 2) }}</div>
                                            @endforeach
                                        @endif
                                        @unless (in_array($campo, ['ventas', 'ingresos_calculados'], true))
                                            <div>{{ number_format($total, 2) }}</div>
                                        @endunless
                                        @if (in_array($campo, ['premios_sacados_2', 'utilidad_2'], true))
                                            <div @class(['porcentaje', 'porcentaje-premios' => $campo === 'premios_sacados_2', 'porcentaje-utilidad' => $campo === 'utilidad_2'])>{{ $campo === 'premios_sacados_2' ? '63%' : '37%' }}</div>
                                        @endif
                                    </td>
                                @endforeach
                            @endforeach
                            @php
                                $periodos = $filas->flatMap(fn (array $fila): array => $fila['periodos']);
                                $resultadoBruto = round($periodos->sum('balance_general'), 2);
                            @endphp
                            <td @class(['importe', 'resultado-bruto', 'positivo' => $resultadoBruto > 0, 'negativo' => $resultadoBruto < 0])>
                                <div>{{ number_format($resultadoBruto, 2) }}</div>
                                <div class="importe-general">{{ number_format($periodos->sum('premios_sacados_2'), 2) }}</div>
                                <div class="importe-general">{{ number_format($periodos->sum('utilidad_2'), 2) }}</div>
                                @foreach (['premios_sacados' => '% Premios', 'utilidad' => '% Utilidad'] as $campo => $etiqueta)
                                    <div @class(['porcentaje', 'porcentaje-premios' => $campo === 'premios_sacados', 'porcentaje-utilidad' => $campo === 'utilidad'])>{{ $etiqueta }}: {{ $campo === 'premios_sacados' ? '63%' : '37%' }}</div>
                                @endforeach
                            </td>
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
