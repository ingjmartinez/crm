<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 30px 34px; }
        body { font-family: DejaVu Sans, sans-serif; color: #243047; font-size: 9px; }
        h1 { font-size: 17px; margin: 0 0 5px; }
        h2 { font-size: 12px; margin: 18px 0 7px; color: #17365d; }
        p { margin: 5px 0; }
        .muted { color: #667085; }
        .small { font-size: 8px; }
        .cards, .detail { width: 100%; border-collapse: collapse; }
        .cards td { width: 50%; border: 1px solid #d7dfe8; padding: 9px; vertical-align: top; }
        .cards strong { display: block; font-size: 13px; margin-top: 3px; }
        .detail th, .detail td { border: 1px solid #d7dfe8; padding: 6px; }
        .detail th { background: #edf2f7; text-align: left; }
        .right { text-align: right; }
        .positive { color: #067647; }
        .negative { color: #b42318; }
        .section { page-break-inside: avoid; }
    </style>
</head>
<body>
    <h1>Informe ejecutivo de rentabilidad de agencia V5</h1>
    <p class="muted">{{ $informe['mes'] }} · {{ $informe['empresa'] ?: 'Todas las empresas' }} · Pago {{ $informe['porcentaje_pago'] }} % · Generado {{ now()->format('d/m/Y H:i') }}</p>
    <p class="muted small">Productos: {{ $informe['productos'] === [] ? 'Ninguno' : implode(', ', $informe['productos']) }}. Período de {{ $informe['dias_mes'] }} días.</p>

    <div class="section">
        <h2>Cumplimiento de metas</h2>
        <table class="cards">
            <tr>
                <td>Agencias activas <strong>{{ number_format($informe['terminales']) }}</strong></td>
                <td>Agencias que vendieron <strong>{{ number_format($informe['terminales_vendieron']) }}</strong></td>
            </tr>
            <tr>
                <td>Terminales que cumplieron la meta todos los días <strong>{{ number_format($informe['terminales_cumplieron_diario']) }}</strong></td>
                <td>Terminales que cumplieron la meta del mes <strong>{{ number_format($informe['terminales_cumplieron_mes']) }}</strong></td>
            </tr>
            <tr>
                <td>Días con meta cumplida entre todas las terminales <strong>{{ number_format($informe['jornadas_cumplidas']) }} / {{ number_format($informe['jornadas_totales']) }}</strong></td>
                <td>Cumplimiento de días con meta <strong>{{ $informe['cumplimiento_diario_porcentaje'] === null ? '—' : number_format($informe['cumplimiento_diario_porcentaje'], 1).' %' }}</strong></td>
            </tr>
            <tr>
                <td>Terminales que cumplieron la meta mensual <strong>{{ $informe['cumplimiento_mensual_porcentaje'] === null ? '—' : number_format($informe['cumplimiento_mensual_porcentaje'], 1).' %' }}</strong></td>
                <td>Ventas frente a meta mensual agregada <strong>{{ $informe['cumplimiento_venta_mensual_porcentaje'] === null ? 'Sin meta' : number_format($informe['cumplimiento_venta_mensual_porcentaje'], 1).' %' }}</strong></td>
            </tr>
        </table>
        <p class="muted small">Meta mensual por terminal = gasto fijo ÷ (1 − {{ $informe['porcentaje_pago'] }} %). Meta diaria = meta mensual ÷ {{ $informe['dias_mes'] }}. Un día sin venta no cumple, incluso si la meta es cero. El porcentaje diario divide los días cumplidos entre todas las terminales y todos los días del mes; el porcentaje mensual divide las terminales cumplidoras entre las terminales activas.</p>
    </div>

    <div class="section">
        <h2>Ventas y resultado</h2>
        <p class="muted small">Premios propios pagados: premios de la terminal pagados por ella misma, por otras terminales o por otras empresas, según la fecha de pago. Excluye premios de otras terminales.</p>
        <table class="detail">
            <tr><th>Concepto</th><th class="right">Importe</th></tr>
            <tr><td>Total vendido</td><td class="right">RD$ {{ number_format($informe['ventas'], 2) }}</td></tr>
            <tr><td>Premios sacados</td><td class="right">RD$ {{ number_format($informe['premios_sacados'], 2) }}</td></tr>
            <tr><td>Premios propios pagados</td><td class="right">RD$ {{ number_format($informe['premios_pagados'], 2) }}</td></tr>
            <tr><td>Beneficio bruto (ventas − premios propios pagados)</td><td class="right">RD$ {{ number_format($informe['beneficio_bruto'], 2) }}</td></tr>
            <tr><td>Total de gastos, incluido R20</td><td class="right">RD$ {{ number_format($informe['gastos'], 2) }}</td></tr>
            <tr><th>Balance después de costos y gastos</th><th class="right {{ $informe['balance'] < 0 ? 'negative' : 'positive' }}">RD$ {{ number_format($informe['balance'], 2) }}</th></tr>
        </table>
        <p class="muted small">Meta mensual agregada: RD$ {{ number_format($informe['meta_mensual_total'], 2) }} · Margen neto sobre ventas: {{ $informe['margen_neto_porcentaje'] === null ? '—' : number_format($informe['margen_neto_porcentaje'], 1).' %' }}.</p>
    </div>

    <div class="section">
        <h2>Balance por terminal</h2>
        <table class="cards">
            <tr>
                <td>Balance positivo: {{ number_format($informe['terminales_balance_positivo']) }} terminales <strong class="positive">RD$ {{ number_format($informe['balance_positivo'], 2) }}</strong></td>
                <td>Balance negativo: {{ number_format($informe['terminales_balance_negativo']) }} terminales <strong class="negative">RD$ {{ number_format($informe['balance_negativo'], 2) }}</strong></td>
            </tr>
        </table>
        @if ($informe['terminales_perdida'] !== [])
            <p class="muted">Cinco terminales con mayor pérdida del mes:</p>
            <table class="detail">
                <tr><th>Terminal</th><th>Agencia</th><th class="right">Balance</th></tr>
                @foreach ($informe['terminales_perdida'] as $terminal)
                    <tr><td>{{ $terminal['terminal'] }}</td><td>{{ $terminal['nombre'] }}</td><td class="right negative">RD$ {{ number_format($terminal['balance'], 2) }}</td></tr>
                @endforeach
            </table>
        @endif
    </div>
</body>
</html>
