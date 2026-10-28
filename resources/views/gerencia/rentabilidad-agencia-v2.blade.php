@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">Rentabilidad de agencia V2</h4>
                    <a href="{{ route('gerencia.rentabilidad-agencia') }}" class="btn btn-outline-secondary">Ver reporte anterior</a>
                    <a href="{{ route('gerencia.rentabilidad-agencia-v3') }}" class="btn btn-outline-success">Ver análisis V3</a>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <p class="text-muted">Ingreso real = ventas registradas − premios pagados. Los costos y gastos provienen de las cuentas activas de Clasificación de cuentas.</p>
                        <form method="GET" action="{{ route('gerencia.rentabilidad-agencia-v2') }}" class="row g-3 align-items-end" id="formRentabilidadAgenciaV2">
                            <div class="col-md-2">
                                <label for="mesRentabilidadV2" class="form-label">Mes</label>
                                <input id="mesRentabilidadV2" type="month" name="mes" class="form-control" value="{{ $mes }}" required>
                            </div>
                            <div class="col-md-3">
                                <label for="empresaRentabilidadV2" class="form-label">Empresa</label>
                                <select id="empresaRentabilidadV2" name="empresa" class="form-select">
                                    <option value="">Todas las empresas</option>
                                    @foreach ($empresas as $nombreEmpresa)
                                        <option value="{{ $nombreEmpresa }}" @selected($empresa === $nombreEmpresa)>{{ $nombreEmpresa }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="alcanceRentabilidadV2" class="form-label">Consultar</label>
                                <select id="alcanceRentabilidadV2" name="alcance" class="form-select">
                                    <option value="todas" @selected($alcance === 'todas')>Todas las terminales</option>
                                    <option value="terminal" @selected($alcance === 'terminal')>Una terminal</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="terminalRentabilidadV2" class="form-label">Número de terminal</label>
                                <input id="terminalRentabilidadV2" type="text" name="terminal" class="form-control" value="{{ $terminal }}" placeholder="Ej.: 052232">
                            </div>
                            <div class="col-md-auto"><button type="submit" class="btn btn-primary">Consultar</button></div>
                            <div class="col-12">
                                <input type="hidden" name="productos_filtrados" value="1">
                                <span class="form-label d-block mb-2">Productos incluidos</span>
                                <div class="d-flex flex-wrap gap-3">
                                    @foreach (['Tradicional', 'No Tradicional', 'Recarga'] as $producto)
                                        <label class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" name="productos[]" value="{{ $producto }}" @checked($productosSeleccionados->contains($producto))>
                                            <span class="form-check-label">{{ $producto }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <small class="text-muted">Los premios de Tradicional y No Tradicional siguen la selección. Recarga no tiene premios asociados.</small>
                            </div>
                        </form>
                        @if ($errors->any())
                            <div class="alert alert-danger mt-3 mb-0">{{ $errors->first() }}</div>
                        @endif
                    </div>
                </div>

                @if ($consultar && $alcance === 'todas' && $resumen !== null)
                    <div class="card mb-4">
                        <div class="card-header"><h5 class="mb-0">Resumen de {{ $resumen['terminales'] }} terminales · {{ $empresa ?: 'Todas las empresas' }} · {{ $mes }}</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                @foreach (['Ventas' => $resumen['venta'], 'Premios pagados' => $resumen['premios'], 'Ingreso real' => $resumen['ingreso_real'], 'Costos' => $resumen['costos'], 'Gastos' => $resumen['gastos'], 'Balance' => $resumen['balance']] as $etiqueta => $importe)
                                    <div class="col-md-4 col-xl-2"><div class="border rounded p-3 h-100"><div class="text-muted small">{{ $etiqueta }}</div><div class="fw-semibold {{ $importe < 0 ? 'text-danger' : '' }}">RD$ {{ number_format($importe, 2) }}</div></div></div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Rentabilidad por terminal</h5></div>
                        <div class="card-body table-responsive">
                            <table id="tablaRentabilidadAgenciaV2" class="table table-bordered table-striped align-middle w-100">
                                <thead><tr><th>Terminal</th><th>Agencia</th><th class="text-end">Ventas</th><th class="text-end">Premios pagados</th><th class="text-end">Ingreso real</th><th class="text-end">Gastos</th><th class="text-end">Balance</th><th>Acción</th></tr></thead>
                                <tbody>
                                    @foreach ($agenciasResumen as $fila)
                                        <tr>
                                            <td>{{ $fila['terminal'] }}</td>
                                            <td>{{ $fila['nombre'] }}</td>
                                            <td class="text-end" data-order="{{ $fila['venta'] }}">RD$ {{ number_format($fila['venta'], 2) }}</td>
                                            <td class="text-end" data-order="{{ $fila['premios'] }}">RD$ {{ number_format($fila['premios'], 2) }}</td>
                                            <td class="text-end" data-order="{{ $fila['ingreso_real'] }}">RD$ {{ number_format($fila['ingreso_real'], 2) }}</td>
                                            <td class="text-end" data-order="{{ $fila['gastos'] }}">RD$ {{ number_format($fila['gastos'], 2) }}</td>
                                            <td class="text-end {{ $fila['balance'] < 0 ? 'text-danger' : '' }}" data-order="{{ $fila['balance'] }}">RD$ {{ number_format($fila['balance'], 2) }}</td>
                                            <td><a class="btn btn-sm btn-outline-primary ver-detalle-terminal-v2" href="{{ route('gerencia.rentabilidad-agencia-v2', ['mes' => $mes, 'empresa' => $empresa, 'alcance' => 'terminal', 'terminal' => $fila['terminal'], 'productos_filtrados' => 1, 'productos' => $productosSeleccionados->all()]) }}" data-terminal="{{ $fila['terminal'] }}">Ver</a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @elseif ($consultar && $alcance === 'terminal' && $terminal !== '' && $agencia === null)
                    <div class="alert alert-warning">No se encontró el terminal activo {{ $terminal }}{{ $empresa !== '' ? ' en '.$empresa : '' }}.</div>
                @elseif ($consultar && $alcance === 'terminal' && $resumen !== null)
                    <div id="detalleTerminalRentabilidadV2">
                    <div class="card mb-4">
                        <div class="card-header"><h5 class="mb-0">{{ $agencia->nombre_agencia ?: $agencia->agencia ?: $terminal }} · Terminal {{ $terminal }} · {{ $mes }}</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                @foreach ([
                                    'Ventas' => $resumen['venta'],
                                    'Premios pagados' => $resumen['premios'],
                                    'Ingreso real' => $resumen['ingreso_real'],
                                    'Costos' => $resumen['costos'],
                                    'Gastos' => $resumen['gastos'],
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
                        <div class="card-header"><h5 class="mb-0">Venta diaria necesaria para cubrir costos y gastos</h5></div>
                        <div class="card-body">
                            <p class="text-muted small">Escenarios calculados con los costos y gastos del mes y {{ $dias->count() }} días. Son metas de equilibrio, sin ganancia adicional.</p>
                            <div class="row g-3">
                                @foreach ($metasDiarias as $meta)
                                    <div class="col-md-4">
                                        <div class="border rounded p-3">
                                            <div class="text-muted">Pago en premios: {{ $meta['premios_porcentaje'] }} %</div>
                                            <div class="fw-semibold">RD$ {{ number_format($meta['meta_diaria'], 2) }} por día</div>
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
                                <thead><tr><th>Fecha</th><th class="text-end">Ventas</th><th class="text-end">Premios pagados</th><th class="text-end">Ingreso real</th></tr></thead>
                                <tbody>
                                    @foreach ($dias as $dia)
                                        <tr>
                                            <td>{{ $dia['fecha'] }}</td>
                                            <td class="text-end">RD$ {{ number_format($dia['venta'], 2) }}</td>
                                            <td class="text-end">RD$ {{ number_format($dia['premios'], 2) }}</td>
                                            <td class="text-end {{ $dia['ingreso_real'] < 0 ? 'text-danger' : '' }}">RD$ {{ number_format($dia['ingreso_real'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="table-light fw-semibold"><tr><td>Total</td><td class="text-end">RD$ {{ number_format($resumen['venta'], 2) }}</td><td class="text-end">RD$ {{ number_format($resumen['premios'], 2) }}</td><td class="text-end">RD$ {{ number_format($resumen['ingreso_real'], 2) }}</td></tr></tfoot>
                            </table>
                        </div>
                    </div>
                    </div>
                @endif
                @if ($consultar && $alcance === 'todas' && $resumen !== null)
                    <div class="modal fade" id="modalDetalleTerminalRentabilidadV2" tabindex="-1" aria-labelledby="tituloModalDetalleTerminalRentabilidadV2" aria-hidden="true">
                        <div class="modal-dialog modal-xl modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="tituloModalDetalleTerminalRentabilidadV2">Detalle de terminal</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                </div>
                                <div class="modal-body" id="contenidoModalDetalleTerminalRentabilidadV2"></div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const alcance = document.getElementById('alcanceRentabilidadV2');
            const terminal = document.getElementById('terminalRentabilidadV2');
            const actualizarTerminal = () => {
                terminal.disabled = alcance.value === 'todas';
                terminal.required = alcance.value === 'terminal';
            };
            alcance.addEventListener('change', actualizarTerminal);
            actualizarTerminal();
            document.getElementById('formRentabilidadAgenciaV2').addEventListener('submit', function () {
                if (window.Swal) {
                    Swal.fire({
                        title: 'Consultando rentabilidad',
                        text: 'Estamos agrupando ventas, premios y gastos de las terminales.',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => Swal.showLoading(),
                    });
                }
            });

            if (window.jQuery && $.fn.DataTable && document.getElementById('tablaRentabilidadAgenciaV2')) {
                $('#tablaRentabilidadAgenciaV2').DataTable({
                    pageLength: 25,
                    order: [[0, 'asc']],
                    language: { url: '{{ asset('assets/json/es-ES.json') }}' },
                });
            }
            const modalElemento = document.getElementById('modalDetalleTerminalRentabilidadV2');
            if (modalElemento && window.bootstrap) {
                document.body.appendChild(modalElemento);
                const modal = new bootstrap.Modal(modalElemento);
                const contenido = document.getElementById('contenidoModalDetalleTerminalRentabilidadV2');
                document.getElementById('tablaRentabilidadAgenciaV2').addEventListener('click', async function (event) {
                    const enlace = event.target.closest('.ver-detalle-terminal-v2');
                    if (!enlace) return;
                    event.preventDefault();
                    document.getElementById('tituloModalDetalleTerminalRentabilidadV2').textContent = 'Terminal ' + enlace.dataset.terminal;
                    contenido.innerHTML = '<div class="text-center py-4">Cargando detalle...</div>';
                    modal.show();
                    try {
                        const respuesta = await fetch(enlace.href, { headers: { 'Accept': 'text/html' } });
                        if (!respuesta.ok) throw new Error('No se pudo consultar el detalle.');
                        const pagina = new DOMParser().parseFromString(await respuesta.text(), 'text/html');
                        const detalle = pagina.getElementById('detalleTerminalRentabilidadV2');
                        if (!detalle) throw new Error('No se encontró el detalle de la terminal.');
                        contenido.innerHTML = detalle.innerHTML;
                    } catch (error) {
                        contenido.textContent = error.message;
                    }
                });
            }
        });
    </script>
@endsection
