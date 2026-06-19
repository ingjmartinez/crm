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

        h1, h2, h3, p {
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
        .warning { color: #f7a600; }
        .danger { color: #ce1126; }
        .dark { color: #111827; }

        .section {
            margin-top: 14px;
        }

        .section-title {
            color: #002d72;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 7px;
            border-bottom: 1px solid #d8dee9;
            padding-bottom: 4px;
        }

        table.data {
            border-collapse: collapse;
            width: 100%;
        }

        table.data th {
            background: #eef2f7;
            color: #111827;
            font-size: 9px;
            text-align: left;
            border: 1px solid #d8dee9;
            padding: 5px;
        }

        table.data td {
            border: 1px solid #e5e7eb;
            padding: 5px;
            vertical-align: top;
        }

        .status-pill {
            font-weight: 700;
        }

        .pill-success { color: #00b489; }
        .pill-warning { color: #b77900; }
        .pill-danger { color: #ce1126; }
        .pill-dark { color: #111827; }

        .muted {
            color: #6b7280;
        }

        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">Reporte gestion de agencias</h1>
        <p class="subtitle">Resumen ejecutivo y detalle por estatus segun la ultima transaccion por terminal.</p>
        <p class="meta">
            Generado: {{ $horaServidor->format('d-m-Y h:i:s A') }} |
            Hora del servidor usada para la metrica |
            Aviso: {{ $umbrales['aviso'] }} min,
            En Alerta: {{ $umbrales['alerta'] }} min,
            Requiere llamada: {{ $umbrales['llamada'] }} min
        </p>
    </div>

    @if (empty($resumen))
        <p class="muted">No hay data cargada para generar el reporte.</p>
    @else
        <table class="grid">
            <tr>
                <td class="card" width="25%">
                    <p class="label">Premio de venta por Hora</p>
                    <p class="value">{{ number_format($resumen['venta_por_hora'] ?? 0, 0) }}</p>
                </td>
                <td class="card" width="25%">
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
                <td class="card" width="25%">
                    <p class="label">Al dia</p>
                    <p class="value success">{{ number_format($estatusResumen['Al dia'] ?? 0) }}</p>
                </td>
                <td class="card" width="25%">
                    <p class="label">Aviso</p>
                    <p class="value warning">{{ number_format($estatusResumen['Aviso'] ?? 0) }}</p>
                </td>
                <td class="card red" width="25%">
                    <p class="label">En Alerta</p>
                    <p class="value danger">{{ number_format($estatusResumen['En Alerta'] ?? 0) }}</p>
                </td>
                <td class="card" width="25%">
                    <p class="label">Requiere llamada</p>
                    <p class="value dark">{{ number_format($estatusResumen['Requiere llamada'] ?? 0) }}</p>
                </td>
            </tr>
        </table>

        <div class="section">
            <h2 class="section-title">Distribucion por estatus</h2>
            <table class="data">
                <thead>
                    <tr>
                        <th>Estatus</th>
                        <th>Terminales</th>
                        <th>Criterio aplicado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="status-pill pill-success">Al dia</span></td>
                        <td>{{ number_format($estatusResumen['Al dia'] ?? 0) }}</td>
                        <td>Ultima transaccion menor a {{ $umbrales['aviso'] }} minutos.</td>
                    </tr>
                    <tr>
                        <td><span class="status-pill pill-warning">Aviso</span></td>
                        <td>{{ number_format($estatusResumen['Aviso'] ?? 0) }}</td>
                        <td>Desde {{ $umbrales['aviso'] }} hasta antes de {{ $umbrales['alerta'] }} minutos.</td>
                    </tr>
                    <tr>
                        <td><span class="status-pill pill-danger">En Alerta</span></td>
                        <td>{{ number_format($estatusResumen['En Alerta'] ?? 0) }}</td>
                        <td>Desde {{ $umbrales['alerta'] }} hasta antes de {{ $umbrales['llamada'] }} minutos.</td>
                    </tr>
                    <tr>
                        <td><span class="status-pill pill-dark">Requiere llamada</span></td>
                        <td>{{ number_format($estatusResumen['Requiere llamada'] ?? 0) }}</td>
                        <td>{{ $umbrales['llamada'] }} minutos o mas sin venta.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @foreach (['Aviso', 'En Alerta', 'Requiere llamada', 'Al dia'] as $estatus)
            @php
                $filas = $estatusDetalle[$estatus] ?? [];
                $pillClass = $estatus === 'Al dia' ? 'pill-success' : ($estatus === 'Aviso' ? 'pill-warning' : ($estatus === 'En Alerta' ? 'pill-danger' : 'pill-dark'));
            @endphp
            <div class="section {{ $loop->index > 0 ? 'page-break' : '' }}">
                <h2 class="section-title">Detalle: {{ $estatus }} ({{ number_format(count($filas)) }})</h2>
                @if (empty($filas))
                    <p class="muted">No hay terminales en este estatus.</p>
                @else
                    <table class="data">
                        <thead>
                            <tr>
                                <th width="38%">Agencia</th>
                                <th width="16%">Terminal</th>
                                <th width="16%">Tipo</th>
                                <th width="20%">Ultima transaccion</th>
                                <th width="10%">Estatus</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($filas as $fila)
                                <tr>
                                    <td>{{ $fila['agencia'] ?: 'SIN AGENCIA' }}</td>
                                    <td>{{ $fila['terminal'] ?: 'SIN TERMINAL' }}</td>
                                    <td>{{ $fila['tipo'] ?: 'N/D' }}</td>
                                    <td>{{ $fila['fecha'] ?: 'N/D' }}</td>
                                    <td><span class="status-pill {{ $pillClass }}">{{ $estatus }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endforeach

        <div class="section page-break">
            <h2 class="section-title">Agencias sin ventas ({{ number_format($agenciasSinVentas->count()) }})</h2>
            @if ($agenciasSinVentas->isEmpty())
                <p class="muted">No hay agencias sin ventas.</p>
            @else
                <table class="data">
                    <thead>
                        <tr>
                            <th>Agencia</th>
                            <th>Terminal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($agenciasSinVentas as $agencia)
                            <tr>
                                <td>{{ $agencia['nombre_agencia'] ?? $agencia['agencia_id'] ?? 'SIN AGENCIA' }}</td>
                                <td>{{ $agencia['terminal'] ?? $agencia['agencia_id'] ?? 'SIN TERMINAL' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif
</body>
</html>
