@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">

                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Ventas por Producto</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="/">Inicio</a></li>
                                    <li class="breadcrumb-item">Comercial</li>
                                    <li class="breadcrumb-item active">Ventas por Producto</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label">Fecha</label>
                                        <input type="date" id="inputFecha" class="form-control"
                                            value="{{ now()->format('Y-m-d') }}">
                                    </div>
                                    <div class="col-md-9 d-flex gap-2 flex-wrap">
                                        <button id="btnGenerarToken" class="btn btn-secondary">
                                            <i class="ri-key-line me-1"></i>Generar Token
                                        </button>
                                        <button id="btnGenerarData" class="btn btn-info text-white">
                                            <i class="ri-download-cloud-line me-1"></i>Generar Data
                                        </button>
                                        <button id="btnConsultar" class="btn btn-primary">
                                            <i class="ri-search-line me-1"></i>Consultar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 d-none" id="seccionResultados">
                        <div class="row mb-3">
                            <div class="col-xl-4 col-md-6">
                                <div class="card card-animate mb-0">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-grow-1">
                                                <p class="text-uppercase fw-medium text-muted mb-0">Monto Total Vendido</p>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-end justify-content-between mt-4">
                                            <div>
                                                <h4 class="fs-22 fw-semibold mb-0" id="cardMontoTotal">RD$ 0.00</h4>
                                            </div>
                                            <div class="avatar-sm flex-shrink-0">
                                                <span class="avatar-title bg-success-subtle rounded fs-3">
                                                    <i class="ri-money-dollar-circle-line text-success"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-4 col-md-6">
                                <div class="card card-animate mb-0">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-grow-1">
                                                <p class="text-uppercase fw-medium text-muted mb-0">Total de Agencias (Distinct)</p>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-end justify-content-between mt-4">
                                            <div>
                                                <h4 class="fs-22 fw-semibold mb-0" id="cardTotalAgencias">0</h4>
                                            </div>
                                            <div class="avatar-sm flex-shrink-0">
                                                <span class="avatar-title bg-info-subtle rounded fs-3">
                                                    <i class="ri-building-2-line text-info"></i>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <button id="btnVerAgencias" class="btn btn-sm btn-outline-info">Ver Detalle</button>
                                            <button id="btnConfigVentasMinimo" class="btn btn-sm btn-outline-primary">Ventas mínimo</button>
                                            <button id="btnLimpiarAgencia" class="btn btn-sm btn-outline-secondary d-none">Limpiar Filtro</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-4 col-md-12">
                                <div class="card card-animate mb-0">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <p class="text-uppercase fw-medium text-muted mb-0">Cumplimiento de Agencias</p>
                                            <span class="badge bg-light text-dark" id="labelFiltroCumplimientoAgencia">Filtro: Todos</span>
                                        </div>
                                        <div class="d-flex gap-4 mt-3">
                                            <div>
                                                <small class="text-muted d-block">Cumplen</small>
                                                <h5 class="mb-0 text-success" id="cardAgenciasCumplen">0</h5>
                                            </div>
                                            <div>
                                                <small class="text-muted d-block">No cumplen</small>
                                                <h5 class="mb-0 text-danger" id="cardAgenciasNoCumplen">0</h5>
                                            </div>
                                        </div>
                                        <div class="mt-3 d-flex gap-2 flex-wrap">
                                            <button id="btnFiltroAgenciasTodos" class="btn btn-sm btn-outline-secondary">Todos</button>
                                            <button id="btnFiltroAgenciasCumplen" class="btn btn-sm btn-outline-success">Cumplen</button>
                                            <button id="btnFiltroAgenciasNoCumplen" class="btn btn-sm btn-outline-danger">No cumplen</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h5 class="card-title mb-0">Resumen por Producto</h5>
                                <span class="badge bg-primary-subtle text-primary fs-12" id="labelFechaAplicada"></span>
                            </div>
                            <div class="card-body">
                                <table id="tableProductos"
                                    class="table table-bordered dt-responsive nowrap table-striped align-middle"
                                    style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Descripción (Producto)</th>
                                            <th>Total Vendido</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h5 class="card-title mb-0">Resumen por Agencia</h5>
                                <span class="badge bg-light text-dark" id="labelMinimoVentas">Mínimo: RD$ 0.00</span>
                            </div>
                            <div class="card-body">
                                <table id="tableAgencias"
                                    class="table table-bordered dt-responsive nowrap table-striped align-middle"
                                    style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Agencia</th>
                                            <th>Total Vendido</th>
                                            <th>Cumplimiento</th>
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
@endsection

