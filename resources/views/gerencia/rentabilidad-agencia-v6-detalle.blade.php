<div id="detalleTerminalRentabilidadV6">
    <h5>{{ $fila['nombre'] }} · Terminal {{ $fila['terminal'] }}</h5>
    <p>{{ $resultado['desde']->format('d/m/Y') }} a {{ $resultado['hasta']->format('d/m/Y') }}. R20 se distribuye por día para conciliar con el cargo mensual.</p>
    <div class="table-responsive"><table class="table table-bordered table-sm"><thead><tr><th>Fecha</th><th>Ventas</th><th>Premios sacados</th><th>Premios propios</th><th>Premios no propios</th><th>Gastos</th><th>Balance propio</th><th>Balance neto</th></tr></thead><tbody>
    @foreach ($fila['periodos'] as $dia)
        <tr><td>{{ $dia['mes'] }}</td>@foreach (['ventas', 'premios_sacados', 'premios_propios', 'premios_no_propios', 'gastos', 'balance_propio', 'balance_neto'] as $campo)<td class="text-end {{ str_starts_with($campo, 'balance') ? ($dia[$campo] > 0 ? 'text-success' : ($dia[$campo] < 0 ? 'text-danger' : '')) : '' }}">RD$ {{ number_format($dia[$campo], 2) }}</td>@endforeach</tr>
    @endforeach
    </tbody><tfoot><tr><td>Total</td>@foreach (['ventas', 'premios_sacados', 'premios_propios', 'premios_no_propios', 'gastos', 'balance_propio', 'balance_neto'] as $campo)<td class="text-end fw-semibold">RD$ {{ number_format($fila[$campo], 2) }}</td>@endforeach</tr></tfoot></table></div>
</div>
