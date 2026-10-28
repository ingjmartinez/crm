@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h4 class="mb-0">Rentabilidad de agencia V3</h4>
                    <a href="{{ route('gerencia.rentabilidad-agencia') }}" class="btn btn-outline-secondary">Ver reporte mensual</a>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <p class="text-muted mb-3">Utilidad = ingresos de las cinco cuentas contables seleccionadas − gastos de las cuentas activas clasificadas como Gasto − R20. El trimestre comprende los últimos tres meses cerrados. Los períodos sin asientos se muestran como datos incompletos y no se clasifican como rentables ni como pérdidas.</p>
                        <form method="GET" action="{{ route('gerencia.rentabilidad-agencia-v3') }}" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="mesCierreRentabilidadV3" class="form-label">Mes de cierre</label>
                                <input id="mesCierreRentabilidadV3" type="month" name="mes_cierre" class="form-control" value="{{ $mesCierre }}" max="{{ now()->subMonth()->format('Y-m') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label for="empresaRentabilidadV3" class="form-label">Empresa</label>
                                <select id="empresaRentabilidadV3" name="empresa" class="form-select">
                                    <option value="">Todas las empresas</option>
                                    @foreach ($empresas as $nombreEmpresa)
                                        <option value="{{ $nombreEmpresa }}" @selected($empresa === $nombreEmpresa)>{{ $nombreEmpresa }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-auto"><button type="submit" class="btn btn-primary">Consultar</button></div>
                        </form>
                        @if ($errors->any())
                            <div class="alert alert-danger mt-3 mb-0">{{ $errors->first() }}</div>
                        @endif
                    </div>
                </div>

                @if ($consultar && $resumen !== null)
                    <div class="card mb-4">
                        <div class="card-header"><h5 class="mb-0">Resultado hasta {{ $mesCierre }}</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                @foreach ([
                                    'Terminales' => number_format($resumen['terminales']),
                                    'Rentables en el año' => number_format($resumen['rentables_anio']),
                                    'Rentables en el trimestre' => number_format($resumen['rentables_trimestre']),
                                    'Terminales con datos incompletos' => number_format($resumen['incompletas']),
                                    'Resultado registrado del año' => 'RD$ '.number_format($resumen['utilidad_anio'], 2),
                                    'Resultado registrado del trimestre' => 'RD$ '.number_format($resumen['utilidad_trimestre'], 2),
                                ] as $etiqueta => $valor)
                                    <div class="col-md-4 col-xl"><div class="border rounded p-3 h-100"><div class="text-muted small">{{ $etiqueta }}</div><div class="fw-semibold">{{ $valor }}</div></div></div>
                                @endforeach
                            </div>
                            <p class="small text-muted mt-3 mb-0">Año: {{ $mesesAnio->first() }} a {{ $mesesAnio->last() }} · Últimos tres meses cerrados: {{ $mesesTrimestre->first() }} a {{ $mesesTrimestre->last() }}. Los resultados con cobertura incompleta son parciales. R20 se aplica una vez por cada mes con movimientos contables y terminal.</p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Evolución por terminal</h5></div>
                        <div class="card-body">
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label for="estadoRentabilidadV3" class="form-label">Clasificación</label>
                                    <select id="estadoRentabilidadV3" class="form-select">
                                        <option value="">Todas</option>
                                        @foreach (['Rentable sostenida', 'Rentable irregular', 'En recuperación', 'En deterioro', 'No rentable', 'Datos incompletos', 'Sin movimientos'] as $estado)
                                            <option value="{{ $estado }}">{{ $estado }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table id="tablaRentabilidadAgenciaV3" class="table table-bordered table-striped align-middle w-100" style="min-width: 1450px">
                                    <thead><tr><th>Terminal</th><th>Agencia</th><th>Empresa</th><th class="text-end">Ingresos registrados año</th><th class="text-end">Gastos registrados año</th><th class="text-end">Resultado año</th><th class="text-end">Margen registrado</th><th class="text-end">Resultado 3 meses</th><th class="text-center">Meses rentables</th><th class="text-center">Cobertura año / 3 meses</th><th>Clasificación</th><th>Acción</th></tr></thead>
                                    <tbody>
                                        @foreach ($filas as $fila)
                                            <tr>
                                                <td>{{ $fila['terminal'] }}</td>
                                                <td>{{ $fila['nombre'] }}</td>
                                                <td>{{ $fila['empresa'] }}</td>
                                                <td class="text-end" data-order="{{ $fila['anio']['ingresos'] }}">RD$ {{ number_format($fila['anio']['ingresos'], 2) }}</td>
                                                <td class="text-end" data-order="{{ $fila['anio']['gastos'] }}">RD$ {{ number_format($fila['anio']['gastos'], 2) }}</td>
                                                <td class="text-end {{ $fila['anio']['utilidad'] < 0 ? 'text-danger' : 'text-success' }}" data-order="{{ $fila['anio']['utilidad'] }}">RD$ {{ number_format($fila['anio']['utilidad'], 2) }}</td>
                                                <td class="text-end" data-order="{{ $fila['anio']['margen'] ?? -999999 }}">{{ $fila['anio']['margen'] === null ? '—' : number_format($fila['anio']['margen'], 1).' %' }}</td>
                                                <td class="text-end {{ $fila['trimestre']['utilidad'] < 0 ? 'text-danger' : 'text-success' }}" data-order="{{ $fila['trimestre']['utilidad'] }}">RD$ {{ number_format($fila['trimestre']['utilidad'], 2) }}</td>
                                                <td class="text-center" data-order="{{ $fila['anio']['meses_rentables'] }}">{{ $fila['anio']['meses_rentables'] }}/{{ $fila['anio']['meses'] }}</td>
                                                <td class="text-center">{{ $fila['anio']['meses_con_movimientos'] }}/{{ $fila['anio']['meses'] }} · {{ $fila['trimestre']['meses_con_movimientos'] }}/3</td>
                                                <td>{{ $fila['clasificacion'] }}</td>
                                                <td><button type="button" class="btn btn-sm btn-outline-primary ver-detalle-v3" data-terminal="{{ $fila['terminal'] }}" data-bs-toggle="modal" data-bs-target="#modalRentabilidadAgenciaV3">Ver meses</button></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="small text-muted mt-3 mb-0">La clasificación financiera requiere movimientos contables en todos los meses del año y del trimestre. Rentable sostenida: utilidad positiva en el año y en cada uno de los tres meses recientes. Recuperación y deterioro comparan el trimestre con el acumulado del año.</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($consultar && $resumen !== null)
        <div class="modal fade" id="modalRentabilidadAgenciaV3" tabindex="-1" aria-labelledby="tituloModalRentabilidadAgenciaV3" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title" id="tituloModalRentabilidadAgenciaV3">Detalle mensual</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                    <div class="modal-body table-responsive">
                        <table class="table table-bordered table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th>Mes</th><th class="text-end">Ingresos contables</th><th class="text-end">Total gastos</th><th class="text-end">Utilidad</th><th>Estado</th></tr></thead>
                            <tbody id="filasModalRentabilidadAgenciaV3"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('script')
    @if ($consultar && $resumen !== null)
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const filas = {{ Illuminate\Support\Js::from($filas) }};
                const tabla = document.getElementById('tablaRentabilidadAgenciaV3');
                if (window.jQuery && $.fn.DataTable) {
                    const dataTable = $(tabla).DataTable({
                        responsive: false,
                        pageLength: 25,
                        order: [[5, 'desc']],
                        language: { url: '{{ asset('assets/json/es-ES.json') }}' },
                    });
                    document.getElementById('estadoRentabilidadV3').addEventListener('change', function () {
                        dataTable.column(10).search(this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '', true, false).draw();
                    });
                }

                tabla.addEventListener('click', function (event) {
                    const boton = event.target.closest('.ver-detalle-v3');
                    if (!boton) return;
                    const fila = filas.find((item) => item.terminal === boton.dataset.terminal);
                    if (!fila) return;
                    document.getElementById('tituloModalRentabilidadAgenciaV3').textContent = `${fila.nombre} · Terminal ${fila.terminal}`;
                    const cuerpo = document.getElementById('filasModalRentabilidadAgenciaV3');
                    cuerpo.replaceChildren();
                    fila.mensual.forEach((mes) => {
                        const tr = document.createElement('tr');
                        [mes.mes, mes.ingresos, mes.gastos, mes.utilidad, mes.con_movimientos ? 'Con movimientos' : 'Sin datos'].forEach((valor, indice) => {
                            const td = document.createElement('td');
                            td.textContent = indice === 0 || indice === 4 ? valor : (mes.con_movimientos ? `RD$ ${Number(valor).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : '—');
                            if (indice > 0 && indice < 4) td.className = 'text-end' + (indice === 3 && valor < 0 ? ' text-danger' : '');
                            tr.appendChild(td);
                        });
                        cuerpo.appendChild(tr);
                    });
                });
            });
        </script>
    @endif
@endsection
