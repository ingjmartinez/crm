<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Detalle gestion de agencias' }}</title>
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

        .meta,
        .filters {
            margin-top: 8px;
            color: #374151;
        }

        .section-title {
            color: #002d72;
            font-size: 14px;
            font-weight: 700;
            margin: 16px 0 8px;
        }

        .muted {
            color: #6b7280;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #d8dee9;
            padding: 6px 8px;
            vertical-align: top;
            text-align: left;
        }

        th {
            background: #f8fafc;
            color: #0f172a;
            font-size: 9px;
            text-transform: uppercase;
        }

        tbody tr:nth-child(even) {
            background: #fafcff;
        }
    </style>
</head>
<body>
    @php
        $filtrosActivos = array_filter($filtrosAgencia ?? [], fn ($value) => trim((string) $value) !== '');
        $modalFiltrosActivos = array_filter($modalFiltros ?? [], fn ($value) => trim((string) $value) !== '');
        $rowsCollection = collect($rows ?? []);
    @endphp

    <div class="header">
        <h1 class="title">{{ $titulo ?? 'Detalle gestion de agencias' }}</h1>
        <p class="subtitle">{{ $subtitulo ?? 'Listado exportado desde el modal.' }}</p>
        <p class="meta">
            Generado: {{ $horaServidor->format('d-m-Y h:i:s A') }} |
            Aviso: {{ $umbrales['aviso'] ?? 20 }} min,
            En Alerta: {{ $umbrales['alerta'] ?? 30 }} min,
            Requiere llamada: {{ $umbrales['llamada'] ?? 60 }} min
        </p>
        <p class="filters">
            Filtros globales:
            Empresa: {{ $filtrosActivos['empresa'] ?? 'Todas' }} |
            Ciudad: {{ $filtrosActivos['ciudad'] ?? 'Todas' }} |
            Ruta: {{ $filtrosActivos['ruta'] ?? 'Todas' }} |
            Coordinador: {{ $filtrosActivos['coordinador'] ?? 'Todos' }}
        </p>
        <p class="filters">
            Filtros del modal:
            Ruta: {{ $modalFiltrosActivos['ruta'] ?? 'Todas' }} |
            Coordinador: {{ $modalFiltrosActivos['coordinador'] ?? 'Todos' }} |
            Registros: {{ number_format($rowsCollection->count()) }}
        </p>
    </div>

    <h2 class="section-title">Detalle</h2>

    @if ($rowsCollection->isEmpty())
        <p class="muted">No hay registros para exportar con los filtros actuales.</p>
    @else
        <table>
            <thead>
                <tr>
                    @foreach (($columnas ?? []) as $columna)
                        <th>{{ $columna }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rowsCollection as $row)
                    <tr>
                        @if (($scope ?? '') === 'agencias_sin_ventas')
                            <td>{{ $row['nombre_agencia'] ?? $row['agencia_id'] ?? 'SIN AGENCIA' }}</td>
                            <td>{{ $row['terminal'] ?? 'SIN TERMINAL' }}</td>
                            <td>{{ $row['ruta'] ?? 'Sin ruta' }}</td>
                            <td>{{ $row['coordinador'] ?? 'Sin coordinador' }}</td>
                        @else
                            <td>{{ $row['agencia'] ?? 'SIN AGENCIA' }}</td>
                            <td>{{ $row['terminal'] ?? 'SIN TERMINAL' }}</td>
                            <td>{{ $row['ruta'] ?? 'Sin ruta' }}</td>
                            <td>{{ $row['cedula'] ?? '' }}</td>
                            <td>{{ $row['nombre'] ?? 'Actualizar en la maestra de empleado' }}</td>
                            <td>{{ $row['coordinador'] ?? 'Sin coordinador' }}</td>
                            <td>{{ $row['tipo'] ?? 'N/D' }}</td>
                            <td>{{ $row['fecha'] ?? 'N/D' }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
