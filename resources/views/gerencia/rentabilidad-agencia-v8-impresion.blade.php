<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Rentabilidad mensual V8</title>
    <style>
        @page { size: A4 landscape; margin: 26px 24px 35px; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #243247; font-size: 9px; }
        h1 { font-size: 19px; margin: 0 0 8px; }
        .subtitulo { color: #576579; margin: 0 0 14px; line-height: 1.6; }
        .bloque-nuevo { page-break-before: always; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #d6dfe9; padding: 6px 3px; vertical-align: middle; }
        th { background: #edf2f8; font-size: 8px; text-align: center; }
        .ruta { text-align: left; background: #203852; color: #fff; font-size: 12px; padding: 9px 7px; margin: 0; }
        .agencia { text-align: left; word-wrap: break-word; }
        .terminal { color: #687789; font-size: 7px; margin-top: 3px; }
        .importe { text-align: right; white-space: nowrap; font-size: 8px; font-weight: bold; color: #203852; }
        .tres-meses th { font-size: 7px; }
        .tres-meses .importe { font-size: 7px; padding-left: 1.5px; padding-right: 1.5px; }
        .tres-meses .totales .importe { font-size: 6.3px; }
        .porcentaje { margin-top: 3px; padding: 2px; font-size: 7px; font-weight: bold; }
        .porcentaje-premios { background: #fff3cd; color: #77520a; }
        .porcentaje-balance { background: #dcece4; color: #285541; }
        .positivo { color: #167047; }
        .negativo { color: #b32636; }
        .totales td { background: #edf2f8; font-weight: bold; border-top: 2px solid #8ba0b9; }
        .barra { margin-bottom: 15px; }
        .barra button { padding: 8px 16px; cursor: pointer; }
        @media print { .barra { display: none; } }
    </style>
</head>
<body>
    @if ($esImpresion)<div class="barra"><button type="button" onclick="window.print()">Imprimir documento</button></div>@endif
    @php
        $campos = ['ventas' => 'Ventas', 'premios_sacados' => 'Premios sacados', 'utilidad' => 'Utilidad', 'gastos' => 'Gastos', 'balance_general' => 'Balance'];
        $primero = true;
        $porRuta = ($filtros['agrupacion'] ?? 'terminal') === 'ruta';
    @endphp
    @foreach ($grupos as $ruta => $filas)
        @foreach ($bloquesMeses as $bloque)
            @php($anchoAgencia = count($bloque) === 3 ? 18 : 20)
            <div @class(['bloque-nuevo' => !$primero])>
                <h1>Rentabilidad mensual de agencias V8</h1>
                <p class="subtitulo">Empresa: {{ $filtros['empresa'] ?: 'Todas las empresas' }} | Año: {{ $filtros['anio'] }} | Productos: {{ $productos ? implode(', ', $productos) : 'Ninguno' }}<br>Importes en RD$, redondeados a enteros. % Premios = premios / ventas de loterías. % Balance = balance / ventas totales del mes. Sin ventas: —.</p>
                <h2 class="ruta">{{ $ruta }} | {{ $filas->count() }} {{ $porRuta ? 'rutas' : ($filas->count() === 1 ? 'agencia' : 'agencias') }}</h2>
                <table @class(['tres-meses' => count($bloque) === 3])>
                    <colgroup><col style="width:{{ $anchoAgencia }}%">@foreach ($bloque as $mes)@foreach ($campos as $campo => $etiqueta)<col style="width:{{ (100 - $anchoAgencia) / (count($bloque) * 5) }}%">@endforeach @endforeach</colgroup>
                    <thead>
                        <tr><th rowspan="2" class="agencia" style="width:{{ $anchoAgencia }}%">{{ $porRuta ? 'Ruta / Empresa' : 'Terminal / Agencia' }}<br>{{ $ruta }}</th>@foreach ($bloque as $mes)<th colspan="5" style="width:{{ (100 - $anchoAgencia) / count($bloque) }}%">{{ $mes['nombre'] }}</th>@endforeach</tr>
                        <tr>@foreach ($bloque as $mes)@foreach ($campos as $campo => $etiqueta)<th>{{ $etiqueta }}</th>@endforeach @endforeach</tr>
                    </thead>
                    <tbody>
                        @foreach ($filas as $fila)
                            <tr>
                                <td class="agencia"><strong>{{ $fila['nombre'] }}</strong><div class="terminal">@if ($porRuta){{ $fila['empresa'] }} | {{ $fila['cantidad_terminales'] }} terminales @else Terminal: {{ $fila['terminal'] }} @endif</div></td>
                                @foreach ($bloque as $indiceMes => $mes)
                                    @php($ventasMes = $fila['periodos'][$indiceMes]['ventas'])
                                    @php($ventasLoteriasMes = $fila['periodos'][$indiceMes]['ventas_loterias'] ?? 0)
                                    @foreach ($campos as $campo => $etiqueta)
                                        @php($valor = $fila['periodos'][$indiceMes][$campo])
                                        <td @class(['importe', 'positivo' => $campo === 'balance_general' && $valor > 0, 'negativo' => $campo === 'balance_general' && $valor < 0])><div>{{ number_format($valor, 0) }}</div>
                                            @if (in_array($campo, ['premios_sacados', 'balance_general'], true))
                                                @php($basePorcentaje = $campo === 'premios_sacados' ? $ventasLoteriasMes : $ventasMes)
                                                <div @class(['porcentaje', 'porcentaje-premios' => $campo === 'premios_sacados', 'porcentaje-balance' => $campo === 'balance_general'])>{{ $basePorcentaje != 0 ? number_format($valor / $basePorcentaje * 100, 2, '.', '').'%' : '—' }}</div>
                                            @endif
                                        </td>
                                    @endforeach
                                @endforeach
                            </tr>
                        @endforeach
                        <tr class="totales"><td>{{ $porRuta ? 'Totales de rutas' : 'Totales de la ruta' }}</td>
                            @foreach ($bloque as $indiceMes => $mes)
                                @php($ventasMes = $filas->sum(fn ($fila) => $fila['periodos'][$indiceMes]['ventas']))
                                @php($ventasLoteriasMes = $filas->sum(fn ($fila) => $fila['periodos'][$indiceMes]['ventas_loterias'] ?? 0))
                                @foreach ($campos as $campo => $etiqueta)
                                    @php($total = $filas->sum(fn ($fila) => $fila['periodos'][$indiceMes][$campo]))
                                    <td @class(['importe', 'positivo' => $campo === 'balance_general' && $total > 0, 'negativo' => $campo === 'balance_general' && $total < 0])><div>{{ number_format($total, 0) }}</div>
                                        @if (in_array($campo, ['premios_sacados', 'balance_general'], true))
                                            @php($basePorcentaje = $campo === 'premios_sacados' ? $ventasLoteriasMes : $ventasMes)
                                            <div @class(['porcentaje', 'porcentaje-premios' => $campo === 'premios_sacados', 'porcentaje-balance' => $campo === 'balance_general'])>{{ $basePorcentaje != 0 ? number_format($total / $basePorcentaje * 100, 2, '.', '').'%' : '—' }}</div>
                                        @endif
                                    </td>
                                @endforeach
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
            @php($primero = false)
        @endforeach
    @endforeach
    @if ($esImpresion)<script>window.addEventListener('load', function () { window.print(); });</script>@endif
</body>
</html>
