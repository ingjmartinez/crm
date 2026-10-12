<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta http-equiv="refresh" content="120">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BI para TV</title>
    <style>
        body { margin: 0; padding: 22px; background: #f3f6f8; color: #17334a; font: 14px Arial, sans-serif; }
        a { color: #087fa8; margin-right: 18px; } h1 { display: inline-block; margin: 0 24px 20px 0; color: #008db6; }
        section { margin-bottom: 20px; padding: 24px; border: 1px solid #9fe5f8; border-radius: 20px; background: #fff; }
        h2 { margin: 0 0 20px; color: #344b65; font-size: 15px; letter-spacing: 1px; }
        h2 small { float: right; color: #7095aa; font-size: 12px; font-weight: normal; letter-spacing: 0; }
        .tag { padding: 5px 10px; border: 1px solid #f5c75a; border-radius: 20px; color: #a04b00; background: #fff4d9; font-size: 11px; }
        .layout, .types { width: 100%; border-spacing: 16px 0; table-layout: fixed; }
        .layout td, .types td { vertical-align: top; } .layout .left { width: 23%; } .layout .chart { width: 53%; } .layout .routes { width: 24%; }
        .metric { margin-bottom: 12px; padding: 17px; border-radius: 14px; background: #f8fafb; }
        .caption, .metric small { display: block; color: #7095aa; font-size: 12px; }
        .metric strong { display: block; margin: 9px 0; color: #008db6; font-size: 28px; }
        .metric .small-value { font-size: 20px; } .divider { margin: 12px 0; border-top: 1px solid #dae8ed; }
        .ring { display: inline-block; margin-top: 12px; padding: 16px 13px; border: 8px solid #dfe7ee; border-top-color: #00a8cc; border-radius: 50%; font-size: 18px; font-weight: bold; }
        .legend { margin: 10px 0; font-size: 12px; color: #008db6; } .legend span { margin-left: 16px; color: #d78b0a; }
        svg { display: block; width: 100%; height: 235px; }
        .routes table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .routes td { padding: 9px 2px; border-bottom: 1px solid #d6e5ed; } .routes td:last-child { text-align: right; font-weight: bold; white-space: nowrap; }
        .type { padding: 22px; border: 1px solid #a7e3f5; border-radius: 15px; } .type.pink { border-color: #f6aed8; }
        .type h3 { margin: 0 0 18px; font-size: 13px; color: #5b7690; } .type h3 span { float: right; color: #008db6; }
        .type.pink h3 span, .type.pink strong { color: #dc0a83; } .type.pink .ring { border-top-color: #dc0a83; } .type strong { display: block; color: #008db6; font-size: 30px; }
        .type .details { display: block; margin-top: 7px; color: #5b7690; font-size: 13px; }
        .type .average { margin-top: 18px; padding-top: 18px; border-top: 1px solid #dce8ee; } .type .average b { display: block; margin: 7px 0; font-size: 18px; }
        .below { color: #d7263d; } .above { color: #159447; }
        @media (max-width: 1100px) {
            body { padding: 10px; }
            h1 { margin-bottom: 10px; font-size: 21px; }
            section { margin-bottom: 10px; padding: 12px; border-radius: 12px; }
            h2 { margin-bottom: 10px; }
            .layout, .types { border-spacing: 8px 0; }
            .layout .left { width: 26%; }
            .layout .chart { width: 74%; }
            .layout .routes { display: none; }
            .metric { padding: 10px; margin-bottom: 7px; }
            .metric strong { font-size: 21px; margin: 5px 0; }
            .metric .small-value { font-size: 16px; }
            .type { padding: 12px; }
            .type h3 { margin-bottom: 8px; }
            .type strong { font-size: 23px; }
            .type .average { margin-top: 10px; padding-top: 10px; }
        }
        @media (max-width: 600px) {
            .layout, .types, .layout tbody, .types tbody, .layout tr, .types tr, .layout td, .types td { display: block; width: auto !important; }
            .layout .routes { display: block; }
            .layout td, .types td { margin-bottom: 10px; }
            h2 small { float: none; display: block; margin-top: 8px; }
        }
    </style>
</head>
<body>
    <h1>BI para TV</h1><a href="{{ route('bi.index') }}">BI completo</a><a href="{{ route('dashboard.index') }}">Dashboard</a>
    <section>
        <h2>DÍA EN CURSO <span class="tag">PARCIAL</span><small>Lecturas horarias de LotoBet · {{ $fecha }} · {{ $lecturas->count() }} lecturas hoy</small></h2>
        <table class="layout"><tr>
            <td class="left">
                <div class="metric"><small>VENTAS ACUMULADAS</small><strong>{{ $ultima ? 'DOP '.number_format($total, 0) : '—' }}</strong><small>Todas las categorías disponibles</small></div>
                <div class="metric"><small>PROMEDIO POR HORA</small><strong>{{ $ultima ? 'DOP '.number_format($total / ($ultima->hora - 5), 0) : '—' }}</strong><small>{{ $ultima ? 'Desde las 06:00 · '.($ultima->hora - 5).' horas' : 'Esperando captura' }}</small>
                    @php $metaDiaria = (float) ($promedios['tradicional'] ?? 0) + (float) ($promedios['no_tradicional'] ?? 0); @endphp
                    @if($ultima && $metaDiaria > 0)
                        <div class="divider"></div><small>META DIARIA</small><strong class="small-value">DOP {{ number_format($metaDiaria, 0) }}</strong>
                        <span class="ring">{{ number_format(min(100, (($ultima->tradicional_acumulado + $ultima->no_tradicional_acumulado) / $metaDiaria) * 100), 0) }}%</span>
                    @endif
                </div>
            </td>
            <td class="chart">
                <span class="caption">VENTAS ACUMULADAS POR HORA</span>
                <div class="legend">● Ventas totales <span>● Mismo día semana anterior ({{ $fechaAnterior }})</span></div>
                @if($ultima)
                    <svg width="900" height="235" viewBox="0 0 900 235" preserveAspectRatio="none" role="img" aria-label="Ventas acumuladas por hora">
                        @for($i = 0; $i <= 4; $i++)
                            @php $y = 190 - $i * 40; @endphp
                            <line x1="70" y1="{{ $y }}" x2="865" y2="{{ $y }}" stroke="#d9eaf0" />
                            <text x="63" y="{{ $y + 4 }}" text-anchor="end" fill="#789bab" font-size="11">{{ $tope * $i / 4 >= 1000000 ? 'DOP '.number_format($tope * $i / 4000000, 1).'M' : 'DOP '.number_format($tope * $i / 4, 0) }}</text>
                        @endfor
                        @if($puntosAnteriores->count() > 1)<polyline points="{{ $puntosAnteriores->map(fn ($punto) => $punto['x'].','.$punto['y'])->implode(' ') }}" fill="none" stroke="#f59e0b" stroke-opacity="0.65" stroke-width="3" stroke-dasharray="7 5" />@endif
                        @if($puntosHoy->count() > 1)<polyline points="{{ $puntosHoy->map(fn ($punto) => $punto['x'].','.$punto['y'])->implode(' ') }}" fill="none" stroke="#0092bf" stroke-width="3" />@endif
                        @foreach($puntosAnteriores as $punto)<circle cx="{{ $punto['x'] }}" cy="{{ $punto['y'] }}" r="3" fill="#f59e0b" fill-opacity="0.65" />@endforeach
                        @foreach($puntosHoy as $punto)
                            @php $color = $punto['porcentaje'] === null || abs($punto['porcentaje']) < 0.05 ? '#0092bf' : ($punto['porcentaje'] > 0 ? '#159447' : '#d7263d'); @endphp
                            <circle cx="{{ $punto['x'] }}" cy="{{ $punto['y'] }}" r="4" fill="{{ $color }}" stroke="white" stroke-width="1" />
                            @if($punto['porcentaje'] !== null && abs($punto['porcentaje']) >= 0.05)<text x="{{ $punto['x'] }}" y="{{ max(14, $punto['y'] - 10) }}" text-anchor="middle" fill="{{ $color }}" font-size="10" font-weight="bold">{{ ($punto['porcentaje'] > 0 ? '+' : '').number_format($punto['porcentaje'], 1) }}%</text>@endif
                        @endforeach
                        @for($hora = 6; $hora <= 22; $hora++)<text x="{{ 70 + ($hora - 6) * 49 }}" y="220" text-anchor="middle" fill="#789bab" font-size="10">{{ str_pad($hora, 2, '0', STR_PAD_LEFT) }}:00</text>@endfor
                    </svg>
                    <small class="caption">Última lectura {{ str_pad($ultima->hora, 2, '0', STR_PAD_LEFT) }}:00</small>
                @else<p>Esperando la primera captura horaria.</p>@endif
            </td>
            <td class="routes"><span class="caption">RUTAS CON MÁS VENTA HOY</span><table>@forelse($rutas as $ruta)<tr><td>{{ $ruta['nombre'] ?? 'Ruta' }}</td><td>DOP {{ number_format((float) ($ruta['monto'] ?? 0), 0) }}</td></tr>@empty<tr><td>Aún no hay ventas por ruta.</td></tr>@endforelse</table></td>
        </tr></table>
    </section>
    <section>
        <h2>VENTAS POR TIPO · DÍA EN CURSO <span class="tag">PARCIAL</span><small>Lectura LotoBet · {{ $fecha }} · {{ $ultima ? str_pad($ultima->hora, 2, '0', STR_PAD_LEFT).':00' : 'sin lectura' }}</small></h2>
        @if($ultima)
            <table class="types"><tr>
                @foreach(['tradicional' => ['VENTAS TRADICIONALES', (float) $ultima->tradicional_acumulado], 'no_tradicional' => ['VENTAS NO TRADICIONALES', (float) $ultima->no_tradicional_acumulado]] as $tipo => $datos)
                    @php $monto = $datos[1]; $agencias = (int) ($ultima->terminales_categoria[$tipo] ?? 0); $promedio = isset($promedios[$tipo]) ? (float) $promedios[$tipo] : null; @endphp
                    <td><div class="type {{ $tipo === 'no_tradicional' ? 'pink' : '' }}">
                        <h3>{{ $datos[0] }} <span>{{ $total > 0 ? number_format($monto / $total * 100, 1) : '0.0' }}% del total</span></h3>
                        <strong>DOP {{ number_format($monto, 0) }}</strong><span class="details">{{ number_format($agencias) }} agencias · Promedio: DOP {{ number_format($agencias > 0 ? $monto / $agencias : 0, 0) }}</span>
                        <div class="average"><small>PROMEDIO DE VENTA DIARIO · ÚLTIMOS 3 MESES CALCULADOS</small><b>{{ $promedio !== null ? 'DOP '.number_format($promedio, 0) : 'Sin calcular' }}</b>
                            @if($promedio > 0)<span class="{{ $monto >= $promedio ? 'above' : 'below' }}">{{ $monto >= $promedio ? 'Meta diaria superada por DOP '.number_format($monto - $promedio, 0) : 'Faltan DOP '.number_format($promedio - $monto, 0).' para llegar al 100%' }}</span><br><span class="ring">{{ number_format(min(100, $monto / $promedio * 100), 0) }}%</span>@endif
                        </div>
                    </div></td>
                @endforeach
            </tr></table>
        @else<p>Esperando la primera captura horaria.</p>@endif
    </section>
</body>
</html>
