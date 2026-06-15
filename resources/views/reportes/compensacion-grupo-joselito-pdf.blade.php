<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte Compensacion {{ $empresa ?? 'Grupo Joselito' }}</title>
    <style>
        @page {
            margin: 18px 22px;
        }

        body {
            color: #172033;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            margin: 0;
        }

        .header {
            border-bottom: 3px solid #2563eb;
            margin-bottom: 14px;
            padding-bottom: 10px;
        }

        .title {
            color: #0f172a;
            font-size: 22px;
            font-weight: 800;
            margin: 0 0 4px;
            text-transform: uppercase;
        }

        .subtitle {
            color: #475569;
            font-size: 11px;
            margin: 0;
        }

        .badge {
            background: #eef6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            color: #1d4ed8;
            display: inline-block;
            font-weight: 700;
            margin-top: 8px;
            padding: 6px 10px;
        }

        .section {
            margin-bottom: 16px;
            page-break-inside: avoid;
        }

        .section-title {
            background: #f8fafc;
            border-left: 5px solid #2563eb;
            color: #111827;
            font-size: 13px;
            font-weight: 800;
            margin: 0 0 8px;
            padding: 8px 10px;
            text-transform: uppercase;
        }

        .summary-grid {
            width: 100%;
        }

        .summary-card {
            border: 1px solid #dbe3ef;
            border-radius: 10px;
            padding: 11px;
            width: 24%;
        }

        .summary-label {
            color: #64748b;
            font-size: 9px;
            font-weight: 700;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .summary-value {
            color: #0f172a;
            font-size: 16px;
            font-weight: 800;
        }

        table {
            border-collapse: collapse;
            margin-bottom: 12px;
            width: 100%;
        }

        th {
            background: #1f2937;
            color: #fff;
            font-size: 9px;
            padding: 6px;
            text-align: left;
        }

        td {
            border: 1px solid #e5e7eb;
            padding: 5px 6px;
            vertical-align: middle;
        }

        tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .text-end {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .total-row {
            background: #ecfdf5 !important;
            font-weight: 800;
        }

        .bar-wrap {
            width: 170px;
        }

        .bar-track {
            background: #e5e7eb;
            border-radius: 999px;
            height: 8px;
            margin: 2px 0;
            overflow: hidden;
            width: 100%;
        }

        .bar-pao,
        .bar-ppo {
            display: block;
            height: 8px;
        }

        .bar-pao {
            background: #2563eb;
        }

        .bar-ppo {
            background: #ea580c;
        }

        .legend {
            color: #475569;
            font-size: 9px;
            font-weight: 700;
            margin-bottom: 6px;
            text-align: right;
        }

        .dot {
            border-radius: 50%;
            display: inline-block;
            height: 8px;
            margin: 0 4px 0 12px;
            width: 8px;
        }

        .dot-pao {
            background: #2563eb;
        }

        .dot-ppo {
            background: #ea580c;
        }

        .page-break {
            page-break-before: always;
        }

        .footer-note {
            color: #64748b;
            font-size: 8px;
            margin-top: 8px;
            text-align: right;
        }
    </style>
</head>
<body>
    @php
        $fmt = fn ($value) => number_format((float) ($value ?? 0), 2, '.', ',');
        $paoTotal = (float) ($resumen['aotra_bet'] ?? 0) + (float) ($resumen['aotra_net'] ?? 0);
        $ppoTotal = (float) ($resumen['porotra_bet'] ?? 0) + (float) ($resumen['porotra_net'] ?? 0);
        $resultadoTotal = $paoTotal - $ppoTotal;
        $beneficioTotal = $resultadoTotal * 1.02;
        $maxDiario = max(1, ...array_map(fn ($row) => max((float) ($row['pao'] ?? 0), (float) ($row['ppo'] ?? 0)), $diario ?: [['pao' => 0, 'ppo' => 0]]));
        $maxConsorcio = max(1, ...array_map(fn ($row) => max((float) ($row['pao'] ?? 0), (float) ($row['ppo'] ?? 0)), $consorcios ?: [['pao' => 0, 'ppo' => 0]]));
        $maxRutas = max(1, ...array_map(fn ($row) => max((float) ($row['pao'] ?? 0), (float) ($row['ppo'] ?? 0)), $rutas ?: [['pao' => 0, 'ppo' => 0]]));
        $barWidth = function ($value, $max) {
            $value = (float) ($value ?? 0);
            return $value > 0 ? max(2, min(100, ($value / $max) * 100)) : 0;
        };
    @endphp

    <div class="header">
        <h1 class="title">Reporte de Compensacion</h1>
        <p class="subtitle">{{ $empresa ?? 'Grupo Joselito' }} | Rango {{ \Carbon\Carbon::parse($fechaInicio)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($fechaFin)->format('d/m/Y') }}</p>
        <div class="badge">Generado: {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}</div>
    </div>

    <div class="section">
        <h2 class="section-title">Resumen ejecutivo</h2>
        <table class="summary-grid">
            <tr>
                <td class="summary-card">
                    <div class="summary-label">Pagos a Consorcios / PAO</div>
                    <div class="summary-value">{{ $fmt($paoTotal) }}</div>
                </td>
                <td class="summary-card">
                    <div class="summary-label">Pagos de Consorcios / PPO</div>
                    <div class="summary-value">{{ $fmt($ppoTotal) }}</div>
                </td>
                <td class="summary-card">
                    <div class="summary-label">Resultado</div>
                    <div class="summary-value">{{ $fmt($resultadoTotal) }}</div>
                </td>
                <td class="summary-card">
                    <div class="summary-label">Resultado + 2%</div>
                    <div class="summary-value">{{ $fmt($beneficioTotal) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Resultados tradicional por consorcio</h2>
        <table>
            <thead>
                <tr>
                    <th>Consorcio</th>
                    <th class="text-end">PAO</th>
                    <th class="text-end">PPO</th>
                    <th class="text-end">Resultado</th>
                    <th class="text-end">Resultado + 2%</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tradicional as $row)
                    @php
                        $pao = (float) ($row['aotra_bet'] ?? 0) + (float) ($row['aotra_net'] ?? 0);
                        $ppo = (float) ($row['porotra_bet'] ?? 0) + (float) ($row['porotra_net'] ?? 0);
                        $resultado = $pao - $ppo;
                    @endphp
                    <tr class="{{ ($row['consorcios'] ?? '') === 'TOTAL' ? 'total-row' : '' }}">
                        <td>{{ $row['consorcios'] ?? '' }}</td>
                        <td class="text-end">{{ $fmt($pao) }}</td>
                        <td class="text-end">{{ $fmt($ppo) }}</td>
                        <td class="text-end">{{ $fmt($resultado) }}</td>
                        <td class="text-end">{{ $fmt($resultado * 1.02) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section page-break">
        <h2 class="section-title">Compensacion por dia</h2>
        <div class="legend"><span class="dot dot-pao"></span>Pagos a Consorcios <span class="dot dot-ppo"></span>Pagos de Consorcios</div>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th class="text-end">PAO</th>
                    <th class="text-end">PPO</th>
                    <th class="text-end">Resultado</th>
                    <th>Grafico</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($diario as $row)
                    @php
                        $resultado = (float) ($row['pao'] ?? 0) - (float) ($row['ppo'] ?? 0);
                    @endphp
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($row['fecha'])->format('d/m/Y') }}</td>
                        <td class="text-end">{{ $fmt($row['pao'] ?? 0) }}</td>
                        <td class="text-end">{{ $fmt($row['ppo'] ?? 0) }}</td>
                        <td class="text-end">{{ $fmt($resultado) }}</td>
                        <td>
                            <div class="bar-wrap">
                                <div class="bar-track"><span class="bar-pao" style="width: {{ $barWidth($row['pao'] ?? 0, $maxDiario) }}%;"></span></div>
                                <div class="bar-track"><span class="bar-ppo" style="width: {{ $barWidth($row['ppo'] ?? 0, $maxDiario) }}%;"></span></div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Compensacion por consorcio</h2>
        <div class="legend"><span class="dot dot-pao"></span>Pagos a Consorcios <span class="dot dot-ppo"></span>Pagos de Consorcios</div>
        <table>
            <thead>
                <tr>
                    <th>Consorcio</th>
                    <th class="text-end">PAO</th>
                    <th class="text-end">PPO</th>
                    <th class="text-end">Resultado</th>
                    <th>Grafico</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($consorcios as $row)
                    @php
                        $resultado = (float) ($row['pao'] ?? 0) - (float) ($row['ppo'] ?? 0);
                    @endphp
                    <tr>
                        <td>{{ $row['consorcios'] ?? '' }}</td>
                        <td class="text-end">{{ $fmt($row['pao'] ?? 0) }}</td>
                        <td class="text-end">{{ $fmt($row['ppo'] ?? 0) }}</td>
                        <td class="text-end">{{ $fmt($resultado) }}</td>
                        <td>
                            <div class="bar-wrap">
                                <div class="bar-track"><span class="bar-pao" style="width: {{ $barWidth($row['pao'] ?? 0, $maxConsorcio) }}%;"></span></div>
                                <div class="bar-track"><span class="bar-ppo" style="width: {{ $barWidth($row['ppo'] ?? 0, $maxConsorcio) }}%;"></span></div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Top 10 rutas por compensacion</h2>
        <div class="legend"><span class="dot dot-pao"></span>Pagos a Consorcios <span class="dot dot-ppo"></span>Pagos de Consorcios</div>
        <table>
            <thead>
                <tr>
                    <th>Ruta</th>
                    <th class="text-end">PAO</th>
                    <th class="text-end">PPO</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Resultado</th>
                    <th>Grafico</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rutas as $row)
                    @php
                        $pao = (float) ($row['pao'] ?? 0);
                        $ppo = (float) ($row['ppo'] ?? 0);
                    @endphp
                    <tr>
                        <td>{{ $row['ruta'] ?? '' }}</td>
                        <td class="text-end">{{ $fmt($pao) }}</td>
                        <td class="text-end">{{ $fmt($ppo) }}</td>
                        <td class="text-end">{{ $fmt($pao + $ppo) }}</td>
                        <td class="text-end">{{ $fmt($pao - $ppo) }}</td>
                        <td>
                            <div class="bar-wrap">
                                <div class="bar-track"><span class="bar-pao" style="width: {{ $barWidth($pao, $maxRutas) }}%;"></span></div>
                                <div class="bar-track"><span class="bar-ppo" style="width: {{ $barWidth($ppo, $maxRutas) }}%;"></span></div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="footer-note">
        Reporte Generado por Grupo Joselito
    </div>
</body>
</html>