<div class="modal fade" id="modalAgencias" tabindex="-1" aria-labelledby="modalAgenciasLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAgenciasLabel">Agencias con Ventas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div class="d-flex gap-2 flex-wrap">
                        <button id="btnModalAgenciasTodos" class="btn btn-sm btn-outline-secondary">Todos</button>
                        <button id="btnModalAgenciasCumplen" class="btn btn-sm btn-outline-success">Cumplen</button>
                        <button id="btnModalAgenciasNoCumplen" class="btn btn-sm btn-outline-danger">No cumplen</button>
                    </div>
                    <span class="badge bg-light text-dark" id="labelModalAgenciasFiltro">Filtro: Todos</span>
                </div>
                <div class="table-responsive">
                    <table id="tableModalAgencias" class="table table-striped table-bordered w-100">
                        <thead>
                            <tr>
                                <th>Agencia</th>
                                <th>Total Vendido</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalVentasMinimo" tabindex="-1" aria-labelledby="modalVentasMinimoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalVentasMinimoLabel">Configurar Ventas Mínimo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label for="inputVentasMinimo" class="form-label">Monto mínimo para cumplimiento</label>
                <input type="number" class="form-control" id="inputVentasMinimo" min="0" step="0.01" value="0">
                <small class="text-muted">Ejemplo: si configuras 1000, agencia con 1001 cumple; con 999 no cumple.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarVentasMinimo">Guardar</button>
            </div>
        </div>
    </div>
