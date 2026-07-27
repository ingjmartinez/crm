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

        .report-table {
            border-collapse: collapse;
            width: 100%;
        }

        .report-table th,
        .report-table td {
            border: 1px solid #d8dee9;
            padding: 5px;
            vertical-align: middle;
        }

        .report-table th {
            background: #002d72;
            color: #fff;
            font-size: 8px;
            text-transform: uppercase;
        }

        .report-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .report-table .number {
            text-align: right;
            white-space: nowrap;
        }

        .report-table .center {
            text-align: center;
        }

        .definitions td {
            padding: 7px;
        }

        .definitions strong {
            color: #002d72;
        }

        .status-chart {
            border: 1px solid #d8dee9;
            background: #fafcff;
            padding: 10px 12px;
        }

        .status-row {
            margin-bottom: 8px;
        }

        .status-label {
            display: inline-block;
            width: 125px;
            font-weight: 700;
        }

        .status-track {
            background: #e5e7eb;
            display: inline-block;
            height: 12px;
            vertical-align: middle;
            width: 72%;
        }

        .status-fill {
            background: #002d72;
            display: block;
            height: 12px;
        }

        .status-fill.green { background: #00b489; }
        .status-fill.orange { background: #f7a600; }
        .status-fill.red { background: #ce1126; }
        .status-fill.dark { background: #111827; }

        .status-total {
            display: inline-block;
            font-weight: 700;
            text-align: right;
            width: 42px;
        }

        .group-label {
            display: inline-block;
            font-size: 8px;
            font-weight: 700;
            overflow: hidden;
            vertical-align: middle;
            white-space: nowrap;
            width: 155px;
        }

        .group-track {
            background: #e5e7eb;
            display: inline-block;
            height: 12px;
            vertical-align: middle;
            width: 58%;
        }

        .group-fill {
            background: #0ab39c;
            display: block;
            height: 12px;
        }

        .group-total {
            display: inline-block;
            font-size: 8px;
            font-weight: 700;
            text-align: right;
            width: 110px;
        }

        .note {
            background: #eef4ff;
            border-left: 3px solid #002d72;
            color: #334155;
            margin: 7px 0;
            padding: 7px 9px;
        }

        .compliance-badge {
            border-radius: 9px;
            color: #fff;
            display: inline-block;
            font-size: 8px;
            font-weight: 700;
            min-width: 48px;
            padding: 3px 6px;
            text-align: center;
        }

        .compliance-badge.verde { background: #00a76f; }
        .compliance-badge.naranja { background: #e67e22; }
        .compliance-badge.amarillo { background: #d4a000; }
        .compliance-badge.rojo { background: #ce1126; }

        .page-break {
            page-break-before: always;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    @php
        $filtrosActivos = array_filter($filtrosAgencia ?? [], fn ($value) => trim((string) $value) !== '');
    @endphp
    <div class="header">
        <h1 class="title">Reporte gestion de agencias</h1>
        <p class="subtitle">
            Informe ejecutivo agrupado por {{ strtolower($agrupacionLabel ?? 'Ruta') }}:
            tarjetas, graficos, consolidado y muestra de agencias.
        </p>
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
            Ciudad: {{ $filtrosActivos['ciudad'] ?? 'Todas' }} |
            Ruta: {{ $filtrosActivos['ruta'] ?? 'Todas' }} |
            Coordinador: {{ $filtrosActivos['coordinador'] ?? 'Todos' }}
        </p>
        <p class="filters">
            Agrupacion principal: <strong>{{ $agrupacionLabel ?? 'Ruta' }}</strong>
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

        <div class="section">
            <h2 class="section-title">Detalle de los indicadores</h2>
            <table class="report-table definitions">
                <tbody>
                    <tr>
                        <td width="25%"><strong>Promedio de venta por hora</strong></td>
                        <td>Total vendido dividido entre las horas distintas con transacciones dentro de los filtros aplicados.</td>
                        <td width="25%"><strong>Agencias con ventas</strong></td>
                        <td>Cantidad unica de terminales que tienen al menos una venta en la data procesada.</td>
                    </tr>
                    <tr>
                        <td><strong>Agencias sin ventas</strong></td>
                        <td>Agencias activas del maestro que no tienen ventas dentro del conjunto filtrado.</td>
                        <td><strong>Total vendido</strong></td>
                        <td>Suma de todas las ventas tradicionales y no tradicionales que cumplen los filtros.</td>
                    </tr>
                    <tr>
                        <td><strong>Cumplimiento %</strong></td>
                        <td>Porcentaje de agencias con ventas respecto al total de agencias de cada grupo.</td>
                        <td><strong>Al dia / Aviso</strong></td>
                        <td>Terminales cuya ultima venta esta dentro del tiempo normal o supera el umbral de aviso.</td>
                    </tr>
                    <tr>
                        <td><strong>En alerta / Requiere llamada</strong></td>
                        <td>Terminales cuya ultima transaccion supera los umbrales configurados de alerta o llamada.</td>
                        <td><strong>Escala de cumplimiento</strong></td>
                        <td>Verde &gt; 90%; naranja entre 80% y 90%; amarillo entre 75% y 79.99%; rojo menor de 75%.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @php
            $statusTotal = max(array_sum($estatusResumen ?? []), 1);
            $statusChartRows = [
                ['label' => 'Al dia', 'value' => (int) ($estatusResumen['Al dia'] ?? 0), 'class' => 'green'],
                ['label' => 'Aviso', 'value' => (int) ($estatusResumen['Aviso'] ?? 0), 'class' => 'orange'],
                ['label' => 'En alerta', 'value' => (int) ($estatusResumen['En Alerta'] ?? 0), 'class' => 'red'],
                ['label' => 'Requiere llamada', 'value' => (int) ($estatusResumen['Requiere llamada'] ?? 0), 'class' => 'dark'],
            ];
        @endphp

        <div class="section">
            <h2 class="section-title">Distribucion de estatus con los filtros aplicados</h2>
            <div class="status-chart">
                @foreach ($statusChartRows as $statusRow)
                    <div class="status-row">
                        <span class="status-label">{{ $statusRow['label'] }}</span>
                        <span class="status-track">
                            <span class="status-fill {{ $statusRow['class'] }}"
                                style="width: {{ max((int) round(($statusRow['value'] / $statusTotal) * 100), $statusRow['value'] > 0 ? 2 : 0) }}%;"></span>
                        </span>
                        <span class="status-total">{{ number_format($statusRow['value']) }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="section">
            <h2 class="section-title">Consolidado por {{ strtolower($agrupacionLabel ?? 'ruta') }}</h2>
            <p class="note">
                Cada fila representa una {{ strtolower($agrupacionLabel ?? 'ruta') }} y cuenta cada terminal una sola vez.
                Las columnas corresponden a las tarjetas e indicadores del reporte.
            </p>
            @if (($resumenAgrupado ?? collect())->isEmpty())
                <p class="muted">No hay grupos disponibles con los filtros seleccionados.</p>
            @else
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>{{ $agrupacionLabel ?? 'Ruta' }}</th>
                            <th>Total agencias</th>
                            <th>Con ventas</th>
                            <th>Sin ventas</th>
                            <th>Cumplimiento %</th>
                            <th>Venta / hora</th>
                            <th>Total vendido</th>
                            <th>Al dia</th>
                            <th>Aviso</th>
                            <th>En alerta</th>
                            <th>Requiere llamada</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($resumenAgrupado as $grupo)
                            <tr>
                                <td><strong>{{ $grupo['grupo'] }}</strong></td>
                                <td class="center">{{ number_format($grupo['total_agencias']) }}</td>
                                <td class="center">{{ number_format($grupo['con_ventas']) }}</td>
                                <td class="center">{{ number_format($grupo['sin_ventas']) }}</td>
                                <td class="center">
                                    <span class="compliance-badge {{ $grupo['cumplimiento_color'] }}">
                                        {{ number_format($grupo['cumplimiento_porcentaje'], 2) }}%
                                    </span>
                                </td>
                                <td class="number">RD$ {{ number_format($grupo['venta_por_hora'], 2) }}</td>
                                <td class="number">RD$ {{ number_format($grupo['total_vendido'], 2) }}</td>
                                <td class="center">{{ number_format($grupo['al_dia']) }}</td>
                                <td class="center">{{ number_format($grupo['aviso']) }}</td>
                                <td class="center">{{ number_format($grupo['en_alerta']) }}</td>
                                <td class="center">{{ number_format($grupo['requiere_llamada']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        @php
            $gruposGrafico = ($resumenAgrupado ?? collect())
                ->sortByDesc('total_vendido')
                ->take(12)
                ->values();
            $maxVentaGrupo = max((float) ($gruposGrafico->max('total_vendido') ?? 0), 1);
        @endphp

        <div class="section">
            <h2 class="section-title">
                Comparativo de ventas por {{ strtolower($agrupacionLabel ?? 'ruta') }}
            </h2>
            <p class="trend-meta">
                Se muestran hasta 12 grupos, ordenados por venta total. La cantidad indicada corresponde
                a las agencias unicas de cada grupo.
            </p>
            @if ($gruposGrafico->isEmpty())
                <p class="muted">No hay grupos disponibles para graficar.</p>
            @else
                <div class="status-chart">
                    @foreach ($gruposGrafico as $grupo)
                        <div class="status-row">
                            <span class="group-label">{{ $grupo['grupo'] }}</span>
                            <span class="group-track">
                                <span class="group-fill"
                                    style="width: {{ max((int) round(($grupo['total_vendido'] / $maxVentaGrupo) * 100), $grupo['total_vendido'] > 0 ? 2 : 0) }}%;"></span>
                            </span>
                            <span class="group-total">
                                RD$ {{ number_format($grupo['total_vendido'], 0) }}
                                · {{ number_format($grupo['total_agencias']) }} ag.
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

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

        <div class="section page-break">
            <h2 class="section-title">Mini tabla de detalle de agencias</h2>
            <p class="note">
                Se muestran {{ number_format(($detalleAgencias ?? collect())->count()) }}
                de {{ number_format($detalleAgenciasTotal ?? 0) }} agencias.
                @if (($detalleAgenciasTotal ?? 0) > ($detalleAgenciasLimite ?? 200))
                    Para proteger el rendimiento del PDF, esta muestra se limita a las primeras
                    {{ number_format($detalleAgenciasLimite ?? 200) }} agencias ordenadas por
                    {{ strtolower($agrupacionLabel ?? 'ruta') }} y terminal.
                @endif
            </p>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>{{ $agrupacionLabel ?? 'Ruta' }}</th>
                        <th>Terminal</th>
                        <th>Agencia</th>
                        <th>Ciudad</th>
                        <th>Ruta</th>
                        <th>Coordinador</th>
                        <th>Estatus</th>
                        <th>Ultima venta</th>
                        <th>Total vendido</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($detalleAgencias ?? [] as $agencia)
                        <tr>
                            <td>{{ $agencia['grupo'] }}</td>
                            <td>{{ $agencia['terminal'] ?: 'N/D' }}</td>
                            <td>{{ $agencia['agencia'] ?: 'Sin nombre' }}</td>
                            <td>{{ $agencia['ciudad'] }}</td>
                            <td>{{ $agencia['ruta'] }}</td>
                            <td>{{ $agencia['coordinador'] }}</td>
                            <td>{{ $agencia['estatus'] }}</td>
                            <td>{{ $agencia['ultima_venta'] }}</td>
                            <td class="number">RD$ {{ number_format($agencia['total_vendido'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="center muted">No hay agencias para mostrar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>
