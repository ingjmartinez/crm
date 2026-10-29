<div id="detalleTerminalRentabilidadV5">
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">{{ $agencia->nombre_agencia ?: $agencia->agencia ?: $terminal }} · Terminal {{ $terminal }} · {{ $mes }}</h5></div>
        <div class="card-body">
            <div class="row g-3">
                @foreach ([
                    'Ventas' => $resumen['venta'],
                    'Premios sacados' => $resumen['premios_sacados'],
                    'Premios propios pagados' => $resumen['premios'],
                    'Ingreso real' => $resumen['ingreso_real'],
                    'Costos' => $resumen['costos'],
                    'Gastos' => $resumen['gastos'],
                    'Gastos fijos' => $resumen['gastos_fijos'],
                    'Balance' => $resumen['balance'],
                ] as $etiqueta => $importe)
                    <div class="col-md-4 col-xl-2">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ $etiqueta }}</div>
                            <div class="fw-semibold {{ $importe < 0 ? 'text-danger' : '' }}">RD$ {{ number_format($importe, 2) }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="text-muted small mt-3 mb-0">Los gastos incluyen RD$ {{ number_format($resumen['r20'], 2) }} de R20 por terminal.</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Meta de venta basada en gastos fijos</h5></div>
        <div class="card-body">
            <p class="text-muted small">Gastos fijos: RD$ {{ number_format($resumen['gastos_fijos'], 2) }} (incluye R20). Ventas por día = meta de venta ÷ {{ $dias->count() }} días.</p>
            <div class="row g-3">
                @foreach ($metasDiarias as $meta)
                    <div class="col-md-4">
                        <div class="border rounded p-3">
                            <div class="text-muted">Pago en premios: {{ $meta['premios_porcentaje'] }} %</div>
                            <div class="fw-semibold">RD$ {{ number_format($meta['meta_diaria'], 2) }} por día</div>
                            <div class="small">Meta mensual: RD$ {{ number_format($meta['meta_venta'], 2) }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Ingresos reales por día</h5></div>
        <div class="card-body table-responsive">
            <table class="table table-bordered table-striped align-middle mb-0">
                <thead><tr><th>Fecha</th><th class="text-end">Ventas</th><th class="text-end">Premios sacados</th><th class="text-end">Premios propios pagados</th><th class="text-end">Ingreso real</th></tr></thead>
                <tbody>
                    @foreach ($dias as $dia)
                        <tr>
                            <td>{{ $dia['fecha'] }}</td>
                            <td class="text-end">RD$ {{ number_format($dia['venta'], 2) }}</td>
                            <td class="text-end">RD$ {{ number_format($dia['premios_sacados'], 2) }}</td>
                            <td class="text-end">RD$ {{ number_format($dia['premios'], 2) }}</td>
                            <td class="text-end {{ $dia['ingreso_real'] < 0 ? 'text-danger' : '' }}">RD$ {{ number_format($dia['ingreso_real'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light fw-semibold"><tr><td>Total</td><td class="text-end">RD$ {{ number_format($resumen['venta'], 2) }}</td><td class="text-end">RD$ {{ number_format($resumen['premios_sacados'], 2) }}</td><td class="text-end">RD$ {{ number_format($resumen['premios'], 2) }}</td><td class="text-end">RD$ {{ number_format($resumen['ingreso_real'], 2) }}</td></tr></tfoot>
            </table>
        </div>
    </div>
</div>
