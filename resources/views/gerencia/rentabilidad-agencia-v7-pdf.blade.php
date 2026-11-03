<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 28px 26px 40px; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #243047; }
        .report-header { padding-top: 8px; }
        h1 { font-size: 19px; margin: 0 0 6px; color: #17365d; }
        h2 { font-size: 13px; margin: 6px 0 4px; color: #17365d; }
        p { margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d7dfe8; padding: 6px; vertical-align: middle; }
        th { background: #edf2f7; text-align: left; }
        thead { display: table-header-group; }
        tfoot { display: table-row-group; font-weight: bold; background: #edf2f7; }
        tr, .section { page-break-inside: avoid; }
        .muted { color: #667085; }
        .small { font-size: 9px; }
        .right { text-align: right; }
        .positivo { color: #067647; }
        .negativo { color: #b42318; }
        .cards td { width: 25%; padding: 7px; }
        .cards strong { display: block; font-size: 15px; margin-top: 5px; }
        .summary { font-size: 10px; }
        .summary td, .summary th { padding: 2px 6px; }
        .financial td { width: 33.33%; padding: 4px 6px; }
        .financial strong { display: block; font-size: 13px; margin-top: 3px; }
        .badge { display: inline-block; border-radius: 3px; padding: 1px 6px; }
        .success { color: #067647; background: #dcfae6; }
        .primary { color: #175cd3; background: #eff4ff; }
        .info { color: #0e5968; background: #cff4fc; }
        .warning { color: #854a0e; background: #fef0c7; }
        .danger { color: #b42318; background: #fee4e2; }
        .secondary { color: #475467; background: #f2f4f7; }
        .nueva-pagina { page-break-before: always; }
        .terminales { font-size: 9px; table-layout: fixed; }
        .terminales td, .terminales th { padding: 3px; }
        .terminales .right { white-space: nowrap; }
        .terminales .agencia { width: 17%; }
        .terminales .importe { width: 10.5%; }
        .terminales .clasificacion { width: 11.5%; }
        .terminales .observaciones { width: 19%; }
        .terminales .agencia, .terminales .observaciones { overflow-wrap: break-word; }
        .terminal-referencia { display: block; margin-top: 1px; color: #667085; font-size: 8px; }
        .observacion { margin: 0 0 1px; font-size: 8.5px; line-height: 1.3; }
        .obs-alerta { color: #b42318; font-weight: bold; }
        .obs-atencion { color: #854a0e; }
        .obs-normal { color: #475467; }
        .print-toolbar { margin-bottom: 20px; font-family: sans-serif; font-size: 14px; }
        .print-toolbar button { padding: 10px 18px; cursor: pointer; }
        @media screen { body.print-preview { max-width: 1120px; margin: 24px auto; padding: 20px; } }
        @media print { .print-toolbar { display: none !important; } }
    </style>
</head>
<body @class(['print-preview' => $esImpresion ?? false])>
    @php($configuracionClasificacion = $resultado['configuracion_clasificacion'] ?? config('rentabilidad'))
    @php($contenido = $contenido ?? 'resumen')
    @if ($esImpresion ?? false)
        <div class="print-toolbar"><button type="button" onclick="window.print()">Imprimir</button></div>
    @endif
    <div class="report-header">
    <h1>{{ $contenido === 'resumen' ? 'Informe ejecutivo de rentabilidad de agencia' : 'Informe de rentabilidad de agencia' }}</h1>
    <p><strong>Año {{ $resultado['desde']->year }}</strong> · Período: {{ $resultado['desde']->format('Y-m') }} a {{ $resultado['hasta']->format('Y-m') }}</p>
    <p class="muted">Empresa: {{ ($filtros['empresa'] ?? '') ?: 'Todas las empresas' }} · Terminal: {{ ($filtros['alcance'] ?? 'todas') === 'terminal' ? $filtros['terminal'] : 'Todas' }}</p>
    <p class="muted">Ruta: {{ ($filtros['ruta'] ?? '') ?: 'Todas las rutas' }}</p>
    <p class="muted small">Productos: {{ implode(', ', $productos) ?: 'Ninguno' }}. Generado: {{ now()->format('d/m/Y H:i') }}.</p>
    @if ($muestra ?? false)
        <p class="muted small">Muestra de formato con observaciones orientativas para revisión.</p>
    @endif
    </div>

    @if ($contenido !== 'terminales')
    <div class="section">
        <h2>Terminales evaluadas en el período</h2>
        <table class="cards">
            <tr>
                <td>Terminales evaluadas<strong>{{ number_format($resultado['resumen_clasificaciones']['total']) }}</strong></td>
                <td>Con ventas<strong>{{ number_format($resultado['resumen_clasificaciones']['con_ventas']) }}</strong></td>
                <td>Sin ventas<strong>{{ number_format($resultado['resumen_clasificaciones']['sin_ventas']) }}</strong></td>
                <td>Meses del período<strong>{{ $resultado['hasta']->month - $resultado['desde']->month + 1 }}</strong></td>
            </tr>
        </table>
        <h2>Clasificación de terminales · Año {{ $resultado['desde']->year }}</h2>
        <table class="summary">
            <thead><tr><th>Categoría</th><th class="right">Terminales</th></tr></thead>
            <tbody>
                @foreach ($resultado['resumen_clasificaciones']['categorias'] as $categoria)
                    <tr><td><span class="badge {{ $categoria['color'] }}">{{ $categoria['nombre'] }}</span></td><td class="right">{{ number_format($categoria['cantidad']) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot><tr><td>Total terminales</td><td class="right">{{ number_format($resultado['resumen_clasificaciones']['total']) }}</td></tr></tfoot>
        </table>
        <p class="small"><strong>En deterioro: {{ number_format($resultado['resumen_clasificaciones']['en_deterioro']) }}</strong> terminales. Es una alerta adicional; no cambia la categoría.</p>
        <p class="muted small">Conteos según los filtros consultados. Se requieren {{ $configuracionClasificacion['min_meses'] }} meses con ventas para clasificar. La recuperación y el deterioro consideran los últimos {{ $configuracionClasificacion['ventana_reciente'] }} meses con ventas. Un balance cero no es positivo. Venta mínima mensual para clasificar: RD$ {{ number_format($configuracionClasificacion['venta_minima_mes'], 2) }}.</p>
    </div>

    <div class="section">
        @if (($configuracionClasificacion['perdida_maxima_rescate'] ?? 0) > 0)
            <p class="muted small">Con el historial mínimo requerido, una pérdida acumulada menor de RD$ {{ number_format($configuracionClasificacion['perdida_maxima_rescate'], 2) }} también permite clasificar como Para rescatar.</p>
        @endif
        @if (isset($configuracionClasificacion['umbral_regular']))
            <p class="muted small">Regular requiere balance acumulado positivo y al menos {{ $configuracionClasificacion['umbral_regular'] }}% de meses positivos, sin alcanzar Buena.</p>
        @endif
        <h2>Resultados del período</h2>
        <table class="financial">
            @foreach (collect(['ventas' => 'Ventas', 'premios_sacados' => 'Premios sacados', 'utilidad' => 'Utilidad', 'gastos' => 'Costos + gastos + R20', 'balance_general' => 'Balance general'])->chunk(3) as $indicadores)
                <tr>
                    @foreach ($indicadores as $campo => $etiqueta)
                        <td>{{ $etiqueta }}<strong class="{{ str_starts_with($campo, 'balance') ? ($resultado['totales'][$campo] > 0 ? 'positivo' : ($resultado['totales'][$campo] < 0 ? 'negativo' : '')) : '' }}">RD$ {{ number_format($resultado['totales'][$campo], 2) }}</strong></td>
                    @endforeach
                    @if ($indicadores->count() < 3)<td></td>@endif
                </tr>
            @endforeach
        </table>
        <p class="muted small">Utilidad = ventas - premios sacados. Balance general = utilidad - gastos. Gastos incluye costos contables, gastos y R20 mensual. La clasificación utiliza el balance general de los meses con ventas.</p>
    </div>

    <p class="muted small">Los totales incluyen las terminales evaluadas con los filtros indicados.</p>
    @endif

    @if ($contenido !== 'resumen')
        @forelse ($resultado['rutas_impresion'] as $grupo)
            <div @class(['nueva-pagina' => ! $loop->first])>
                <h2>Ruta: {{ $grupo['ruta'] ?: 'Sin ruta asignada' }} · {{ count($grupo['terminales']) }} {{ count($grupo['terminales']) === 1 ? 'terminal' : 'terminales' }}</h2>
                @unless ($loop->first)
                    <p class="muted small">Empresa: {{ ($filtros['empresa'] ?? '') ?: 'Todas las empresas' }} · Período: {{ $resultado['desde']->format('Y-m') }} a {{ $resultado['hasta']->format('Y-m') }}</p>
                @endunless
                <p class="muted small">Importes en RD$.</p>
                <table class="terminales">
                    <thead><tr><th class="agencia">Agencia</th><th class="right importe">Ventas</th><th class="right importe">Premios sacados</th><th class="right importe">Utilidad</th><th class="right importe">Gastos</th><th class="right importe">Balance general</th><th class="clasificacion">Clasificación</th><th class="observaciones">Observación</th></tr></thead>
                    <tbody>
                        @foreach ($grupo['terminales'] as $fila)
                            <tr>
                                <td class="agencia">{{ $fila['nombre'] }}<span class="terminal-referencia">Terminal {{ $fila['terminal'] }}</span></td>
                                @foreach (\App\Services\RentabilidadAgenciaV7Service::IMPORTES as $campo)
                                    <td class="right importe {{ $campo === 'balance_general' ? ($fila[$campo] > 0 ? 'positivo' : ($fila[$campo] < 0 ? 'negativo' : '')) : '' }}">{{ number_format($fila[$campo], 2) }}</td>
                                @endforeach
                                <td class="clasificacion"><span class="badge {{ $fila['clasificacion']['color'] }}">{{ $fila['clasificacion']['nombre'] }}</span>
                                    @if ($fila['clasificacion']['en_deterioro'])
                                        <br>En deterioro
                                    @endif
                                </td>
                                <td class="observaciones">
                                    @foreach ($fila['observaciones'] as $observacion)
                                        <p class="observacion obs-{{ $observacion['nivel'] }}">{{ $observacion['texto'] }}</p>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr><td class="agencia">Totales de la ruta</td>@foreach (\App\Services\RentabilidadAgenciaV7Service::IMPORTES as $campo)<td class="right importe">{{ number_format($grupo['totales'][$campo], 2) }}</td>@endforeach<td colspan="2"></td></tr></tfoot>
                </table>
            </div>
        @empty
            <p>No se encontraron terminales para imprimir.</p>
        @endforelse
    @endif
    @if ($esImpresion ?? false)
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