</div>

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btnConsultar   = document.getElementById('btnConsultar');
    const inputFecha     = document.getElementById('inputFecha');
    const seccion        = document.getElementById('seccionResultados');
    const labelFecha     = document.getElementById('labelFechaAplicada');
    const cardMontoTotal = document.getElementById('cardMontoTotal');
    const cardTotalAgencias = document.getElementById('cardTotalAgencias');
    const btnVerAgencias = document.getElementById('btnVerAgencias');
    const btnConfigVentasMinimo = document.getElementById('btnConfigVentasMinimo');
    const btnLimpiarAgencia = document.getElementById('btnLimpiarAgencia');
    const labelMinimoVentas = document.getElementById('labelMinimoVentas');
    const cardAgenciasCumplen = document.getElementById('cardAgenciasCumplen');
    const cardAgenciasNoCumplen = document.getElementById('cardAgenciasNoCumplen');
    const labelFiltroCumplimientoAgencia = document.getElementById('labelFiltroCumplimientoAgencia');
    const btnFiltroAgenciasTodos = document.getElementById('btnFiltroAgenciasTodos');
    const btnFiltroAgenciasCumplen = document.getElementById('btnFiltroAgenciasCumplen');
    const btnFiltroAgenciasNoCumplen = document.getElementById('btnFiltroAgenciasNoCumplen');
    const btnModalAgenciasTodos = document.getElementById('btnModalAgenciasTodos');
    const btnModalAgenciasCumplen = document.getElementById('btnModalAgenciasCumplen');
    const btnModalAgenciasNoCumplen = document.getElementById('btnModalAgenciasNoCumplen');
    const labelModalAgenciasFiltro = document.getElementById('labelModalAgenciasFiltro');
    let dtProductos      = null;
    let dtAgencias       = null;
    let dtModalAgencias  = null;
    let ventasFuente     = [];
    let agenciaSeleccionada = null;
    let ventasMinimoConfig = 0;
    let filtroCumplimientoAgencia = 'todos';
    let resumenAgenciaVisibleActual = new Map();
    let modalAgencyEntries = [];
    let modalAgencyFilter = 'todos';

    const formatMoney = (value) => Number(value ?? 0).toLocaleString('es-DO', { minimumFractionDigits: 2 });
    const cumpleMinimo = (total) => Number(total ?? 0) >= Number(ventasMinimoConfig ?? 0);

    const updateFiltroLabel = () => {
        const texto = filtroCumplimientoAgencia === 'cumplen'
            ? 'Filtro: Cumplen'
            : filtroCumplimientoAgencia === 'no_cumplen'
                ? 'Filtro: No cumplen'
                : 'Filtro: Todos';
        labelFiltroCumplimientoAgencia.textContent = texto;
    };

    const filtrarResumenAgenciaPorCumplimiento = (resumenMap) => {
        const entries = [...resumenMap.entries()];
        if (filtroCumplimientoAgencia === 'cumplen') {
            return entries.filter(([, total]) => cumpleMinimo(total));
        }
        if (filtroCumplimientoAgencia === 'no_cumplen') {
            return entries.filter(([, total]) => !cumpleMinimo(total));
        }
        return entries;
    };

    const updateModalFiltroLabel = () => {
        const texto = modalAgencyFilter === 'cumplen'
            ? 'Filtro: Cumplen'
            : modalAgencyFilter === 'no_cumplen'
                ? 'Filtro: No cumplen'
                : 'Filtro: Todos';
        labelModalAgenciasFiltro.textContent = texto;
    };

    const filtrarModalAgencias = () => {
        if (modalAgencyFilter === 'cumplen') {
            return modalAgencyEntries.filter(([, total]) => cumpleMinimo(total));
        }
        if (modalAgencyFilter === 'no_cumplen') {
            return modalAgencyEntries.filter(([, total]) => !cumpleMinimo(total));
        }
        return modalAgencyEntries;
    };

    const renderModalAgencias = () => {
        if (dtModalAgencias) {
            dtModalAgencias.destroy();
            dtModalAgencias = null;
        }

        const tbodyModal = document.querySelector('#tableModalAgencias tbody');
        tbodyModal.innerHTML = '';

        const agenciasFiltradas = filtrarModalAgencias();
        agenciasFiltradas.forEach(([agencia, total]) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${agencia}</td>
                <td>${formatMoney(total)}</td>
                <td><button class="btn btn-sm btn-primary btnFiltrarAgencia" data-agencia="${agencia}">Ver</button></td>
            `;
            tbodyModal.appendChild(tr);
        });

        dtModalAgencias = $('#tableModalAgencias').DataTable({
            destroy: true,
            responsive: true,
            language: {
                url: '/json/es-DO.json',
                search: 'Buscar:',
                lengthMenu: 'Mostrar _MENU_ registros',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                paginate: { first: 'Primera', last: 'Última', next: 'Siguiente', previous: 'Anterior' }
            },
            order: [[1, 'desc']],
        });

        updateModalFiltroLabel();
    };

    const getCumplimientoBadge = (total) => {
        const cumple = cumpleMinimo(total);
        return cumple
            ? '<span class="badge bg-success">CUMPLIÓ</span>'
            : '<span class="badge bg-danger">NO CUMPLE</span>';
    };

    const destroyTables = () => {
        if (dtProductos) {
            dtProductos.destroy();
            dtProductos = null;
        }
        if (dtAgencias) {
            dtAgencias.destroy();
            dtAgencias = null;
        }
        document.querySelector('#tableProductos tbody').innerHTML = '';
        document.querySelector('#tableAgencias tbody').innerHTML = '';
        cardMontoTotal.textContent = 'RD$ 0.00';
        cardTotalAgencias.textContent = '0';
        cardAgenciasCumplen.textContent = '0';
        cardAgenciasNoCumplen.textContent = '0';
        labelFecha.textContent = '';
        labelMinimoVentas.textContent = `Mínimo: RD$ ${formatMoney(ventasMinimoConfig)}`;
        resumenAgenciaVisibleActual = new Map();
        agenciaSeleccionada = null;
        filtroCumplimientoAgencia = 'todos';
        updateFiltroLabel();
        btnLimpiarAgencia.classList.add('d-none');
    };

    const renderResumen = (ventas) => {
        const resumenProducto = new Map();
        const resumenAgencia = new Map();
        let totalVendido = 0;

        (ventas ?? []).forEach(item => {
            const descripcion = (item.descripcion ?? 'SIN DESCRIPCIÓN').toString().trim() || 'SIN DESCRIPCIÓN';
            const agencia = (item.agencia_id ?? 'SIN AGENCIA').toString().trim() || 'SIN AGENCIA';
            const monto = Number(item.monto ?? 0);

            resumenProducto.set(descripcion, (resumenProducto.get(descripcion) ?? 0) + monto);
            resumenAgencia.set(agencia, (resumenAgencia.get(agencia) ?? 0) + monto);
            totalVendido += monto;
        });

        cardMontoTotal.textContent = `RD$ ${formatMoney(totalVendido)}`;
        const agenciasCumplen = [...resumenAgencia.entries()].filter(([, total]) => cumpleMinimo(total)).length;
        const agenciasNoCumplen = [...resumenAgencia.entries()].length - agenciasCumplen;
        cardAgenciasCumplen.textContent = agenciasCumplen.toLocaleString('es-DO');
        cardAgenciasNoCumplen.textContent = agenciasNoCumplen.toLocaleString('es-DO');

        const agenciasFiltradas = filtrarResumenAgenciaPorCumplimiento(resumenAgencia)
            .sort((a, b) => b[1] - a[1]);

        const agenciasPermitidas = new Set(agenciasFiltradas.map(([agencia]) => agencia));
        const productosOrdenados = [...resumenProducto.entries()]
            .sort((a, b) => b[1] - a[1]);

        if (filtroCumplimientoAgencia !== 'todos') {
            const resumenProductoFiltrado = new Map();
            (ventas ?? []).forEach(item => {
                const agencia = (item.agencia_id ?? 'SIN AGENCIA').toString().trim() || 'SIN AGENCIA';
                if (!agenciasPermitidas.has(agencia)) return;
                const descripcion = (item.descripcion ?? 'SIN DESCRIPCIÓN').toString().trim() || 'SIN DESCRIPCIÓN';
                const monto = Number(item.monto ?? 0);
                resumenProductoFiltrado.set(descripcion, (resumenProductoFiltrado.get(descripcion) ?? 0) + monto);
            });
            productosOrdenados.splice(0, productosOrdenados.length, ...[...resumenProductoFiltrado.entries()].sort((a, b) => b[1] - a[1]));
            totalVendido = [...agenciasFiltradas].reduce((sum, [, total]) => sum + Number(total), 0);
            cardMontoTotal.textContent = `RD$ ${formatMoney(totalVendido)}`;
        }

        cardTotalAgencias.textContent = agenciasFiltradas.length.toLocaleString('es-DO');
        resumenAgenciaVisibleActual = new Map(agenciasFiltradas);

        const tbodyProductos = document.querySelector('#tableProductos tbody');
        productosOrdenados.forEach(([descripcion, total]) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${descripcion}</td>
                <td>${formatMoney(total)}</td>
            `;
            tbodyProductos.appendChild(tr);
        });

        const tbodyAgencias = document.querySelector('#tableAgencias tbody');
        agenciasFiltradas.forEach(([agencia, total]) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${agencia}</td>
                <td>${formatMoney(total)}</td>
                <td>${getCumplimientoBadge(total)}</td>
            `;
            tbodyAgencias.appendChild(tr);
        });

        dtProductos = $('#tableProductos').DataTable({
            destroy: true,
            responsive: true,
            language: {
                url: '/json/es-DO.json',
                search: 'Buscar:',
                lengthMenu: 'Mostrar _MENU_ registros',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                paginate: { first: 'Primera', last: 'Última', next: 'Siguiente', previous: 'Anterior' }
            },
            dom: 'Bfrtip',
            buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
            order: [[1, 'desc']],
        });

        dtAgencias = $('#tableAgencias').DataTable({
            destroy: true,
            responsive: true,
            language: {
                url: '/json/es-DO.json',
                search: 'Buscar:',
                lengthMenu: 'Mostrar _MENU_ registros',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                paginate: { first: 'Primera', last: 'Última', next: 'Siguiente', previous: 'Anterior' }
            },
            dom: 'Bfrtip',
            buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
            order: [[1, 'desc']],
        });

        updateFiltroLabel();

        return { resumenAgencia: resumenAgenciaVisibleActual };
    };

    const abrirModalAgencias = (resumenAgencia) => {
        modalAgencyEntries = [...resumenAgencia.entries()].sort((a, b) => b[1] - a[1]);
        modalAgencyFilter = 'todos';
        renderModalAgencias();

        const modal = new bootstrap.Modal(document.getElementById('modalAgencias'));
        modal.show();
    };

    const aplicarFiltroAgencia = (agencia) => {
        agenciaSeleccionada = agencia;
        const ventasFiltradas = (ventasFuente ?? []).filter(item => {
            return (item.agencia_id ?? '').toString().trim() === agencia.toString().trim();
        });

        if (dtProductos) { dtProductos.destroy(); dtProductos = null; }
        if (dtAgencias) { dtAgencias.destroy(); dtAgencias = null; }
        document.querySelector('#tableProductos tbody').innerHTML = '';
        document.querySelector('#tableAgencias tbody').innerHTML = '';

        renderResumen(ventasFiltradas);
        labelFecha.textContent = `Fecha: ${inputFecha.value} | Agencia: ${agencia}`;
        btnLimpiarAgencia.classList.remove('d-none');
    };

    const refrescarVistaActual = () => {
        if (!ventasFuente.length) return;

        if (dtProductos) { dtProductos.destroy(); dtProductos = null; }
        if (dtAgencias) { dtAgencias.destroy(); dtAgencias = null; }
        document.querySelector('#tableProductos tbody').innerHTML = '';
        document.querySelector('#tableAgencias tbody').innerHTML = '';

        const dataRender = agenciaSeleccionada
            ? ventasFuente.filter(item => (item.agencia_id ?? '').toString().trim() === agenciaSeleccionada.toString().trim())
            : ventasFuente;

        renderResumen(dataRender);
        if (agenciaSeleccionada) {
            labelFecha.textContent = `Fecha: ${inputFecha.value} | Agencia: ${agenciaSeleccionada}`;
            btnLimpiarAgencia.classList.remove('d-none');
        } else {
            labelFecha.textContent = 'Fecha: ' + inputFecha.value;
            btnLimpiarAgencia.classList.add('d-none');
        }
    };

    const isSuccessCode = (code) => {
        if (code === undefined || code === null) return false;
        const normalized = String(code).trim().toLowerCase();
        return normalized === '0' || normalized === '200' || normalized === 'success' || normalized === 'ok';
    };

    const getApiError = (data) => {
        if (data?.error) return data.error;
        if (data?.code !== undefined && !isSuccessCode(data.code)) {
            return data?.message || `Código inesperado: ${data.code}`;
        }
        return null;
    };

    // --- Generar Token ---
    document.getElementById('btnGenerarToken').addEventListener('click', function () {
        this.disabled = true;
        Swal.fire({
            title: 'Generando token...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => Swal.showLoading(),
        });
        fetch('/generar-token')
            .then(r => r.json())
            .then(data => {
                Swal.fire({ title: 'Listo', text: data.success ?? 'Token generado.', icon: 'success' });
            })
            .catch(err => {
                Swal.fire({ title: 'Error', text: err.message, icon: 'error' });
            })
            .finally(() => { document.getElementById('btnGenerarToken').disabled = false; });
    });

    // --- Generar Data (solo trae y muestra, no guarda) ---
    document.getElementById('btnGenerarData').addEventListener('click', function () {
        const fecha = inputFecha.value;
        if (!fecha) {
            Swal.fire({ title: 'Requerido', text: 'Selecciona una fecha primero.', icon: 'warning' });
            return;
        }

        this.disabled = true;
        Swal.fire({
            title: 'Generando data...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => Swal.showLoading(),
        });

        destroyTables();

        fetch(`/ventas-producto-lotobet?fecha=${fecha}`)
            .then(r => r.json())
            .then(data => {
                Swal.close();
                document.getElementById('btnGenerarData').disabled = false;

                const apiError = getApiError(data);
                if (apiError) {
                    Swal.fire({ title: 'Error', text: apiError, icon: 'error' });
                    return;
                }

                if (!Array.isArray(data.ventas)) {
                    Swal.fire({ title: 'Error', text: 'La API no devolvió un listado de ventas válido.', icon: 'error' });
                    return;
                }

                ventasFuente = data.ventas;
                const { resumenAgencia } = renderResumen(data.ventas);

                labelFecha.textContent = 'Fecha: ' + fecha;
                seccion.classList.remove('d-none');

                btnVerAgencias.onclick = () => {
                    if (!resumenAgencia || resumenAgencia.size === 0) {
                        Swal.fire({ title: 'Sin datos', text: 'No hay agencias con ventas para mostrar.', icon: 'info' });
                        return;
                    }
                    abrirModalAgencias(resumenAgencia);
                };

                Swal.fire({ title: 'Listo', text: 'Datos obtenidos correctamente.', icon: 'success', timer: 1500, showConfirmButton: false });
            })
            .catch(err => {
                Swal.close();
                document.getElementById('btnGenerarData').disabled = false;
                Swal.fire({ title: 'Error de red', text: err.message, icon: 'error' });
            });
    });

    // --- Consultar (usa datos ya guardados en BD) ---
    btnConsultar.addEventListener('click', function () {
        const fecha = inputFecha.value;
        if (!fecha) {
            Swal.fire({ title: 'Requerido', text: 'Selecciona una fecha para consultar.', icon: 'warning' });
            return;
        }

        btnConsultar.disabled = true;
        Swal.fire({
            title: 'Consultando...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => Swal.showLoading(),
        });

        destroyTables();

        fetch(`/ventas-producto-lotobet?fecha=${fecha}`)
            .then(r => r.json())
            .then(data => {
                Swal.close();
                btnConsultar.disabled = false;

                const apiError = getApiError(data);
                if (apiError) {
                    Swal.fire({ title: 'Error', text: apiError, icon: 'error' });
                    return;
                }

                if (!Array.isArray(data.ventas)) {
                    Swal.fire({ title: 'Error', text: 'La API no devolvió un listado de ventas válido.', icon: 'error' });
                    return;
                }

                ventasFuente = data.ventas;
                const { resumenAgencia } = renderResumen(data.ventas);

                labelFecha.textContent = 'Fecha: ' + fecha;
                seccion.classList.remove('d-none');

                btnVerAgencias.onclick = () => {
                    if (!resumenAgencia || resumenAgencia.size === 0) {
                        Swal.fire({ title: 'Sin datos', text: 'No hay agencias con ventas para mostrar.', icon: 'info' });
                        return;
                    }
                    abrirModalAgencias(resumenAgencia);
                };
            })
            .catch(err => {
                Swal.close();
                btnConsultar.disabled = false;
                Swal.fire({ title: 'Error de red', text: err.message, icon: 'error' });
            });
    });

    document.addEventListener('click', function (event) {
        const target = event.target;
        if (!target.classList.contains('btnFiltrarAgencia')) return;

        const agencia = target.getAttribute('data-agencia');
        if (!agencia) return;

        const modalInstance = bootstrap.Modal.getInstance(document.getElementById('modalAgencias'));
        if (modalInstance) modalInstance.hide();

        aplicarFiltroAgencia(agencia);
    });

    btnLimpiarAgencia.addEventListener('click', function () {
        if (!ventasFuente.length) return;

        if (dtProductos) { dtProductos.destroy(); dtProductos = null; }
        if (dtAgencias) { dtAgencias.destroy(); dtAgencias = null; }
        document.querySelector('#tableProductos tbody').innerHTML = '';
        document.querySelector('#tableAgencias tbody').innerHTML = '';

        renderResumen(ventasFuente);
        labelFecha.textContent = 'Fecha: ' + inputFecha.value;
        agenciaSeleccionada = null;
        btnLimpiarAgencia.classList.add('d-none');
    });

    btnConfigVentasMinimo.addEventListener('click', function () {
        document.getElementById('inputVentasMinimo').value = ventasMinimoConfig;
        const modal = new bootstrap.Modal(document.getElementById('modalVentasMinimo'));
        modal.show();
    });

    document.getElementById('btnGuardarVentasMinimo').addEventListener('click', function () {
        const value = Number(document.getElementById('inputVentasMinimo').value ?? 0);
        ventasMinimoConfig = value >= 0 ? value : 0;
        labelMinimoVentas.textContent = `Mínimo: RD$ ${formatMoney(ventasMinimoConfig)}`;

        const modalInstance = bootstrap.Modal.getInstance(document.getElementById('modalVentasMinimo'));
        if (modalInstance) modalInstance.hide();

        refrescarVistaActual();

        if (modalAgencyEntries.length) {
            renderModalAgencias();
        }
    });

    btnFiltroAgenciasTodos.addEventListener('click', function () {
        filtroCumplimientoAgencia = 'todos';
        refrescarVistaActual();
    });

    btnFiltroAgenciasCumplen.addEventListener('click', function () {
        filtroCumplimientoAgencia = 'cumplen';
        refrescarVistaActual();
    });

    btnFiltroAgenciasNoCumplen.addEventListener('click', function () {
        filtroCumplimientoAgencia = 'no_cumplen';
        refrescarVistaActual();
    });

    btnModalAgenciasTodos.addEventListener('click', function () {
        modalAgencyFilter = 'todos';
        renderModalAgencias();
    });

    btnModalAgenciasCumplen.addEventListener('click', function () {
        modalAgencyFilter = 'cumplen';
        renderModalAgencias();
    });

    btnModalAgenciasNoCumplen.addEventListener('click', function () {
        modalAgencyFilter = 'no_cumplen';
        renderModalAgencias();
    });

    labelMinimoVentas.textContent = `Mínimo: RD$ ${formatMoney(ventasMinimoConfig)}`;
    updateFiltroLabel();
    updateModalFiltroLabel();
});
</script>
@endsection
