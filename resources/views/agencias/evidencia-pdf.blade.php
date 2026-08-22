<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 24px; }
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.35; }
        h1, p { margin: 0; }
        .header { border-bottom: 4px solid #b91c1c; margin-bottom: 14px; padding-bottom: 10px; }
        .title { color: #991b1b; font-size: 21px; }
        .description { color: #4b5563; font-size: 10px; margin-top: 4px; }
        .meta { margin-top: 8px; }
        .summary { background: #fef2f2; border: 1px solid #fecaca; margin: 12px 0; padding: 8px; }
        table { border-collapse: collapse; width: 100%; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #d1d5db; padding: 5px 6px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; color: #111827; font-size: 8px; text-transform: uppercase; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        .number { text-align: right; }
        .center { text-align: center; }
        .empty { color: #6b7280; padding: 18px; text-align: center; }
        .footer-note { color: #6b7280; font-size: 8px; margin-top: 10px; }
    </style>
</head>
<body>
    @php($agencias = collect($resultado['agencias'] ?? []))

    <div class="header">
        <h1 class="title">Boletín de cambios: {{ $titulo }}</h1>
        <p class="description">{{ $descripcion }}</p>
        <p class="meta">
            Generado: {{ $generadoEn->format('d/m/Y h:i:s A') }} |
            Generado por: {{ $generadoPor }}
            @if (! empty($resultado['desde']) && ! empty($resultado['hasta']))
                | Rango evaluado: {{ $resultado['desde'] }} a {{ $resultado['hasta'] }}
            @endif
        </p>
    </div>

    <div class="summary">
        <strong>Total encontrado antes de ejecutar acciones:</strong> {{ number_format($agencias->count()) }}
    </div>

    @if ($agencias->isEmpty())
        <div class="empty">No se encontraron registros para este caso al momento de generar la evidencia.</div>
    @else
        <table>
            <thead>
                @if ($tipo === 'no_registradas_con_venta')
                    <tr>
                        <th>#</th>
                        <th>Terminal</th>
                        <th>Dias con venta</th>
                        <th>Ultima venta</th>
                        <th>Total venta</th>
                        <th>Estado</th>
                    </tr>
                @else
                    <tr>
                        <th>#</th>
                        <th>Agencia</th>
                        <th>Terminal</th>
                        <th>Nombre</th>
                        <th>Empresa</th>
                        <th>Ciudad</th>
                        <th>Ruta</th>
                        <th>Estado</th>
                    </tr>
                @endif
            </thead>
            <tbody>
                @foreach ($agencias as $index => $agencia)
                    @if ($tipo === 'no_registradas_con_venta')
                        <tr>
                            <td class="number">{{ $index + 1 }}</td>
                            <td>{{ $agencia['terminal'] ?? '-' }}</td>
                            <td class="number">{{ number_format($agencia['dias_con_venta'] ?? 0) }}</td>
                            <td>{{ $agencia['ultima_fecha'] ?? '-' }}</td>
                            <td class="number">RD$ {{ number_format($agencia['total_venta'] ?? 0, 2) }}</td>
                            <td class="center">No registrada</td>
                        </tr>
                    @else
                        <tr>
                            <td class="number">{{ $index + 1 }}</td>
                            <td>{{ $agencia['agencia'] ?? '-' }}</td>
                            <td>{{ $agencia['terminal'] ?? '-' }}</td>
                            <td>{{ $agencia['nombre_agencia'] ?? '-' }}</td>
                            <td>{{ $agencia['empresa'] ?? '-' }}</td>
                            <td>{{ $agencia['ciudad'] ?? '-' }}</td>
                            <td>{{ $agencia['ruta'] ?? '-' }}</td>
                            <td class="center">{{ $agencia['estatus_texto'] ?? ((int) ($agencia['estatus'] ?? 0) === 1 ? 'Activa' : 'Inactiva') }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="footer-note">Boletín conservado como constancia del estado consultado antes de realizar cambios en el módulo de agencias.</p>
</body>
</html>
