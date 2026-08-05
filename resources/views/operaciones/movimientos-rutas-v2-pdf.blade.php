<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe de movimientos por ruta</title>
    <style>
        @page { margin: 24px; }
        body { color: #263238; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1 { color: #405189; font-size: 18px; margin: 0 0 4px; }
        h2 { color: #405189; font-size: 12px; margin: 18px 0 6px; }
        .muted { color: #6c757d; }
        .header { border-bottom: 2px solid #405189; margin-bottom: 12px; padding-bottom: 8px; }
        .header-meta { float: right; text-align: right; }
        .clearfix::after { clear: both; content: ''; display: table; }
        .cards { margin-bottom: 12px; width: 100%; }
        .cards td { background: #f3f6f9; border: 1px solid #dfe3e8; padding: 8px; width: 16.66%; }
        .card-label { color: #6c757d; font-size: 8px; margin-bottom: 3px; }
        .card-value { font-size: 11px; font-weight: bold; }
        .success { color: #0ab39c; }
        .danger { color: #f06548; }
        .primary { color: #405189; }
        table.data { border-collapse: collapse; page-break-inside: auto; width: 100%; }
        table.data thead { display: table-header-group; }
        table.data tr { page-break-inside: avoid; }
        table.data th { background: #405189; color: #fff; font-weight: bold; padding: 5px; text-align: left; }
        table.data td { border: 1px solid #dfe3e8; padding: 4px 5px; vertical-align: top; }
        table.data tbody tr:nth-child(even) { background: #f8f9fa; }
        .right { text-align: right; }
        .center { text-align: center; }
        .empty { color: #6c757d; padding: 8px; text-align: center; }
        .footer { border-top: 1px solid #dfe3e8; color: #6c757d; margin-top: 16px; padding-top: 6px; }
    </style>
</head>
<body>
    <div class="header clearfix">
        <div class="header-meta">
            Fecha del reporte: {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}<br>
            Generado: {{ now()->format('d/m/Y h:i A') }}
        </div>
        <h1>Informe de movimientos por ruta</h1>
        <div><strong>{{ $resumenRuta['ruta'] }}</strong></div>
    </div>

    <table class="cards" cellspacing="6">
        <tr>
            <td><div class="card-label">Transacciones</div><div class="card-value">{{ number_format($resumenRuta['transacciones']) }}</div></td>
            <td><div class="card-label">Neto esperado</div><div class="card-value">RD$ {{ number_format($resumenRuta['neto_esperado'], 2) }}</div></td>
            <td><div class="card-label">Depositado en banco</div><div class="card-value success">RD$ {{ number_format($resumenRuta['depositado_banco'], 2) }}</div></td>
            <td><div class="card-label">Gastos de ruta</div><div class="card-value primary">RD$ {{ number_format($resumenRuta['gastos_ruta'], 2) }}</div></td>
            <td><div class="card-label">Pendiente</div><div class="card-value {{ $resumenRuta['pendiente'] > 0 ? 'danger' : 'success' }}">RD$ {{ number_format($resumenRuta['pendiente'], 2) }}</div></td>
            <td><div class="card-label">Cumplimiento</div><div class="card-value">{{ number_format($resumenRuta['cumplimiento'], 1) }}%</div></td>
        </tr>
    </table>

    <h2>Depósitos bancarios aplicados</h2>
    <table class="data">
        <thead><tr><th>Registrado</th><th>Banco</th><th>Referencia</th><th class="right">Monto</th><th>Usuario</th><th class="center">Comprobante</th><th>Observación</th></tr></thead>
        <tbody>
            @forelse ($depositos as $deposito)
                <tr>
                    <td>{{ $deposito->created_at?->format('d/m/Y h:i A') }}</td>
                    <td>{{ $deposito->banco }}</td>
                    <td>{{ $deposito->referencia ?: '-' }}</td>
                    <td class="right">RD$ {{ number_format((float) $deposito->monto, 2) }}</td>
                    <td>{{ $deposito->usuario?->name ?? 'Sistema' }}</td>
                    <td class="center">{{ $deposito->comprobante_path ? 'Sí' : 'No' }}</td>
                    <td>{{ $deposito->observacion ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No hay depósitos bancarios aplicados.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Gastos de ruta aplicados</h2>
    <table class="data">
        <thead><tr><th>Registrado</th><th>Concepto</th><th class="right">Monto</th><th>Usuario</th><th class="center">Comprobante</th><th>Observación</th></tr></thead>
        <tbody>
            @forelse ($gastos as $gasto)
                <tr>
                    <td>{{ $gasto->created_at?->format('d/m/Y h:i A') }}</td>
                    <td>{{ $gasto->concepto }}</td>
                    <td class="right">RD$ {{ number_format((float) $gasto->monto, 2) }}</td>
                    <td>{{ $gasto->usuario?->name ?? 'Sistema' }}</td>
                    <td class="center">{{ $gasto->comprobante_path ? 'Sí' : 'No' }}</td>
                    <td>{{ $gasto->observacion ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No hay gastos de ruta aplicados.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Transacciones del CSV</h2>
    <table class="data">
        <thead><tr><th>Transacción</th><th>Terminal</th><th>Agencia</th><th>Tipo</th><th class="right">Monto original</th></tr></thead>
        <tbody>
            @forelse ($transacciones as $transaccion)
                <tr>
                    <td>{{ $transaccion->id_trans }}</td>
                    <td>{{ $transaccion->terminal ?: '-' }}</td>
                    <td>{{ $transaccion->nombre_agencia ?: '-' }}</td>
                    <td>{{ $transaccion->tipo_etiqueta }}</td>
                    <td class="right">RD$ {{ number_format((float) $transaccion->monto_original, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No hay transacciones para esta ruta.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Movimientos por Ruta V2 · Documento generado por el CRM</div>
</body>
</html>
