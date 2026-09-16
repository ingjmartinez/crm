<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Top 50 retiros de agencias sin cuadrar</title>
    <style>
        @page { margin: 24px; }
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1, p { margin: 0; }
        .header { border-bottom: 4px solid #ce1126; margin-bottom: 16px; padding-bottom: 10px; }
        .title { color: #002d72; font-size: 22px; }
        .meta { color: #4b5563; margin-top: 5px; }
        .summary { border-collapse: separate; border-spacing: 8px; margin: 0 -8px 14px; width: 100%; }
        .summary td { background: #f8fafc; border: 1px solid #d8dee9; border-top: 3px solid #002d72; padding: 9px; }
        .summary .label { color: #64748b; font-size: 8px; text-transform: uppercase; }
        .summary .value { font-size: 15px; font-weight: bold; margin-top: 3px; }
        .report-table { border-collapse: collapse; width: 100%; }
        .report-table th, .report-table td { border: 1px solid #d8dee9; padding: 5px; vertical-align: middle; }
        .report-table th { background: #002d72; color: #fff; font-size: 8px; text-transform: uppercase; }
        .report-table tbody tr:nth-child(even) { background: #f8fafc; }
        .center { text-align: center; }
        .number { text-align: right; white-space: nowrap; }
        .rank { color: #ce1126; font-size: 14px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">Top 50 agencias con mayor monto de retiros sin cuadrar</h1>
        <p class="meta">Generado: {{ \Carbon\Carbon::parse($generado_en)->format('d-m-Y h:i:s A') }}</p>
        <p class="meta">Reporte de rutas: {{ $nombre_archivo }} | Consolidado: {{ $nombre_archivo_consolidado }}</p>
    </div>

    <table class="summary">
        <tr>
            <td width="33%"><div class="label">Agencias con retiros</div><div class="value">{{ number_format($resumen['total_agencias']) }}</div></td>
            <td width="33%"><div class="label">Rutas analizadas</div><div class="value">{{ number_format($resumen['total_rutas']) }}</div></td>
            <td width="34%"><div class="label">Total de retiros sin cuadrar</div><div class="value">RD$ {{ number_format($resumen['total_retiros'], 2) }}</div></td>
        </tr>
    </table>

    <table class="report-table">
        <thead>
            <tr>
                <th width="5%">Posicion</th>
                <th width="10%">Terminal</th>
                <th width="25%">Agencia</th>
                <th>Ruta</th>
                <th width="14%">Fechas</th>
                <th width="15%">Monto de retiros</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($top_agencias as $indice => $agencia)
                <tr>
                    <td class="center rank">{{ $indice + 1 }}</td>
                    <td>{{ $agencia['terminal'] }}</td>
                    <td>{{ $agencia['agencia'] ?: 'Sin nombre' }}</td>
                    <td>{{ $agencia['rutas'] ?: 'Sin ruta' }}</td>
                    <td>{{ $agencia['fechas'] ?: 'Sin fecha' }}</td>
                    <td class="number"><strong>RD$ {{ number_format($agencia['total_retiros'], 2) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
