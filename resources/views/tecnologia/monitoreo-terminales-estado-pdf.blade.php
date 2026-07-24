<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Monitoreo de terminales - {{ $estado }}</title>
    <style>
        @page { margin: 24px; }
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1 { color: #405189; font-size: 20px; margin: 0 0 4px; }
        p { margin: 0 0 14px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d8dee9; padding: 6px; text-align: left; }
        th { background: #405189; color: #fff; font-size: 9px; text-transform: uppercase; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <h1>{{ $estado === 'AVISO' ? 'Terminales con aviso' : 'Terminales que requieren llamada' }}</h1>
    <p>Generado: {{ $generadoEn->format('d/m/Y h:i:s A') }} | Registros: {{ number_format($registros->count()) }}</p>

    <table>
        <thead>
            <tr>
                <th>Terminal</th>
                <th>Agencia</th>
                <th>Coordinador</th>
                <th>Fecha</th>
                <th>Apertura</th>
                <th>Ponche</th>
                <th>Tardanza</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($registros as $registro)
                <tr>
                    <td>{{ $registro['terminal'] }}</td>
                    <td>{{ $registro['agencia'] }}</td>
                    <td>{{ $registro['coordinador'] }}</td>
                    <td>{{ $registro['fecha'] }}</td>
                    <td class="text-center">{{ $registro['hora_apertura'] }}</td>
                    <td class="text-center">{{ ($registro['hora_ponche'] ?? null) ?: 'Sin ponche' }}</td>
                    <td class="text-center">{{ ($registro['minutos_tardanza'] ?? null) === null ? '-' : $registro['minutos_tardanza'].' min' }}</td>
                    <td>{{ $registro['estado'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
