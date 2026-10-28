@extends('app')

@section('content')
    <style>
        #tablaRentabilidadAgenciaV2 .escenario-pago-v2 { display: none; white-space: nowrap; }
        #tablaRentabilidadAgenciaV2.mostrar-pago-60 .escenario-pago-v2[data-porcentaje="60"],
        #tablaRentabilidadAgenciaV2.mostrar-pago-70 .escenario-pago-v2[data-porcentaje="70"],
        #tablaRentabilidadAgenciaV2.mostrar-pago-80 .escenario-pago-v2[data-porcentaje="80"] { display: block; }
        #tablaRentabilidadAgenciaV2 th:nth-child(4),
        #tablaRentabilidadAgenciaV2 th:nth-child(5),
        #tablaRentabilidadAgenciaV2 td:nth-child(4),
        #tablaRentabilidadAgenciaV2 td:nth-child(5) { min-width: 195px; white-space: nowrap; }
    </style>
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
                        <p class="text-muted">Ingreso real = ventas registradas − premios pagados. Premios sacados se muestra por separado y no se resta otra vez. Los costos y gastos provienen de las cuentas activas de Clasificación de cuentas.</p>
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
                                @foreach (['Ventas' => $resumen['venta'], 'Premios sacados' => $resumen['premios_sacados'], 'Premios pagados' => $resumen['premios'], 'Ingreso real' => $resumen['ingreso_real'], 'Costos' => $resumen['costos'], 'Gastos' => $resumen['gastos'], 'Balance' => $resumen['balance']] as $etiqueta => $importe)
                                    <div class="col-md-4 col-xl-2"><div class="border rounded p-3 h-100"><div class="text-muted small">{{ $etiqueta }}</div><div class="fw-semibold {{ $importe < 0 ? 'text-danger' : '' }}">RD$ {{ number_format($importe, 2) }}</div></div></div>
                                @endforeach
                                <div class="col-md-4 col-xl-2"><div class="border rounded p-3 h-100"><div class="text-muted small">Terminales con balance positivo</div><div class="fw-semibold text-success">{{ number_format($resumen['terminales_balance_positivo']) }}</div></div></div>
                                <div class="col-md-4 col-xl-2"><div class="border rounded p-3 h-100"><div class="text-muted small">Terminales con balance negativo</div><div class="fw-semibold text-danger">{{ number_format($resumen['terminales_balance_negativo']) }}</div></div></div>
                            </div>
                            <form id="formInformeEjecutivoRentabilidadV2" method="GET" action="{{ route('gerencia.rentabilidad-agencia-v2.informe-ejecutivo') }}" target="_blank" class="mt-3">
                                <input type="hidden" name="mes" value="{{ $mes }}">
                                <input type="hidden" name="empresa" value="{{ $empresa }}">
                                <input type="hidden" name="productos_filtrados" value="1">
                                <input type="hidden" name="porcentaje_pago" id="porcentajeInformeEjecutivoRentabilidadV2" value="60">
                                @foreach ($productosSeleccionados as $producto)
                                    <input type="hidden" name="productos[]" value="{{ $producto }}">
                                @endforeach
                                <button type="submit" class="btn btn-outline-primary" @disabled($mes >= now()->format('Y-m'))>Descargar informe ejecutivo PDF</button>
                                <small class="text-muted ms-2">Usa el menor porcentaje de pago marcado. Disponible para meses cerrados.</small>
                            </form>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Rentabilidad por terminal</h5></div>
                        <div class="card-body table-responsive">
                            <div id="porcentajesPagoRentabilidadV2" class="d-flex flex-wrap align-items-center gap-3 mb-3">
                                <span class="fw-medium">Metas basadas en gastos fijos:</span>
                                @foreach ([60, 70, 80] as $porcentaje)
                                    <label class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" value="{{ $porcentaje }}" @checked($porcentaje === 60)>
                                        <span class="form-check-label">Pago {{ $porcentaje }} %</span>
                                    </label>
                                @endforeach
                                <small class="text-muted">Meta = gasto fijo ÷ (1 − pago). Ventas por día = meta ÷ días del mes.</small>
                            </div>
                            <table id="tablaRentabilidadAgenciaV2" class="table table-bordered table-striped align-middle w-100 mostrar-pago-60" style="min-width: 1750px">
                                <thead><tr><th>Terminal</th><th>Agencia</th><th class="text-end">Ventas</th><th class="text-end">Ventas por día</th><th class="text-end">Meta de venta</th><th class="text-end">Premios sacados</th><th class="text-end">Premios pagados</th><th class="text-end">Ingreso real</th><th class="text-end">Gasto fijo</th><th class="text-end">Gastos</th><th class="text-end">Balance</th><th>Acción</th></tr></thead>
                                <tbody>
                                    @foreach ($agenciasResumen as $fila)
                                        <tr>
                                            <td>{{ $fila['terminal'] }}</td>
                                            <td>{{ $fila['nombre'] }}</td>
                                            <td class="text-end" data-order="{{ $fila['venta'] }}">RD$ {{ number_format($fila['venta'], 2) }}</td>
                                            <td class="text-end" data-order="{{ $fila['ventas_por_dia'][60] }}">
                                                @foreach ([60, 70, 80] as $porcentaje)
                                                    <div class="escenario-pago-v2" data-porcentaje="{{ $porcentaje }}">{{ $porcentaje }} %: <strong>RD$ {{ number_format($fila['ventas_por_dia'][$porcentaje], 2) }}</strong></div>
                                                @endforeach
                                            </td>
                                            <td class="text-end" data-order="{{ $fila['meta_venta'][60] }}">
                                                @foreach ([60, 70, 80] as $porcentaje)
                                                    <div class="escenario-pago-v2" data-porcentaje="{{ $porcentaje }}">{{ $porcentaje }} %: <strong>RD$ {{ number_format($fila['meta_venta'][$porcentaje], 2) }}</strong></div>
                                                @endforeach
                                            </td>
                                            <td class="text-end" data-order="{{ $fila['premios_sacados'] }}">RD$ {{ number_format($fila['premios_sacados'], 2) }}</td>
                                            <td class="text-end" data-order="{{ $fila['premios'] }}">RD$ {{ number_format($fila['premios'], 2) }}</td>
                                            <td class="text-end" data-order="{{ $fila['ingreso_real'] }}">RD$ {{ number_format($fila['ingreso_real'], 2) }}</td>
                                            <td class="text-end" data-order="{{ $fila['gastos_fijos'] }}">RD$ {{ number_format($fila['gastos_fijos'], 2) }}</td>
                                            <td class="text-end" data-order="{{ $fila['gastos'] }}">RD$ {{ number_format($fila['gastos'], 2) }}</td>
                                            <td class="text-end {{ $fila['balance'] < 0 ? 'text-danger' : '' }}" data-order="{{ $fila['balance'] }}">RD$ {{ number_format($fila['balance'], 2) }}</td>
                                            <td class="text-nowrap">
                                                <a class="btn btn-sm btn-outline-primary ver-detalle-terminal-v2" href="{{ route('gerencia.rentabilidad-agencia-v2', ['mes' => $mes, 'empresa' => $empresa, 'alcance' => 'terminal', 'terminal' => $fila['terminal'], 'productos_filtrados' => 1, 'productos' => $productosSeleccionados->all()]) }}" data-terminal="{{ $fila['terminal'] }}">Ver</a>
                                                <button type="button" class="btn btn-sm btn-outline-secondary ver-cuentas-terminal-v2" data-terminal="{{ $fila['terminal'] }}">Cuentas</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @elseif ($consultar && $alcance === 'terminal' && $terminal !== '' && $agencia === null)
                    <div class="alert alert-warning">No se encontró el terminal activo {{ $terminal }}{{ $empresa !== '' ? ' en '.$empresa : '' }}.</div>
                @elseif ($consultar && $alcance === 'terminal' && $resumen !== null)
                    @include('gerencia.rentabilidad-agencia-v2-detalle')
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
                    <div class="modal fade" id="modalCuentasRentabilidadV2" tabindex="-1" aria-labelledby="tituloModalCuentasRentabilidadV2" aria-hidden="true">
                        <div class="modal-dialog modal-xl modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <div>
                                        <h5 class="modal-title" id="tituloModalCuentasRentabilidadV2">Movimientos de cuentas</h5>
                                        <span class="text-muted" id="terminalModalCuentasRentabilidadV2"></span>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered align-middle mb-0">
                                            <thead class="table-light"><tr><th>Cuenta</th><th>Descripción</th><th>Tipo</th><th>Clasificación</th><th class="text-end">Costo</th><th class="text-end">Gasto fijo</th><th class="text-end">Gasto variable</th><th class="text-end">Total gasto</th></tr></thead>
                                            <tbody id="filasModalCuentasRentabilidadV2"></tbody>
                                            <tfoot class="table-light fw-semibold"><tr><td colspan="4">Total</td><td class="text-end" id="totalCostosCuentasV2">RD$ 0.00</td><td class="text-end" id="totalFijosCuentasV2">RD$ 0.00</td><td class="text-end" id="totalVariablesCuentasV2">RD$ 0.00</td><td class="text-end" id="totalGastosCuentasV2">RD$ 0.00</td></tr></tfoot>
                                        </table>
                                    </div>
                                </div>
                                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button></div>
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
            const tablaMetas = document.getElementById('tablaRentabilidadAgenciaV2');
            if (tablaMetas) {
                document.querySelectorAll('#porcentajesPagoRentabilidadV2 input[type="checkbox"]').forEach((checkbox) => {
                    checkbox.addEventListener('change', function () {
                        tablaMetas.classList.toggle(`mostrar-pago-${this.value}`, this.checked);
                    });
                });
            }
            const formInforme = document.getElementById('formInformeEjecutivoRentabilidadV2');
            if (formInforme) {
                formInforme.addEventListener('submit', function (event) {
                    const porcentajes = [...document.querySelectorAll('#porcentajesPagoRentabilidadV2 input:checked')]
                        .map((checkbox) => Number(checkbox.value));
                    if (porcentajes.length === 0) {
                        event.preventDefault();
                        if (window.Swal) Swal.fire('Selecciona un pago', 'Marca 60 %, 70 % u 80 % antes de generar el informe.', 'warning');
                        return;
                    }
                    document.getElementById('porcentajeInformeEjecutivoRentabilidadV2').value = Math.min(...porcentajes);
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
                    const controlador = new AbortController();
                    const esperaMaxima = setTimeout(() => controlador.abort(), 30000);
                    try {
                        const respuesta = await fetch(enlace.href, { headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, signal: controlador.signal });
                        if (!respuesta.ok) throw new Error('No se pudo consultar el detalle.');
                        if (respuesta.redirected) throw new Error('La sesión expiró. Actualiza la página e inicia sesión de nuevo.');
                        const pagina = new DOMParser().parseFromString(await respuesta.text(), 'text/html');
                        const detalle = pagina.getElementById('detalleTerminalRentabilidadV2');
                        if (!detalle) throw new Error('No se encontró el detalle de la terminal.');
                        contenido.replaceChildren(...detalle.cloneNode(true).childNodes);
                    } catch (error) {
                        contenido.textContent = error.name === 'AbortError'
                            ? 'La consulta tardó demasiado. Intenta de nuevo o reduce los productos seleccionados.'
                            : error.message;
                    } finally {
                        clearTimeout(esperaMaxima);
                    }
                });
            }
            const modalCuentasElemento = document.getElementById('modalCuentasRentabilidadV2');
            if (modalCuentasElemento && window.bootstrap) {
                document.body.appendChild(modalCuentasElemento);
                const modalCuentas = new bootstrap.Modal(modalCuentasElemento);
                const filasCuentas = document.getElementById('filasModalCuentasRentabilidadV2');
                const moneda = (valor) => `RD$ ${Number(valor || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                let consultaCuentas;
                document.getElementById('tablaRentabilidadAgenciaV2').addEventListener('click', async function (event) {
                    const boton = event.target.closest('.ver-cuentas-terminal-v2');
                    if (!boton) return;
                    consultaCuentas?.abort();
                    const controlador = new AbortController();
                    consultaCuentas = controlador;
                    const esperaMaxima = setTimeout(() => controlador.abort(), 30000);
                    document.getElementById('tituloModalCuentasRentabilidadV2').textContent = 'Movimientos de cuentas';
                    document.getElementById('terminalModalCuentasRentabilidadV2').textContent = `Terminal ${boton.dataset.terminal}`;
                    filasCuentas.innerHTML = '<tr><td colspan="8" class="text-center py-4">Cargando cuentas...</td></tr>';
                    modalCuentas.show();
                    try {
                        const url = new URL({{ Illuminate\Support\Js::from(route('gerencia.rentabilidad-agencia-v2.cuentas')) }});
                        url.searchParams.set('mes', {{ Illuminate\Support\Js::from($mes) }});
                        url.searchParams.set('empresa', {{ Illuminate\Support\Js::from($empresa) }});
                        url.searchParams.set('terminal', boton.dataset.terminal);
                        const respuesta = await fetch(url, { headers: { 'Accept': 'application/json' }, signal: controlador.signal });
                        if (!respuesta.ok) throw new Error('No se pudieron consultar las cuentas de esta terminal.');
                        const datos = await respuesta.json();
                        if (controlador !== consultaCuentas) return;
                        document.getElementById('tituloModalCuentasRentabilidadV2').textContent = `Movimientos de ${datos.agencia}`;
                        filasCuentas.replaceChildren();
                        if (datos.movimientos.length === 0) {
                            const fila = document.createElement('tr');
                            const celda = document.createElement('td');
                            celda.colSpan = 8;
                            celda.className = 'text-center text-muted';
                            celda.textContent = 'Sin costos ni gastos en el mes consultado.';
                            fila.appendChild(celda);
                            filasCuentas.appendChild(fila);
                        }
                        datos.movimientos.forEach((movimiento) => {
                            const fila = document.createElement('tr');
                            const fijo = movimiento.tipo === 'gasto' && movimiento.clasificacion === 'FIJO';
                            const variable = movimiento.tipo === 'gasto' && movimiento.clasificacion === 'VARIABLE';
                            [movimiento.cuenta, movimiento.descripcion, movimiento.tipo === 'costo' ? 'Costo' : 'Gasto', movimiento.clasificacion || 'Sin clasificar', movimiento.tipo === 'costo' ? moneda(movimiento.importe) : '—', fijo ? moneda(movimiento.importe) : '—', variable ? moneda(movimiento.importe) : '—', movimiento.tipo === 'gasto' ? moneda(movimiento.importe) : '—'].forEach((valor, indice) => {
                                const celda = document.createElement('td');
                                celda.textContent = valor;
                                if (indice >= 4) celda.className = 'text-end fw-semibold';
                                fila.appendChild(celda);
                            });
                            filasCuentas.appendChild(fila);
                        });
                        document.getElementById('totalCostosCuentasV2').textContent = moneda(datos.costos);
                        document.getElementById('totalFijosCuentasV2').textContent = moneda(datos.gastos_fijos);
                        document.getElementById('totalVariablesCuentasV2').textContent = moneda(datos.gastos_variables);
                        document.getElementById('totalGastosCuentasV2').textContent = moneda(datos.gastos);
                    } catch (error) {
                        if (controlador !== consultaCuentas) return;
                        if (error.name === 'AbortError' && !modalCuentasElemento.classList.contains('show')) return;
                        filasCuentas.innerHTML = `<tr><td colspan="8" class="text-center text-danger">${error.name === 'AbortError' ? 'La consulta tardó demasiado. Intenta de nuevo.' : 'No se pudieron cargar las cuentas.'}</td></tr>`;
                    } finally {
                        clearTimeout(esperaMaxima);
                    }
                });
                modalCuentasElemento.addEventListener('hidden.bs.modal', () => consultaCuentas?.abort());
            }
        });
    </script>
@endsection
