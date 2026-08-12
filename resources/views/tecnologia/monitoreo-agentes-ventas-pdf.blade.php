<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Monitoreo de agentes de ventas</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #212529; }
        h1 { margin: 0 0 4px; font-size: 17px; color: #405189; }
        p { margin: 0 0 12px; color: #6c757d; }
        table { width: 100%; border-collapse: collapse; }
        th { padding: 6px 4px; color: #fff; background: #405189; text-align: left; }
        td { padding: 5px 4px; border: 1px solid #dee2e6; vertical-align: top; }
        tr:nth-child(even) { background: #f8f9fa; }
    </style>
</head>
<body>
    <h1>Monitoreo de agentes de ventas</h1>
    <p>Generado el {{ $generadoEn->format('d/m/Y h:i A') }} · {{ count($registros) }} registros</p>
    <table>
        <thead><tr><th>Fecha</th><th>Sistema</th><th>Cédula</th><th>Agente</th><th>Entrada</th><th>Salida</th><th>Marca</th><th>Última venta</th><th>Terminal</th><th>Agencia</th><th>Empresa</th><th>Coordinador</th><th>Estado</th><th>Observación</th></tr></thead>
        <tbody>
            @foreach ($registros as $registro)
                <tr>
                    <td>{{ $registro['fecha'] }}</td><td>{{ $registro['sistema'] }}</td><td>{{ $registro['cedula'] ?: 'Sin cédula' }}</td>
                    <td>{{ $registro['agente'] }}</td><td>{{ $registro['entrada'] ?: 'Sin entrada' }}</td><td>{{ $registro['salida'] ?: 'Sin salida' }}</td>
                    <td>{{ $registro['marca_validar'] ?? '-' }}</td><td>{{ $registro['ultima_venta'] ?? '-' }}</td>
                    <td>{{ $registro['terminal'] }}</td><td>{{ $registro['agencia'] }}</td><td>{{ $registro['empresa'] }}</td>
                    <td>{{ $registro['coordinador'] }}</td><td>{{ $registro['estado'] }}</td><td>{{ $registro['observacion'] ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
