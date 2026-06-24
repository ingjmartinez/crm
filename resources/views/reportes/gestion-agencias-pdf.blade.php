<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte gestion de agencias</title>
    <style>
        @page {
            margin: 22px 24px;
        }

        body {
            color: #1f2937;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            line-height: 1.35;
        }

        h1, h2, p {
            margin: 0;
        }

        .header {
            border-bottom: 4px solid #002d72;
            padding-bottom: 10px;
            margin-bottom: 14px;
            position: relative;
        }

        .header:after {
            content: "";
            display: block;
            height: 4px;
            width: 42%;
            background: #ce1126;
            position: absolute;
            bottom: -4px;
            left: 0;
        }

        .title {
            color: #002d72;
            font-size: 22px;
            font-weight: 700;
        }

        .subtitle {
            color: #6b7280;
            margin-top: 3px;
        }

        .meta {
            margin-top: 8px;
            color: #374151;
        }

        .grid {
            width: 100%;
            border-spacing: 8px;
            margin-left: -8px;
            margin-right: -8px;
        }

        .card {
            border: 1px solid #d8dee9;
            border-top: 3px solid #002d72;
            padding: 10px;
            background: #fff;
        }

        .card.red {
            border-top-color: #ce1126;
        }

        .card.green {
            border-top-color: #00b489;
        }

        .card.orange {
            border-top-color: #f7a600;
        }

        .card.dark {
            border-top-color: #111827;
        }

        .label {
            color: #6b7280;
            font-size: 9px;
            margin-bottom: 4px;
        }

        .value {
            color: #111827;
            font-size: 17px;
            font-weight: 700;
        }

        .success { color: #00b489; }
        .warning { color: #c27a00; }
        .danger { color: #ce1126; }
        .dark-text { color: #111827; }

        .section {
            margin-top: 16px;
        }

        .section-title {
            color: #002d72;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 7px;
            border-bottom: 1px solid #d8dee9;
            padding-bottom: 4px;
        }

        .trend-meta {
            color: #4b5563;
            margin-bottom: 10px;
        }

        .trend-box {
            border: 1px solid #d8dee9;
            background: #fafcff;
            padding: 10px 12px 8px;
        }

        .trend-chart {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .trend-chart td {
            padding: 0 2px;
            text-align: center;
            vertical-align: bottom;
        }

        .trend-plot {
            height: 180px;
            vertical-align: bottom;
        }

        .trend-bar-wrap {
            height: 160px;
            position: relative;
            vertical-align: bottom;
        }

        .trend-bar {
            width: 100%;
            background: #0ab39c;
            border-radius: 2px 2px 0 0;
        }

        .trend-value {
            color: #334155;
            font-size: 7px;
            padding-top: 4px;
        }

        .trend-label {
            color: #64748b;
            font-size: 7px;
            padding-top: 3px;
        }

        .muted {
            color: #6b7280;
        }

        .filters {
            margin-top: 6px;
            color: #374151;
        }
    </style>
</head>
<body>
    @php
        $filtrosActivos = array_filter($filtrosAgencia ?? [], fn ($value) => trim((string) $value) !== '');
    @endphp
    <div class="header">
        <h1 class="title">Reporte gestion de agencias</h1>
        <p class="subtitle">Tarjetas resumen y grafico de tendencia de ventas por hora.</p>
        <p class="meta">
            Generado: {{ $horaServidor->format('d-m-Y h:i:s A') }} |
            Hora servidor usada para la metrica |
            Aviso: {{ $umbrales['aviso'] }} min,
            En Alerta: {{ $umbrales['alerta'] }} min,
            Requiere llamada: {{ $umbrales['llamada'] }} min
        </p>
        <p class="filters">
            Filtros:
            Empresa: {{ $filtrosActivos['empresa'] ?? 'Todas' }} |
            Ruta: {{ $filtrosActivos['ruta'] ?? 'Todas' }} |
            Coordinador: {{ $filtrosActivos['coordinador'] ?? 'Todos' }}
        </p>
    </div>

    @if (empty($resumen))
        <p class="muted">No hay data cargada para generar el reporte.</p>
    @else
        <table class="grid">
            <tr>
                <td class="card" width="25%">
                    <p class="label">Promedio de venta por hora</p>
                    <p class="value">{{ number_format($resumen['venta_por_hora'] ?? 0, 0) }}</p>
                </td>
                <td class="card green" width="25%">
                    <p class="label">Agencias con ventas</p>
                    <p class="value success">{{ number_format($resumen['total_validas'] ?? 0) }}</p>
                </td>
                <td class="card red" width="25%">
                    <p class="label">Agencias sin ventas</p>
                    <p class="value danger">{{ number_format($resumen['total_eliminadas'] ?? 0) }}</p>
                </td>
                <td class="card" width="25%">
                    <p class="label">Total vendido</p>
                    <p class="value">{{ number_format($resumen['total_apostado'] ?? 0, 2) }}</p>
                </td>
            </tr>
        </table>

        <table class="grid">
            <tr>
                <td class="card green" width="25%">
                    <p class="label">Al dia</p>
                    <p class="value success">{{ number_format($estatusResumen['Al dia'] ?? 0) }}</p>
                </td>
                <td class="card orange" width="25%">
                    <p class="label">Aviso</p>
                    <p class="value warning">{{ number_format($estatusResumen['Aviso'] ?? 0) }}</p>
                </td>
                <td class="card red" width="25%">
                    <p class="label">En alerta</p>
                    <p class="value danger">{{ number_format($estatusResumen['En Alerta'] ?? 0) }}</p>
                </td>
                <td class="card dark" width="25%">
                    <p class="label">Requiere llamada</p>
                    <p class="value dark-text">{{ number_format($estatusResumen['Requiere llamada'] ?? 0) }}</p>
                </td>
            </tr>
        </table>

        @php
            $labels = $tendenciaVentasHora['labels'] ?? [];
            $series = $tendenciaVentasHora['series'] ?? [];
            $maxSeries = !empty($series) ? max($series) : 0;
            $labelStep = max((int) ceil(max(count($labels), 1) / 12), 1);
            $valueStep = max((int) ceil(max(count($series), 1) / 8), 1);
        @endphp

        <div class="section">
            <h2 class="section-title">Tendencia de ventas por hora</h2>
            <p class="trend-meta">
                Desde {{ $tendenciaVentasHora['primera_venta'] ?? 'N/D' }}
                hasta {{ $tendenciaVentasHora['ultima_venta'] ?? 'N/D' }}
                |
                Acumulado RD$ {{ number_format((float) ($tendenciaVentasHora['total'] ?? 0), 2) }}
            </p>

            @if (empty($series))
                <p class="muted">No hay ventas con hora valida para graficar.</p>
            @else
                <div class="trend-box">
                    <table class="trend-chart">
                        <tr class="trend-plot">
                            @foreach ($series as $index => $value)
                                @php
                                    $height = $maxSeries > 0 ? max((int) round((((float) $value) / $maxSeries) * 150), 4) : 4;
                                    $showValue = $index % $valueStep === 0 || $index === count($series) - 1;
                                @endphp
                                <td>
                                    <div class="trend-bar-wrap">
                                        <div class="trend-bar" style="height: {{ $height }}px;"></div>
                                    </div>
                                    <div class="trend-value">
                                        {{ $showValue ? number_format((float) $value, 0) : ' ' }}
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach ($labels as $index => $label)
                                @php
                                    $showLabel = $index % $labelStep === 0 || $index === count($labels) - 1;
                                @endphp
                                <td class="trend-label">
                                    {{ $showLabel ? $label : ' ' }}
                                </td>
                            @endforeach
                        </tr>
                    </table>
                </div>
            @endif
        </div>
    @endif
</body>
</html>
