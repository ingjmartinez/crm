<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 26px 40px; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #243047; }
        .report-header { padding-top: 12px; }
        h1 { font-size: 19px; margin: 0 0 6px; color: #17365d; }
        h2 { font-size: 13px; margin: 10px 0 6px; color: #17365d; }
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
        .cards td { width: 25%; padding: 9px; }
        .cards strong { display: block; font-size: 15px; margin-top: 5px; }
        .summary { font-size: 11px; }
        .summary td, .summary th { padding: 4px 6px; }
        .financial td { width: 33.33%; padding: 4px 6px; }
        .financial strong { display: block; font-size: 13px; margin-top: 3px; }
        .badge { display: inline-block; border-radius: 3px; padding: 1px 6px; }
        .success { color: #067647; background: #dcfae6; }
        .primary { color: #175cd3; background: #eff4ff; }
        .warning { color: #854a0e; background: #fef0c7; }
        .danger { color: #b42318; background: #fee4e2; }
        .secondary { color: #475467; background: #f2f4f7; }
    </style>
</head>
<body>
    @php($configuracionClasificacion = $resultado['configuracion_clasificacion'] ?? config('rentabilidad'))
    <div class="report-header">
    <h1>Informe ejecutivo de rentabilidad de agencia V7</h1>
    <p><strong>Año {{ $resultado['desde']->year }}</strong> · Período: {{ $resultado['desde']->format('Y-m') }} a {{ $resultado['hasta']->format('Y-m') }}</p>
    <p class="muted">Empresa: {{ ($filtros['empresa'] ?? '') ?: 'Todas las empresas' }} · Terminal: {{ ($filtros['alcance'] ?? 'todas') === 'terminal' ? $filtros['terminal'] : 'Todas' }}</p>
    <p class="muted">Ruta: {{ ($filtros['ruta'] ?? '') ?: 'Todas las rutas' }}</p>
    <p class="muted small">Productos: {{ implode(', ', $productos) ?: 'Ninguno' }}. Generado: {{ now()->format('d/m/Y H:i') }}.</p>
    </div>

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

    <p class="muted small">Los totales incluyen todas las terminales evaluadas del período. El detalle por terminal está disponible en la tabla del reporte y sus exportaciones.</p>
</body>
</html>
