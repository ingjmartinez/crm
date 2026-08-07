<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resumen de movimientos por rutas</title>
    <style>
        @page { margin: 34px; }
        body { background: #fff; color: #263238; font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        .header { border-bottom: 3px solid #405189; margin-bottom: 24px; padding-bottom: 14px; }
        .header-meta { color: #6c757d; float: right; line-height: 1.6; text-align: right; }
        .clearfix::after { clear: both; content: ''; display: table; }
        h1 { color: #405189; font-size: 22px; margin: 0 0 5px; }
        .subtitle { color: #6c757d; font-size: 12px; }
        .cards { border-collapse: separate; border-spacing: 10px; margin: 0 -10px 18px; width: calc(100% + 20px); }
        .card { background: #f3f6f9; border: 1px solid #dfe3e8; border-radius: 7px; padding: 17px; width: 50%; }
        .card-label { color: #6c757d; font-size: 10px; font-weight: bold; letter-spacing: .5px; margin-bottom: 7px; text-transform: uppercase; }
        .card-value { color: #263238; font-size: 21px; font-weight: bold; }
        .primary { color: #405189; }
        .info { color: #299cdb; }
        .warning { color: #f7b84b; }
        .success { color: #0ab39c; }
        .danger { color: #f06548; }
        .performance { background: #f8f9fa; border: 1px solid #dfe3e8; border-radius: 7px; margin-top: 10px; padding: 18px; }
        .performance-title { color: #405189; font-size: 13px; font-weight: bold; margin-bottom: 12px; }
        .performance-values { margin-bottom: 9px; width: 100%; }
        .performance-values td { width: 50%; }
        .right { text-align: right; }
        .bar { background: #e1e5eb; border-radius: 8px; height: 18px; overflow: hidden; position: relative; }
        .bar-fill { background: #0ab39c; height: 18px; }
        .percentage { color: #263238; font-size: 18px; font-weight: bold; margin-top: 8px; text-align: center; }
        .footer { border-top: 1px solid #dfe3e8; color: #6c757d; margin-top: 28px; padding-top: 8px; text-align: center; }
    </style>
</head>
<body>
    @php
        $cumplimiento = (float) $resumen['cumplimiento_depositos'];
        $anchoCumplimiento = min(max($cumplimiento, 0), 100);
    @endphp

    <div class="header clearfix">
        <div class="header-meta">
            Fecha del reporte: <strong>{{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</strong><br>
            Empresa: <strong>{{ $empresaNombre }}</strong><br>
            Generado: {{ now()->format('d/m/Y h:i A') }}
        </div>
        <h1>Resumen de movimientos por rutas</h1>
        <div class="subtitle">Mini informe diario de conciliación</div>
    </div>

    <table class="cards">
        <tr>
            <td class="card">
                <div class="card-label">Rutas</div>
                <div class="card-value primary">{{ number_format((int) $resumen['rutas']) }}</div>
            </td>
            <td class="card">
                <div class="card-label">Transacciones</div>
                <div class="card-value info">{{ number_format((int) $resumen['transacciones']) }}</div>
            </td>
        </tr>
        <tr>
            <td class="card">
                <div class="card-label">Neto esperado</div>
                <div class="card-value warning">RD$ {{ number_format((float) $resumen['neto_esperado'], 2) }}</div>
            </td>
            <td class="card">
                <div class="card-label">Depositado en banco</div>
                <div class="card-value success">RD$ {{ number_format((float) $resumen['depositado_banco'], 2) }}</div>
            </td>
        </tr>
        <tr>
            <td class="card">
                <div class="card-label">Gastos de ruta</div>
                <div class="card-value primary">RD$ {{ number_format((float) $resumen['gastos_ruta'], 2) }}</div>
            </td>
            <td class="card">
                <div class="card-label">Pendiente</div>
                <div class="card-value {{ $resumen['pendiente'] > 0 ? 'danger' : 'success' }}">RD$ {{ number_format((float) $resumen['pendiente'], 2) }}</div>
            </td>
        </tr>
    </table>

    <div class="performance">
        <div class="performance-title">Cumplimiento del neto esperado</div>
        <table class="performance-values">
            <tr>
                <td>Neto esperado<br><strong>RD$ {{ number_format((float) $resumen['neto_esperado'], 2) }}</strong></td>
                <td class="right">Depositado en banco<br><strong class="success">RD$ {{ number_format((float) $resumen['depositado_banco'], 2) }}</strong></td>
            </tr>
        </table>
        <div class="bar"><div class="bar-fill" style="width: {{ $anchoCumplimiento }}%;"></div></div>
        <div class="percentage">{{ number_format($cumplimiento, 1) }}%</div>
    </div>

    <div class="footer">Movimientos por Ruta V2 · Documento generado por el CRM</div>
</body>
</html>
