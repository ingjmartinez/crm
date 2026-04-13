@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Electricidad</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('contabilidad.index') }}">Contabilidad</a></li>
                                    <li class="breadcrumb-item active">Electricidad</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="row g-2 align-items-end">
                                    <div class="col-12 col-md-3">
                                        <label class="form-label">Mes</label>
                                        <input type="month" class="form-control" id="filtroMes">
                                    </div>
                                    <div class="col-12 col-md-3">
                                        <label class="form-label">Empresa</label>
                                        <select class="form-select" id="filtroEmpresa">
                                            <option value="">Todas</option>
                                            <option value="Joselito">Joselito</option>
                                            <option value="Negosur">Negosur</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-2">
                                        <label class="form-label">Estado</label>
                                        <select class="form-select" id="filtroPagado">
                                            <option value="todos">Todos</option>
                                            <option value="si">Pagado</option>
                                            <option value="no">Pendiente</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-4 d-grid d-md-flex justify-content-md-end gap-2">
                                        <button type="button" class="btn btn-info" id="btnFiltrarElectricidad">Filtrar</button>
                                        <button type="button" class="btn btn-success" id="btnNuevoElectricidad">Nuevo registro</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 mb-3">
                        <div class="row g-2" id="resumenElectricidad">
                            <div class="col-12 col-md-4">
                                <div class="border rounded p-3 bg-light h-100">
                                    <div class="small text-muted">Registros</div>
                                    <div class="fs-5 fw-semibold" id="totalRegistros">0</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="border rounded p-3 bg-light h-100">
                                    <div class="small text-muted">kWh total</div>
                                    <div class="fs-5 fw-semibold" id="totalKwh">0.000</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="border rounded p-3 bg-light h-100">
                                    <div class="small text-muted">Pendiente por pagar</div>
                                    <div class="fs-5 fw-semibold text-danger" id="totalPendiente">0.00</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Control de facturas de electricidad</h5>
                                <div class="text-muted small">Consumo kWh y costo se calculan automaticamente.</div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="tablaElectricidad" class="table table-bordered table-striped align-middle mb-0" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Empresa</th>
                                                <th>Sucursal</th>
                                                <th>Contrato</th>
                                                <th class="text-end">Lectura Ant.</th>
                                                <th class="text-end">Lectura Act.</th>
                                                <th class="text-end">Ajuste</th>
                                                <th class="text-end">kWh</th>
                                                <th class="text-end">Tarifa</th>
                                                <th class="text-end">Total</th>
                                                <th class="text-center">Pagado</th>
                                                <th class="text-center">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="electricidadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="electricidadModalLabel">Nuevo registro de electricidad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="electricidadId">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label">Fecha factura *</label>
                            <input type="date" class="form-control" id="electricidadFecha">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Empresa *</label>
                            <select id="electricidadEmpresa" class="form-select">
                                <option value="">Seleccione</option>
                                <option value="Joselito">Joselito</option>
                                <option value="Negosur">Negosur</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sucursal *</label>
                            <input type="text" class="form-control" id="electricidadSucursal" maxlength="120" placeholder="Sucursal o local">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Contrato</label>
                            <input type="text" class="form-control" id="electricidadContrato" maxlength="50">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Medidor</label>
                            <input type="text" class="form-control" id="electricidadMedidor" maxlength="50">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Lectura anterior *</label>
                            <input type="number" min="0" step="0.001" class="form-control" id="electricidadLecturaAnterior" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Lectura actual *</label>
                            <input type="number" min="0" step="0.001" class="form-control" id="electricidadLecturaActual" value="0">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Ajuste kWh</label>
                            <input type="number" step="0.001" class="form-control" id="electricidadAjuste" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tarifa kWh *</label>
                            <input type="number" min="0" step="0.0001" class="form-control" id="electricidadTarifa" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Otros cargos</label>
                            <input type="number" min="0" step="0.01" class="form-control" id="electricidadOtros" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Impuestos</label>
                            <input type="number" min="0" step="0.01" class="form-control" id="electricidadImpuestos" value="0">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Consumo kWh</label>
                            <input type="text" class="form-control" id="electricidadConsumoPreview" readonly value="0.000">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Total estimado</label>
                            <input type="text" class="form-control" id="electricidadTotalPreview" readonly value="0.00">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="electricidadPagado">
                                <label class="form-check-label" for="electricidadPagado">Pagado</label>
                            </div>
                        </div>
                        <div class="col-md-2" id="wrapFechaPago">
                            <label class="form-label">Fecha pago</label>
                            <input type="date" class="form-control" id="electricidadFechaPago">
                        </div>
                        <div class="col-md-2" id="wrapReferenciaPago">
                            <label class="form-label">Ref. pago</label>
                            <input type="text" class="form-control" id="electricidadReferenciaPago" maxlength="120">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observacion</label>
                            <textarea class="form-control" id="electricidadObservacion" rows="2" maxlength="1000" placeholder="Comentario opcional"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarElectricidad">Guardar</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const csrfToken = '{{ csrf_token() }}';
            const modalElement = document.getElementById('electricidadModal');
            const electricidadModal = new bootstrap.Modal(modalElement);
            const tablaBody = document.querySelector('#tablaElectricidad tbody');
            const filtroMes = document.getElementById('filtroMes');
            const filtroEmpresa = document.getElementById('filtroEmpresa');
            const filtroPagado = document.getElementById('filtroPagado');
            const btnFiltrar = document.getElementById('btnFiltrarElectricidad');
            const btnNuevo = document.getElementById('btnNuevoElectricidad');
            const btnGuardar = document.getElementById('btnGuardarElectricidad');
            const totalRegistros = document.getElementById('totalRegistros');
            const totalKwh = document.getElementById('totalKwh');
            const totalPendiente = document.getElementById('totalPendiente');

            const electricidadId = document.getElementById('electricidadId');
            const electricidadFecha = document.getElementById('electricidadFecha');
            const electricidadEmpresa = document.getElementById('electricidadEmpresa');
            const electricidadSucursal = document.getElementById('electricidadSucursal');
            const electricidadContrato = document.getElementById('electricidadContrato');
            const electricidadMedidor = document.getElementById('electricidadMedidor');
            const electricidadLecturaAnterior = document.getElementById('electricidadLecturaAnterior');
            const electricidadLecturaActual = document.getElementById('electricidadLecturaActual');
            const electricidadAjuste = document.getElementById('electricidadAjuste');
            const electricidadTarifa = document.getElementById('electricidadTarifa');
            const electricidadOtros = document.getElementById('electricidadOtros');
            const electricidadImpuestos = document.getElementById('electricidadImpuestos');
            const electricidadConsumoPreview = document.getElementById('electricidadConsumoPreview');
            const electricidadTotalPreview = document.getElementById('electricidadTotalPreview');
            const electricidadPagado = document.getElementById('electricidadPagado');
            const electricidadFechaPago = document.getElementById('electricidadFechaPago');
            const electricidadReferenciaPago = document.getElementById('electricidadReferenciaPago');
            const electricidadObservacion = document.getElementById('electricidadObservacion');
            const wrapFechaPago = document.getElementById('wrapFechaPago');
            const wrapReferenciaPago = document.getElementById('wrapReferenciaPago');

            function formatMoney(value) {
                const number = parseFloat(value || '0') || 0;
                return number.toFixed(2);
            }

            function formatKwh(value) {
                const number = parseFloat(value || '0') || 0;
                return number.toFixed(3);
            }

            function recalcularPreview() {
                const lecturaAnterior = parseFloat(electricidadLecturaAnterior.value || '0') || 0;
                const lecturaActual = parseFloat(electricidadLecturaActual.value || '0') || 0;
                const ajuste = parseFloat(electricidadAjuste.value || '0') || 0;
                const tarifa = parseFloat(electricidadTarifa.value || '0') || 0;
                const otros = parseFloat(electricidadOtros.value || '0') || 0;
                const impuestos = parseFloat(electricidadImpuestos.value || '0') || 0;

                const consumo = Math.max(0, (lecturaActual - lecturaAnterior) + ajuste);
                const subtotal = consumo * tarifa;
                const total = subtotal + otros + impuestos;

                electricidadConsumoPreview.value = formatKwh(consumo);
                electricidadTotalPreview.value = formatMoney(total);
            }

            function togglePagoFields() {
                const visible = !!electricidadPagado.checked;
                wrapFechaPago.classList.toggle('d-none', !visible);
                wrapReferenciaPago.classList.toggle('d-none', !visible);
                if (!visible) {
                    electricidadFechaPago.value = '';
                    electricidadReferenciaPago.value = '';
                }
            }

            function resetModal() {
                electricidadId.value = '';
                document.getElementById('electricidadModalLabel').textContent = 'Nuevo registro de electricidad';
                electricidadFecha.value = '';
                electricidadEmpresa.value = '';
                electricidadSucursal.value = '';
                electricidadContrato.value = '';
                electricidadMedidor.value = '';
                electricidadLecturaAnterior.value = '0';
                electricidadLecturaActual.value = '0';
                electricidadAjuste.value = '0';
                electricidadTarifa.value = '0';
                electricidadOtros.value = '0';
                electricidadImpuestos.value = '0';
                electricidadPagado.checked = false;
                electricidadFechaPago.value = '';
                electricidadReferenciaPago.value = '';
                electricidadObservacion.value = '';
                togglePagoFields();
                recalcularPreview();
            }

            function llenarModalParaEditar(item) {
                electricidadId.value = item.id || '';
                document.getElementById('electricidadModalLabel').textContent = 'Editar registro de electricidad';
                electricidadFecha.value = item.fecha_factura || '';
                electricidadEmpresa.value = item.empresa || '';
                electricidadSucursal.value = item.sucursal || '';
                electricidadContrato.value = item.contrato || '';
                electricidadMedidor.value = item.medidor || '';
                electricidadLecturaAnterior.value = item.lectura_anterior ?? 0;
                electricidadLecturaActual.value = item.lectura_actual ?? 0;
                electricidadAjuste.value = item.ajuste_kwh ?? 0;
                electricidadTarifa.value = item.tarifa_kwh ?? 0;
                electricidadOtros.value = item.otros_cargos ?? 0;
                electricidadImpuestos.value = item.impuestos ?? 0;
                electricidadPagado.checked = !!item.pagado;
                electricidadFechaPago.value = item.fecha_pago || '';
                electricidadReferenciaPago.value = item.referencia_pago || '';
                electricidadObservacion.value = item.observacion || '';
                togglePagoFields();
                recalcularPreview();
            }

            function obtenerFiltros() {
                const params = new URLSearchParams();
                if ((filtroMes.value || '').trim() !== '') {
                    params.set('mes', filtroMes.value.trim());
                }
                if ((filtroEmpresa.value || '').trim() !== '') {
                    params.set('empresa', filtroEmpresa.value.trim());
                }
                params.set('pagado', (filtroPagado.value || 'todos').trim());
                return params;
            }

            function pintarTabla(items) {
                tablaBody.innerHTML = '';

                items.forEach(function (item) {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${item.fecha_factura || ''}</td>
                        <td>${item.empresa || ''}</td>
                        <td>${item.sucursal || ''}</td>
                        <td>${item.contrato || ''}</td>
                        <td class="text-end">${formatKwh(item.lectura_anterior)}</td>
                        <td class="text-end">${formatKwh(item.lectura_actual)}</td>
                        <td class="text-end">${formatKwh(item.ajuste_kwh)}</td>
                        <td class="text-end fw-semibold">${formatKwh(item.consumo_kwh)}</td>
                        <td class="text-end">${formatMoney(item.tarifa_kwh)}</td>
                        <td class="text-end fw-semibold">${formatMoney(item.total_factura)}</td>
                        <td class="text-center">${item.pagado ? '<span class="badge bg-success-subtle text-success">Pagado</span>' : '<span class="badge bg-warning-subtle text-warning">Pendiente</span>'}</td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-primary me-1 btn-editar-electricidad">Editar</button>
                            <button class="btn btn-sm btn-danger btn-eliminar-electricidad">Eliminar</button>
                        </td>
                    `;

                    row.querySelector('.btn-editar-electricidad')?.addEventListener('click', function () {
                        llenarModalParaEditar(item);
                        electricidadModal.show();
                    });

                    row.querySelector('.btn-eliminar-electricidad')?.addEventListener('click', async function () {
                        if (!confirm('¿Deseas eliminar este registro de electricidad?')) {
                            return;
                        }

                        try {
                            const response = await fetch('/contabilidad/electricidad/' + item.id, {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': csrfToken,
                                    'Accept': 'application/json',
                                },
                            });

                            const payload = await response.json().catch(function () { return {}; });
                            if (!response.ok) {
                                throw new Error(payload.message || 'No se pudo eliminar el registro.');
                            }

                            await cargarElectricidad();
                        } catch (error) {
                            alert(error.message || 'No se pudo eliminar el registro.');
                        }
                    });

                    tablaBody.appendChild(row);
                });

                if ($.fn.DataTable.isDataTable('#tablaElectricidad')) {
                    $('#tablaElectricidad').DataTable().destroy();
                }

                $('#tablaElectricidad').DataTable({
                    responsive: true,
                    scrollX: true,
                    order: [[0, 'desc']],
                    dom: 'Bfrtip',
                    buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
                });
            }

            async function cargarElectricidad() {
                const params = obtenerFiltros();
                const query = params.toString();
                const url = '/contabilidad/electricidad/data' + (query !== '' ? ('?' + query) : '');

                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('No se pudo cargar la data de electricidad.');
                }

                const payload = await response.json();
                pintarTabla(payload.data || []);

                const resumen = payload.resumen || {};
                totalRegistros.textContent = String(resumen.registros || 0);
                totalKwh.textContent = formatKwh(resumen.kwh_total || 0);
                totalPendiente.textContent = formatMoney(resumen.monto_pendiente || 0);
            }

            async function guardarElectricidad() {
                const id = (electricidadId.value || '').trim();
                const payload = {
                    fecha_factura: electricidadFecha.value,
                    empresa: electricidadEmpresa.value,
                    sucursal: electricidadSucursal.value.trim(),
                    contrato: electricidadContrato.value.trim(),
                    medidor: electricidadMedidor.value.trim(),
                    lectura_anterior: electricidadLecturaAnterior.value,
                    lectura_actual: electricidadLecturaActual.value,
                    ajuste_kwh: electricidadAjuste.value,
                    tarifa_kwh: electricidadTarifa.value,
                    otros_cargos: electricidadOtros.value,
                    impuestos: electricidadImpuestos.value,
                    pagado: electricidadPagado.checked ? 1 : 0,
                    fecha_pago: electricidadPagado.checked ? electricidadFechaPago.value : null,
                    referencia_pago: electricidadPagado.checked ? electricidadReferenciaPago.value.trim() : null,
                    observacion: electricidadObservacion.value.trim(),
                };

                if (!payload.fecha_factura || !payload.empresa || !payload.sucursal) {
                    alert('Fecha, empresa y sucursal son obligatorias.');
                    return;
                }

                const method = id === '' ? 'POST' : 'PUT';
                const url = id === '' ? '/contabilidad/electricidad' : ('/contabilidad/electricidad/' + id);

                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(payload),
                });

                const json = await response.json().catch(function () { return {}; });
                if (!response.ok) {
                    const firstValidation = json?.errors ? Object.values(json.errors)[0]?.[0] : '';
                    throw new Error(firstValidation || json.message || 'No se pudo guardar el registro.');
                }

                electricidadModal.hide();
                await cargarElectricidad();
            }

            btnNuevo?.addEventListener('click', function () {
                resetModal();
                electricidadModal.show();
            });

            btnFiltrar?.addEventListener('click', function () {
                cargarElectricidad().catch(function (error) {
                    alert(error.message || 'No se pudo filtrar la data.');
                });
            });

            [
                electricidadLecturaAnterior,
                electricidadLecturaActual,
                electricidadAjuste,
                electricidadTarifa,
                electricidadOtros,
                electricidadImpuestos,
            ].forEach(function (input) {
                input?.addEventListener('input', recalcularPreview);
            });

            electricidadPagado?.addEventListener('change', togglePagoFields);
            btnGuardar?.addEventListener('click', function () {
                guardarElectricidad().catch(function (error) {
                    alert(error.message || 'No se pudo guardar el registro.');
                });
            });

            resetModal();
        });
    </script>
@endsection
