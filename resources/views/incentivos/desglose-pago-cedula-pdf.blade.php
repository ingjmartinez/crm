<div>
    <!-- It is not the man who has too little, but the man who craves more, that is poor. - Seneca -->
</div>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Desglose de pago por cédula</title>
    <style>
        @page { margin: 30px; }
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.45; }
        h1, h2, p { margin: 0; }
        .header { border-bottom: 4px solid #002d72; margin-bottom: 18px; padding-bottom: 10px; }
        .title { color: #002d72; font-size: 21px; }
        .muted { color: #64748b; }
        .meta { margin-top: 5px; }
        .summary { border-collapse: separate; border-spacing: 7px; margin: 0 -7px 18px; width: 100%; }
        .summary td { border: 1px solid #cbd5e1; border-top: 3px solid #002d72; padding: 9px; width: 25%; }
        .label { color: #64748b; font-size: 8px; text-transform: uppercase; }
        .value { font-size: 15px; font-weight: bold; margin-top: 3px; }
        .success { color: #087f5b; }
        .section { margin-top: 16px; }
        .section-title { border-bottom: 1px solid #cbd5e1; color: #002d72; font-size: 14px; margin-bottom: 8px; padding-bottom: 4px; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data th { background: #002d72; color: #fff; padding: 7px; text-align: left; }
        table.data td { border: 1px solid #dbe2ea; padding: 7px; }
        .right { text-align: right; }
        .center { text-align: center; }
        .formula { background: #f8fafc; color: #475569; }
        .total td { background: #e8f0fb; font-weight: bold; }
        .note { background: #f8fafc; border-left: 4px solid #002d72; margin-top: 16px; padding: 10px; }
    </style>
</head>
<body>
    @php
        $meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
    @endphp

    <div class="header">
        <h1 class="title">Desglose de Pago por Cédula</h1>
        <p class="meta"><strong>{{ $meses[$periodo->mes] }} {{ $periodo->anio }}</strong> · Revisión {{ $periodo->revision }}</p>
        <p class="muted">Datos conservados del {{ $periodo->fecha_inicio->format('d/m/Y') }} al {{ $periodo->fecha_fin->format('d/m/Y') }}</p>
    </div>

    <div class="section">
        <h2 class="section-title">Identificación</h2>
        <p><strong>Cédula:</strong> {{ $detalle->cedula }}</p>
        <p><strong>IdEmpleado:</strong> {{ $detalle->empleadoid ?: 'N/D' }}</p>
        <p><strong>Nombre:</strong> {{ $detalle->nombre }}</p>
        <p><strong>Empresa:</strong> {{ $detalle->empresa }}</p>
    </div>

    <table class="summary">
        <tr>
            <td><div class="label">Ventas último mes</div><div class="value">RD$ {{ number_format($detalle->ventas_ultimo_mes) }}</div></td>
            <td><div class="label">Ventas mes actual</div><div class="value">RD$ {{ number_format($detalle->ventas_mes_actual) }}</div></td>
            <td><div class="label">Días con ventas</div><div class="value">{{ $detalle->dias_ventas }}</div></td>
            <td><div class="label">Incentivo generado</div><div class="value success">RD$ {{ number_format($detalle->incentivo_generado) }}</div></td>
        </tr>
    </table>

    <div class="section">
        <h2 class="section-title">Cálculo por tipo de pago</h2>
        <table class="data">
            <thead><tr><th>Tipo</th><th class="right">Ventas</th><th class="right">Participación</th><th class="center">Días</th><th class="right">Premio completo</th><th class="right">Incentivo</th></tr></thead>
            <tbody>
                @forelse ($tiposPago as $tipo)
                    <tr>
                        <td>Pago {{ $tipo['etiqueta'] }}</td>
                        <td class="right">RD$ {{ number_format($tipo['ventas']) }}</td>
                        <td class="right">{{ number_format($tipo['porcentaje'], 2) }}%</td>
                        <td class="center">{{ $tipo['dias'] }}</td>
                        <td class="right">RD$ {{ number_format($tipo['premio_escala']) }}</td>
                        <td class="right"><strong>RD$ {{ number_format($tipo['incentivo']) }}</strong></td>
                    </tr>
                    <tr class="formula"><td colspan="6">Fórmula: RD$ {{ number_format($tipo['premio_escala']) }} × {{ number_format($tipo['porcentaje'], 2) }}% = RD$ {{ number_format($tipo['incentivo']) }} después del redondeo guardado.</td></tr>
                @empty
                    <tr><td colspan="6">El período no conserva detalle por tipo de pago.</td></tr>
                @endforelse
                <tr class="total"><td>Total</td><td class="right">RD$ {{ number_format($resumen['ventas_desglosadas']) }}</td><td class="right">{{ number_format($resumen['porcentaje_total'], 2) }}%</td><td></td><td></td><td class="right">RD$ {{ number_format($resumen['incentivo_desglosado']) }}</td></tr>
            </tbody>
        </table>
    </div>

    <div class="note">
        Los tipos 60, 70 y 80 identifican escalas de pago; no representan un porcentaje directo sobre las ventas. El incentivo proporcional se calcula usando la participación de ventas de cada tipo sobre la base total y se redondea individualmente.
    </div>

    <p class="muted" style="margin-top:18px">Documento generado el {{ now()->format('d/m/Y H:i') }} para consulta interna.</p>
</body>
</html>
