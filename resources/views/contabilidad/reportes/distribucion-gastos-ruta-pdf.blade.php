<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Distribución de gastos de ruta</title>
    <style>
        @page { margin: 28px 30px; }
        body { color: #263238; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        .header { border-bottom: 3px solid #405189; margin-bottom: 15px; padding-bottom: 10px; }
        .header-meta { color: #64748b; float: right; line-height: 1.6; text-align: right; }
        .clearfix::after { clear: both; content: ''; display: table; }
        h1 { color: #405189; font-size: 19px; margin: 0 0 4px; }
        h2 { color: #405189; font-size: 12px; margin: 16px 0 7px; }
        .subtitle { color: #64748b; font-size: 10px; }
        .cards { border-collapse: separate; border-spacing: 7px; margin: 0 -7px 10px; width: calc(100% + 14px); }
        .card { background: #f3f6f9; border: 1px solid #dfe3e8; padding: 9px 10px; width: 20%; }
        .card-label { color: #64748b; font-size: 8px; font-weight: bold; margin-bottom: 4px; text-transform: uppercase; }
        .card-value { font-size: 14px; font-weight: bold; }
        .success { color: #078c7d; }
        .danger { color: #d94b32; }
        table.report { border-collapse: collapse; page-break-inside: auto; width: 100%; }
        table.report thead { display: table-header-group; }
        table.report tr { page-break-inside: avoid; }
        table.report th { background: #405189; color: #fff; font-size: 8px; padding: 6px 5px; text-align: left; text-transform: uppercase; }
        table.report td { border: 1px solid #dfe3e8; padding: 5px; vertical-align: top; }
        table.report tbody tr:nth-child(even) { background: #f8fafc; }
        .right { text-align: right; }
        .center { text-align: center; }
        .badge { border-radius: 8px; display: inline-block; font-size: 7px; font-weight: bold; padding: 2px 6px; text-transform: uppercase; }
        .badge-ok { background: #d9f3ed; color: #078c7d; }
        .badge-warning { background: #fff1d6; color: #a66a00; }
        .warning-box { background: #fff8e6; border: 1px solid #f3cc78; margin-top: 12px; padding: 8px; }
        .footer { border-top: 1px solid #dfe3e8; color: #64748b; margin-top: 15px; padding-top: 6px; text-align: center; }
    </style>
</head>
<body>
    <div class="header clearfix">
        <div class="header-meta">
            Período: <strong>{{ \Carbon\Carbon::parse($meta['fecha_ini'])->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($meta['fecha_fin'])->format('d/m/Y') }}</strong><br>
            Empresa: <strong>{{ $ruta['empresa'] }}</strong><br>
            Generado: {{ now()->format('d/m/Y h:i A') }}
        </div>
        <h1>Distribución de gastos de ruta</h1>
        <div class="subtitle">Ruta: <strong>{{ $ruta['ruta'] }}</strong></div>
    </div>

    <table class="cards">
        <tr>
            <td class="card"><div class="card-label">Gasto de la ruta</div><div class="card-value">RD$ {{ number_format((float) $ruta['gasto_ruta'], 2) }}</div></td>
            <td class="card"><div class="card-label">Socios</div><div class="card-value">{{ number_format((int) $ruta['socios']) }}</div></td>
            <td class="card"><div class="card-label">Terminales / agencias</div><div class="card-value">{{ number_format((int) $ruta['agencias']) }}</div></td>
            <td class="card"><div class="card-label">Monto asignado</div><div class="card-value success">RD$ {{ number_format((float) $ruta['asignado_socios'], 2) }}</div></td>
            <td class="card"><div class="card-label">Monto pendiente</div><div class="card-value {{ (float) $ruta['pendiente'] > 0 ? 'danger' : 'success' }}">RD$ {{ number_format((float) $ruta['pendiente'], 2) }}</div></td>
        </tr>
    </table>

    <h2>Resumen por socio</h2>
    <table class="report">
        <thead><tr><th>Cuenta</th><th>Socio</th><th class="right">Agencias</th><th class="right">Participación</th><th class="right">Monto distribuido</th><th class="center">Estado</th></tr></thead>
        <tbody>
            @forelse ($socios as $socio)
                <tr>
                    <td>{{ $socio['cuenta_codigo'] }} - {{ $socio['cuenta_descripcion'] }}</td>
                    <td>{{ $socio['socio'] }}</td>
                    <td class="right">{{ number_format((int) $socio['agencias']) }}</td>
                    <td class="right">{{ number_format((float) $socio['participacion'], 2) }}%</td>
                    <td class="right"><strong>RD$ {{ number_format((float) $socio['gasto_socio'], 2) }}</strong></td>
                    <td class="center"><span class="badge {{ $socio['estado'] === 'asignado' ? 'badge-ok' : 'badge-warning' }}">{{ $socio['estado'] }}</span></td>
                </tr>
            @empty
                <tr><td colspan="6" class="center">No existen montos distribuidos por socio.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Detalle de la distribución por terminal</h2>
    <table class="report">
        <thead><tr><th>Cuenta</th><th>Terminal</th><th>Agencia</th><th>Socio</th><th class="right">Participación</th><th class="right">Monto distribuido</th><th class="center">Estado</th></tr></thead>
        <tbody>
            @forelse ($detalle as $fila)
                <tr>
                    <td>{{ $fila['cuenta_codigo'] }} - {{ $fila['cuenta_descripcion'] }}</td>
                    <td>{{ $fila['terminal'] }}</td>
                    <td>{{ $fila['agencia'] }}</td>
                    <td>{{ $fila['socio'] }}</td>
                    <td class="right">{{ number_format((float) $fila['participacion'], 2) }}%</td>
                    <td class="right"><strong>RD$ {{ number_format((float) $fila['gasto_agencia'], 2) }}</strong></td>
                    <td class="center"><span class="badge {{ $fila['estado'] === 'asignado' ? 'badge-ok' : 'badge-warning' }}">{{ $fila['estado'] }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7" class="center">No existen terminales para distribuir.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if (count($incidencias) > 0)
        <div class="warning-box"><strong>Atención:</strong> este informe contiene {{ count($incidencias) }} incidencia(s) con un monto pendiente de RD$ {{ number_format((float) collect($incidencias)->sum('monto_pendiente'), 2) }}.</div>
        <h2>Incidencias</h2>
        <table class="report">
            <thead><tr><th>Terminal</th><th>Agencia</th><th>Tipo</th><th>Detalle</th><th class="right">Monto pendiente</th></tr></thead>
            <tbody>
                @foreach ($incidencias as $incidencia)
                    <tr>
                        <td>{{ $incidencia['terminal'] }}</td>
                        <td>{{ $incidencia['agencia'] }}</td>
                        <td>{{ $incidencia['tipo'] }}</td>
                        <td>{{ $incidencia['detalle'] }}</td>
                        <td class="right">RD$ {{ number_format((float) $incidencia['monto_pendiente'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">Operaciones · Distribución de Gastos de Ruta · Documento generado por el CRM</div>
</body>
</html>
