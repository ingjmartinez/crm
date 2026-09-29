<div>
    <!-- Let all your things have their places; let each part of your business have its time. - Benjamin Franklin -->
</div>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe ejecutivo - {{ $informe['empresa'] }}</title>
    <style>
        @page { margin: 24px 30px 28px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #24324a; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        .header { background: #13294b; color: #fff; padding: 18px 22px; border-radius: 7px; }
        .header-table, .kpi-table, .content-table, .weekly-table { width: 100%; border-collapse: collapse; }
        .header-title { margin: 0 0 5px; font-size: 22px; font-weight: 700; }
        .header-subtitle { color: #cbd8ea; font-size: 10px; }
        .header-meta { text-align: right; line-height: 1.7; }
        .section-title { margin: 18px 0 8px; color: #13294b; font-size: 13px; }
        .kpi-table td { width: 20%; padding-right: 8px; vertical-align: top; }
        .kpi-table td:last-child { padding-right: 0; }
        .kpi { min-height: 67px; padding: 11px 13px; border: 1px solid #dbe3ee; border-top: 4px solid #09aa94; border-radius: 5px; background: #f9fbfd; }
        .kpi.blue { border-top-color: #3498db; }
        .kpi.green { border-top-color: #28b463; }
        .kpi.orange { border-top-color: #f39c12; }
        .kpi-label { color: #718096; font-size: 8px; text-transform: uppercase; letter-spacing: .5px; }
        .kpi-value { margin-top: 5px; color: #172b4d; font-size: 14px; font-weight: 700; }
        .kpi-note { margin-top: 3px; color: #718096; font-size: 8px; }
        .content-table > tbody > tr > td { width: 50%; vertical-align: top; }
        .content-table > tbody > tr > td:first-child { padding-right: 9px; }
        .content-table > tbody > tr > td:last-child { padding-left: 9px; }
        .panel { border: 1px solid #dbe3ee; border-radius: 6px; padding: 12px; }
        .panel-title { margin: 0 0 3px; color: #172b4d; font-size: 12px; font-weight: 700; }
        .panel-subtitle { margin-bottom: 10px; color: #718096; font-size: 8px; }
        .week { margin-bottom: 9px; }
        .week-label { margin-bottom: 4px; font-weight: 700; }
        .week-value { float: right; color: #44546a; font-weight: normal; }
        .sales-row { margin-top: 3px; }
        .sales-label { display: inline-block; width: 92px; color: #68778d; font-size: 8px; }
        .sales-amount { float: right; color: #44546a; font-size: 8px; }
        .sales-bar { min-width: 2px; height: 7px; border-radius: 2px; }
        .bar-traditional { background: #09aa94; }
        .bar-nontraditional { background: #3498db; }
        .bar-recharges { background: #f59e0b; }
        .growth { margin-left: 6px; font-size: 8px; }
        .legend { margin-top: 7px; color: #68778d; font-size: 8px; }
        .dot { display: inline-block; width: 7px; height: 7px; margin-right: 3px; border-radius: 50%; }
        .weekly-table th { padding: 6px 5px; color: #65758b; border-bottom: 1px solid #cfd9e6; font-size: 8px; text-align: right; text-transform: uppercase; }
        .weekly-table th:first-child, .weekly-table td:first-child { text-align: left; }
        .weekly-table td { padding: 8px 5px; border-bottom: 1px solid #edf1f5; text-align: right; }
        .compliance-values { width: 120px; margin-left: auto; border-collapse: collapse; font-size: 7px; }
        .compliance-values td { padding: 0 1px 2px; border: 0; }
        .compliance-values td:first-child { color: #16804b; text-align: left; }
        .compliance-values td:last-child { color: #c0392b; text-align: right; }
        .compliance-bar { width: 120px; height: 8px; margin-left: auto; background: #f4b7b7; border-radius: 3px; overflow: hidden; }
        .compliance-fill { height: 8px; background: #28b463; }
        .positive { color: #16804b; }
        .negative { color: #c0392b; }
        .insight { margin-top: 13px; padding: 9px 12px; color: #34445d; border-left: 4px solid #f39c12; background: #fff8e8; }
        .footer { position: fixed; right: 0; bottom: -17px; left: 0; color: #8a97a8; font-size: 8px; text-align: center; }
        .muted { color: #8793a5; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <div class="header-title">Informe ejecutivo dominical</div>
                    <div class="header-subtitle">Ventas y cumplimiento de horario · Últimas cuatro semanas</div>
                </td>
                <td class="header-meta">
                    <strong>{{ $informe['empresa'] }}</strong><br>
                    Período: {{ $informe['periodo'] }}<br>
                    Generado: {{ now()->format('d/m/Y h:i A') }}
                </td>
            </tr>
        </table>
    </div>

    <h2 class="section-title">Resumen del período</h2>
    <table class="kpi-table">
        <tr>
            <td><div class="kpi"><div class="kpi-label">Ventas totales</div><div class="kpi-value">RD$ {{ number_format($informe['ventas_total'], 2) }}</div><div class="kpi-note">Acumulado de 4 semanas</div></div></td>
            <td><div class="kpi blue"><div class="kpi-label">Ventas tradicionales</div><div class="kpi-value">RD$ {{ number_format($informe['ventas_tradicionales'], 2) }}</div><div class="kpi-note">Participación: {{ $informe['ventas_total'] > 0 ? number_format(($informe['ventas_tradicionales'] / $informe['ventas_total']) * 100, 1) : '0.0' }}%</div></div></td>
            <td><div class="kpi orange"><div class="kpi-label">Ventas no tradicionales</div><div class="kpi-value">RD$ {{ number_format($informe['ventas_no_tradicionales'], 2) }}</div><div class="kpi-note">Participación: {{ $informe['ventas_total'] > 0 ? number_format(($informe['ventas_no_tradicionales'] / $informe['ventas_total']) * 100, 1) : '0.0' }}%</div></div></td>
            <td><div class="kpi orange"><div class="kpi-label">Recargas</div><div class="kpi-value">RD$ {{ number_format($informe['ventas_recargas'], 2) }}</div><div class="kpi-note">Participación: {{ $informe['ventas_total'] > 0 ? number_format(($informe['ventas_recargas'] / $informe['ventas_total']) * 100, 1) : '0.0' }}%</div></div></td>
            <td><div class="kpi green"><div class="kpi-label">Cumplimiento de horario</div><div class="kpi-value">{{ $informe['porcentaje_cumplimiento'] !== null ? number_format($informe['porcentaje_cumplimiento'], 1).'%' : 'Sin datos' }}</div><div class="kpi-note">{{ number_format($informe['cumplen']) }} de {{ number_format($informe['evaluados']) }} evaluaciones</div></div></td>
        </tr>
    </table>

    <h2 class="section-title">Comportamiento semanal</h2>
    <table class="content-table">
        <tr>
            <td>
                <div class="panel">
                    <div class="panel-title">Ventas de las últimas cuatro semanas</div>
                    <div class="panel-subtitle">Tres barras por semana: Tradicional, No tradicional y Recargas. La variación compara el total con la semana anterior.</div>
                    @foreach ($informe['semanas'] as $semana)
                        @php
                            $tradicionalAncho = ($semana['tradicional'] / $informe['venta_maxima']) * 100;
                            $noTradicionalAncho = ($semana['no_tradicional'] / $informe['venta_maxima']) * 100;
                            $recargasAncho = ($semana['recargas'] / $informe['venta_maxima']) * 100;
                        @endphp
                        <div class="week">
                            <div class="week-label">
                                {{ $semana['fecha_formateada'] }}
                                @if ($semana['cargado'])
                                    <span class="week-value">
                                        Total RD$ {{ number_format($semana['ventas_total'], 2) }}
                                        @if ($semana['variacion_ventas'] !== null)
                                            <span class="growth {{ $semana['variacion_ventas'] >= 0 ? 'positive' : 'negative' }}">{{ $semana['variacion_ventas'] >= 0 ? '+' : '' }}{{ number_format($semana['variacion_ventas'], 1) }}%</span>
                                        @else
                                            <span class="growth muted">Base</span>
                                        @endif
                                    </span>
                                @else
                                    <span class="week-value">Sin datos cargados</span>
                                @endif
                            </div>
                            <div class="sales-row">
                                <span class="sales-label">Tradicional</span><span class="sales-amount">RD$ {{ number_format($semana['tradicional'], 2) }}</span>
                                <div class="sales-bar bar-traditional" style="width: {{ $tradicionalAncho }}%"></div>
                            </div>
                            <div class="sales-row">
                                <span class="sales-label">No tradicional</span><span class="sales-amount">RD$ {{ number_format($semana['no_tradicional'], 2) }}</span>
                                <div class="sales-bar bar-nontraditional" style="width: {{ $noTradicionalAncho }}%"></div>
                            </div>
                            <div class="sales-row">
                                <span class="sales-label">Recargas</span><span class="sales-amount">RD$ {{ number_format($semana['recargas'], 2) }}</span>
                                <div class="sales-bar bar-recharges" style="width: {{ $recargasAncho }}%"></div>
                            </div>
                        </div>
                    @endforeach
                    <div class="legend"><span class="dot" style="background:#09aa94"></span> Tradicional &nbsp;&nbsp; <span class="dot" style="background:#3498db"></span> No tradicional &nbsp;&nbsp; <span class="dot" style="background:#f59e0b"></span> Recargas</div>
                </div>
            </td>
            <td>
                <div class="panel">
                    <div class="panel-title">Cumplimiento de horario</div>
                    <div class="panel-subtitle">Cantidad de usuarios evaluados por domingo.</div>
                    <table class="weekly-table">
                        <thead><tr><th>Domingo</th><th>Cumplió</th><th>No cumplió</th><th>% cumplimiento / no cumplimiento</th></tr></thead>
                        <tbody>
                            @foreach ($informe['semanas'] as $semana)
                                <tr>
                                    <td>{{ $semana['fecha_formateada'] }}</td>
                                    <td class="positive">{{ number_format($semana['cumplen']) }}</td>
                                    <td class="negative">{{ number_format($semana['no_cumplen']) }}</td>
                                    <td>
                                        @if ($semana['porcentaje_cumplimiento'] !== null)
                                            @php($porcentajeNoCumplimiento = round(100 - $semana['porcentaje_cumplimiento'], 1))
                                            <table class="compliance-values"><tr><td>{{ number_format($semana['porcentaje_cumplimiento'], 1) }}% cumple</td><td>{{ number_format($porcentajeNoCumplimiento, 1) }}% no cumple</td></tr></table>
                                            <div class="compliance-bar"><div class="compliance-fill" style="width: {{ $semana['porcentaje_cumplimiento'] }}%"></div></div>
                                        @else
                                            <span class="muted">Sin datos</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="insight">
        <strong>Lectura ejecutiva:</strong>
        @if ($informe['variacion_ventas'] !== null)
            las ventas de la semana más reciente presentan una variación de
            <strong class="{{ $informe['variacion_ventas'] >= 0 ? 'positive' : 'negative' }}">{{ $informe['variacion_ventas'] >= 0 ? '+' : '' }}{{ number_format($informe['variacion_ventas'], 1) }}%</strong>
            frente a la primera semana con datos.
        @else
            no hay suficientes semanas cargadas para calcular la variación de ventas.
        @endif
        @if ($informe['variacion_cumplimiento'] !== null)
            El cumplimiento cambió
            <strong class="{{ $informe['variacion_cumplimiento'] >= 0 ? 'positive' : 'negative' }}">{{ $informe['variacion_cumplimiento'] >= 0 ? '+' : '' }}{{ number_format($informe['variacion_cumplimiento'], 1) }} puntos porcentuales</strong>.
        @endif
    </div>

    <div class="footer">Nómina Domingo · Documento de uso gerencial</div>
</body>
</html>
