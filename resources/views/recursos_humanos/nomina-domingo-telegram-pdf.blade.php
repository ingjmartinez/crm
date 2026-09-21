<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nómina Domingo - {{ $estado }}</title>
    <style>
        @page { margin: 28px; }
        body { color: #24324a; font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h1 { font-size: 20px; margin: 0 0 5px; }
        .meta { color: #667085; margin-bottom: 18px; }
        .estado { color: {{ $cumplieron ? '#198754' : '#dc3545' }}; font-weight: bold; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d8dee8; padding: 7px; }
        th { background: #eef2f7; text-align: left; }
        .numero { text-align: right; }
        .vacio { color: #667085; padding: 25px; text-align: center; }
    </style>
</head>
<body>
    <h1>Nómina Domingo — <span class="estado">{{ $estado }}</span></h1>
    <div class="meta">
        <strong>Coordinador:</strong> {{ $coordinador }} &nbsp;|&nbsp;
        <strong>Fecha:</strong> {{ $fecha->format('d/m/Y') }} &nbsp;|&nbsp;
        <strong>Total:</strong> {{ number_format($empleados->count()) }}
    </div>

    <table>
        <thead>
            <tr><th>Terminal</th><th>Cédula</th><th>Empleado</th><th>Empresa</th><th class="numero">Horas (h/min)</th><th>Estado</th></tr>
        </thead>
        <tbody>
            @forelse ($empleados as $empleado)
                <tr>
                    <td>{{ $empleado['terminal'] }}</td>
                    <td>{{ $empleado['cedula'] }}</td>
                    <td>{{ $empleado['empleado'] }}</td>
                    <td>{{ $empleado['empresa'] }}</td>
                    <td class="numero">{{ $empleado['horas_trabajadas_formato'] }}</td>
                    <td>{{ $empleado['estatus'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="vacio">No hay empleados en esta clasificación.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
