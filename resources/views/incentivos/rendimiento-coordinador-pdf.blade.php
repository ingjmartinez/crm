<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Rendimiento integral de coordinador</title>
    <style>
        @page { margin: 22px 24px; }
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.35; }
        h1, h2, p { margin: 0; }
        .header { border-bottom: 4px solid #002d72; margin-bottom: 12px; padding-bottom: 9px; }
        .title { color: #002d72; font-size: 20px; }
        .subtitle { color: #475569; margin-top: 3px; }
        .meta { color: #64748b; margin-top: 5px; }
        .grid { border-spacing: 6px; margin-left: -6px; width: 100%; }
        .card { border: 1px solid #d8dee9; border-top: 3px solid #002d72; padding: 8px; }
        .card.green { border-top-color: #0ab39c; }
        .card.orange { border-top-color: #f7a600; }
        .card.red { border-top-color: #ce1126; }
        .label { color: #64748b; font-size: 8px; text-transform: uppercase; }
        .value { color: #111827; font-size: 15px; font-weight: bold; margin-top: 3px; }
        .section { margin-top: 13px; page-break-inside: avoid; }
        .section-title { border-bottom: 1px solid #cbd5e1; color: #002d72; font-size: 12px; margin-bottom: 6px; padding-bottom: 3px; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data th { background: #002d72; color: white; font-size: 8px; padding: 5px; text-align: left; }
        table.data td { border-bottom: 1px solid #e2e8f0; padding: 4px 5px; }
        .right { text-align: right; }
        .center { text-align: center; }
        .success { color: #087f6d; }
        .danger { color: #b91c1c; }
        .warning { color: #b45309; }
        .trend { border-collapse: collapse; table-layout: fixed; width: 100%; }
        .trend td { padding: 0 1px; text-align: center; vertical-align: bottom; }
        .bar-box { height: 82px; vertical-align: bottom; }
        .bar { background: #0ab39c; margin: auto; width: 72%; }
        .bar.previous { background: #94a3b8; }
        .tiny { color: #64748b; font-size: 6px; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    @php
        $maxTrend = max(collect($tendencia)->max('venta_actual') ?? 0, collect($tendencia)->max('venta_anterior') ?? 0, 1);
        $trendPdf = count($tendencia) > 31
            ? collect($tendencia)->chunk((int) ceil(count($tendencia) / 31))->map(fn ($chunk) => [
                'etiqueta' => $chunk->first()['etiqueta'] . '-' . $chunk->last()['etiqueta'],
                'venta_actual' => $chunk->sum('venta_actual'),
                'venta_anterior' => $chunk->sum('venta_anterior'),
            ])->values()
            : collect($tendencia);
        $maxTrendPdf = max($trendPdf->max('venta_actual') ?? 0, $trendPdf->max('venta_anterior') ?? 0, 1);
    @endphp

    <div class="header">
        <h1 class="title">Rendimiento integral de coordinador</h1>
        <p class="subtitle"><strong>{{ $meta['coordinador'] }}</strong> | {{ $meta['periodo'] }} | {{ $meta['sistema'] }}</p>
        <p class="meta">Meta individual: RD${{ number_format($meta['meta_usuario'], 2) }} | Comparación: {{ $meta['periodo_anterior'] }} | Generado: {{ $meta['generado'] }}</p>
    </div>

    <table class="grid"><tr>
        <td class="card" width="20%"><p class="label">Venta total zona</p><p class="value">RD${{ number_format($resumen['venta_total'], 2) }}</p></td>
        <td class="card green" width="20%"><p class="label">Usuarios que cumplen</p><p class="value success">{{ $resumen['usuarios_cumplieron'] }} / {{ $resumen['usuarios_vendedores'] }}</p></td>
        <td class="card orange" width="20%"><p class="label">Cumplimiento usuarios</p><p class="value">{{ number_format($resumen['cumplimiento_usuarios_pct'], 2) }}%</p></td>
        <td class="card" width="20%"><p class="label">Agencias con ventas</p><p class="value">{{ $resumen['agencias_con_ventas'] }} / {{ $resumen['agencias_asignadas'] }}</p></td>
        <td class="card red" width="20%"><p class="label">Agencias sin ventas</p><p class="value danger">{{ $resumen['agencias_sin_ventas'] }}</p></td>
    </tr></table>

    <table class="grid"><tr>
        <td class="card" width="25%"><p class="label">Promedio por agencia</p><p class="value">RD${{ number_format($resumen['promedio_agencia'], 2) }}</p></td>
        <td class="card" width="25%"><p class="label">Promedio por usuario</p><p class="value">RD${{ number_format($resumen['promedio_usuario'], 2) }}</p></td>
        <td class="card green" width="25%"><p class="label">Incentivo estimado</p><p class="value success">RD${{ number_format($resumen['incentivo_total'], 2) }}</p></td>
        <td class="card {{ $comparacion['variacion'] >= 0 ? 'green' : 'red' }}" width="25%">
            <p class="label">Variación vs. periodo anterior</p>
            <p class="value {{ $comparacion['variacion'] >= 0 ? 'success' : 'danger' }}">
                {{ $comparacion['variacion_pct'] === null ? 'Sin base' : number_format($comparacion['variacion_pct'], 2) . '%' }}
            </p>
        </td>
    </tr></table>

    <div class="section">
        <h2 class="section-title">Tendencia del periodo</h2>
        <p class="meta" style="margin-bottom:5px">Verde: periodo actual. Gris: periodo anterior equivalente. Para periodos extensos se agrupan días.</p>
        <table class="trend"><tr>
            @foreach ($trendPdf as $punto)
                <td class="bar-box">
                    <table style="border-collapse:collapse;width:100%;height:82px"><tr>
                        <td style="vertical-align:bottom;width:50%"><div class="bar" style="height:{{ max(1, round(($punto['venta_actual'] / $maxTrendPdf) * 72)) }}px"></div></td>
                        <td style="vertical-align:bottom;width:50%"><div class="bar previous" style="height:{{ max(1, round(($punto['venta_anterior'] / $maxTrendPdf) * 72)) }}px"></div></td>
                    </tr></table>
                    <div class="tiny">{{ $punto['etiqueta'] }}</div>
                </td>
            @endforeach
        </tr></table>
    </div>

    <div class="page-break"></div>

    <div class="section">
        <h2 class="section-title">Plan de rescate</h2>
        <p class="meta" style="margin-bottom:5px">
            Agencias críticas: {{ $rescate['resumen']['agencias_criticas'] }} |
            Rescates rápidos de agencia: {{ $rescate['resumen']['agencias_rescate_rapido'] }} |
            Usuarios próximos a meta: {{ $rescate['resumen']['usuarios_rescate_rapido'] }} |
            Usuarios críticos: {{ $rescate['resumen']['usuarios_criticos'] }}
        </p>
        <table class="data">
            <thead><tr><th>Prioridad</th><th>Terminal</th><th>Agencia</th><th class="right">Venta</th><th>Usuario a trabajar</th><th class="right">Avance</th><th>Acción sugerida</th></tr></thead>
            <tbody>
                @forelse (collect($rescate['agencias'])->take(8) as $row)
                    <tr><td>{{ $row['prioridad'] }}</td><td>{{ $row['terminal'] }}</td><td>{{ $row['agencia'] }}</td><td class="right">{{ number_format($row['venta_total'], 2) }}</td><td>{{ $row['mejor_usuario'] }}</td><td class="right">{{ number_format($row['mejor_usuario_avance_pct'], 2) }}%</td><td>{{ $row['accion_sugerida'] }}</td></tr>
                @empty
                    <tr><td colspan="7">No se detectaron agencias para rescate en el periodo.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="meta" style="margin:7px 0 4px"><strong>Usuarios prioritarios</strong></p>
        <table class="data">
            <thead><tr><th>Prioridad</th><th>Usuario</th><th>Agencia</th><th class="right">Venta</th><th class="right">Avance</th><th class="right">Faltante</th><th>Acción sugerida</th></tr></thead>
            <tbody>
                @forelse (collect($rescate['usuarios'])->take(8) as $row)
                    <tr><td>{{ $row['prioridad'] }}</td><td>{{ $row['nombre'] }}</td><td>{{ $row['agencia_principal'] }}</td><td class="right">{{ number_format($row['venta_total'], 2) }}</td><td class="right">{{ number_format($row['avance_pct'], 2) }}%</td><td class="right">{{ number_format($row['faltante'], 2) }}</td><td>{{ $row['accion_sugerida'] }}</td></tr>
                @empty
                    <tr><td colspan="7">No se detectaron usuarios pendientes de meta.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Top 10 agencias por volumen</h2>
        <table class="data">
            <thead><tr><th>#</th><th>Terminal</th><th>Agencia</th><th class="right">Venta</th><th class="center">Usuarios</th><th class="center">Usuarios meta</th><th class="right">Promedio</th><th>Mejor usuario</th></tr></thead>
            <tbody>
                @forelse (collect($agencias)->take(10) as $row)
                    <tr><td>{{ $row['ranking'] }}</td><td>{{ $row['terminal'] }}</td><td>{{ $row['agencia'] }}</td><td class="right">{{ number_format($row['venta_total'], 2) }}</td><td class="center">{{ $row['usuarios'] }}</td><td class="center">{{ $row['usuarios_cumplieron'] }}</td><td class="right">{{ number_format($row['promedio_usuario'], 2) }}</td><td>{{ $row['mejor_usuario'] }}</td></tr>
                @empty
                    <tr><td colspan="8">No hay agencias asignadas para estos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="page-break"></div>
    <div class="section">
        <h2 class="section-title">Ranking de usuarios vendedores</h2>
        <table class="data">
            <thead><tr><th>#</th><th>Cédula</th><th>Usuario</th><th>Agencia principal</th><th class="right">Venta total</th><th class="right">Avance</th><th class="right">Faltante</th><th>Clasificación</th><th class="right">Incentivo</th></tr></thead>
            <tbody>
                @forelse (collect($usuarios)->take(25) as $row)
                    <tr><td>{{ $row['ranking'] }}</td><td>{{ $row['cedula'] }}</td><td>{{ $row['nombre'] }}</td><td>{{ $row['agencia_principal'] }}</td><td class="right">{{ number_format($row['venta_total'], 2) }}</td><td class="right">{{ number_format($row['avance_pct'], 2) }}%</td><td class="right">{{ number_format($row['faltante'], 2) }}</td><td>{{ $row['clasificacion'] }}</td><td class="right">{{ number_format($row['incentivo'], 2) }}</td></tr>
                @empty
                    <tr><td colspan="9">No se encontraron vendedores con ventas en el periodo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Agencias que requieren atención</h2>
        <table class="data">
            <thead><tr><th>Terminal</th><th>Agencia</th><th>Estado</th><th class="right">Venta</th><th class="center">Usuarios</th><th class="center">Usuarios meta</th><th>Mejor usuario</th></tr></thead>
            <tbody>
                @forelse (collect($agencias)->filter(fn ($row) => $row['venta_total'] <= 0 || !$row['tiene_usuario_meta'])->sortBy('venta_total')->take(20) as $row)
                    <tr><td>{{ $row['terminal'] }}</td><td>{{ $row['agencia'] }}</td><td>{{ $row['activa'] ? 'Activa' : 'Inactiva' }}</td><td class="right">{{ number_format($row['venta_total'], 2) }}</td><td class="center">{{ $row['usuarios'] }}</td><td class="center">{{ $row['usuarios_cumplieron'] }}</td><td>{{ $row['mejor_usuario'] }}</td></tr>
                @empty
                    <tr><td colspan="7">Todas las agencias con ventas tienen al menos un usuario en meta.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="meta" style="margin-top:12px">La meta se evalúa sobre la venta total del usuario dentro de las agencias asignadas al coordinador. La venta de agencia es la suma de sus usuarios y se presenta como indicador comercial independiente.</p>
</body>
</html>
