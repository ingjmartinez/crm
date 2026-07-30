@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Gastos por Agencia de Incentivo</h4>
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('contabilidad.index') }}">Contabilidad</a></li>
                                <li class="breadcrumb-item active">Gastos por Agencia de Incentivo</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-sm-6 col-xl-3">
                        <div class="card mb-0">
                            <div class="card-body">
                                <p class="text-muted mb-1">Agencias</p>
                                <h4 class="mb-0" id="totalAgencias">0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="card mb-0">
                            <div class="card-body">
                                <p class="text-muted mb-1">Agentes con incentivo</p>
                                <h4 class="mb-0" id="totalAgentes">0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="card mb-0">
                            <div class="card-body">
                                <p class="text-muted mb-1">Ventas distribuidas</p>
                                <h4 class="mb-0" id="totalVentas">$0.00</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="card mb-0 border-success">
                            <div class="card-body">
                                <p class="text-muted mb-1">Gasto de incentivo</p>
                                <h4 class="mb-0 text-success" id="totalIncentivo">$0.00</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <form id="formReporte" class="row g-3 align-items-end">
                            <div class="col-md-2">
                                <label for="fechaIni" class="form-label">Fecha inicio</label>
                                <input type="date" id="fechaIni" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <label for="fechaFin" class="form-label">Fecha fin</label>
                                <input type="date" id="fechaFin" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <label for="sistema" class="form-label">Sistema</label>
                                <select id="sistema" class="form-select">
                                    <option value="Todos">Todos</option>
                                    <option value="Lotobet">Lotobet</option>
                                    <option value="Lotonet">Lotonet</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="minDias" class="form-label">Mín. días de venta</label>
                                <input type="number" id="minDias" class="form-control" value="1" min="1">
                            </div>
                            <div class="col-md-2">
                                <label for="tipoPago" class="form-label">Tipo de pago</label>
                                <select id="tipoPago" class="form-select">
                                    <option value="tramos_60">Pagos a 60</option>
                                    <option value="tramos_70">Pagos a 70</option>
                                    <option value="tramos_80">Pagos a 80</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-grid">
                                <button type="submit" class="btn btn-primary" id="btnGenerar">
                                    <i class="ri-file-chart-line me-1"></i>Generar reporte
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <div class="text-muted" id="rangoEvaluado">Genere el reporte para consultar la distribución.</div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-dark" id="btnDescargarExcel" disabled>
                                    <i class="ri-file-excel-2-line me-1"></i>Descargar Excel
                                </button>
                                <button type="button" class="btn btn-warning" id="btnFaltantes" disabled>
                                    <i class="ri-user-unfollow-line me-1"></i>Faltantes
                                </button>
                                <button type="button" class="btn btn-success" id="btnDesvinculados" disabled>
                                    <i class="ri-user-forbid-line me-1"></i>Desvinculados
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table id="tablaReporte" class="table table-bordered table-striped align-middle w-100">
                                <thead>
                                    <tr>
                                        <th>Terminal</th>
                                        <th>Agencia</th>
                                        <th>Empresa</th>
                                        <th>Cédula</th>
                                        <th>IdEmpleado</th>
                                        <th>Agente</th>
                                        <th class="text-end">Venta total cédula</th>
                                        <th class="text-end">Venta agencia</th>
                                        <th class="text-end">Participación</th>
                                        <th class="text-end">Incentivo total</th>
                                        <th class="text-end">Gasto agencia</th>
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

    <div class="modal fade" id="modalFaltantes" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Faltantes encontrados</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning" id="resumenFaltantes"></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead><tr><th>Cédula</th><th>Nombre</th><th>Cantidad</th><th>Monto</th></tr></thead>
                            <tbody id="detalleFaltantes"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-warning" id="btnAplicarFaltantes">Aplicar faltantes</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalDesvinculados" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Usuarios desvinculados</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success" id="resumenDesvinculados"></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead><tr><th>Cédula</th><th>IdEmpleado</th><th>Nombre</th><th>Estatus</th><th>Fecha salida</th></tr></thead>
                            <tbody id="detalleDesvinculados"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-success" id="btnAplicarDesvinculados">Aplicar desvinculados</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        const csrfToken = @json(csrf_token());
        const dataUrl = @json(route('contabilidad.reportes.gastos-incentivo-agencia.data'));
        const faltantesUrl = @json(url('/incentivos/reporte-nuevo-incentivo-v5/faltantes'));
        const desvinculadosUrl = @json(url('/incentivos/reporte-nuevo-incentivo-v5/desvinculados'));
        let sourceRows = [];
        let displayedRows = [];
        let faltantesActuales = new Set();
        let desvinculadosCedulasActuales = new Set();
        let desvinculadosIdsActuales = new Set();
        let cedulasExcluidas = new Set();
        let empleadoIdsExcluidos = new Set();
        let table = null;

        const buildRanges = (percent, pagos) => [
            { desde: 100001, hasta: 250000, pago: pagos[0], tipo: 'fijo' },
            { desde: 250001, hasta: 400000, pago: pagos[1], tipo: 'fijo' },
            { desde: 400001, hasta: 550000, pago: pagos[2], tipo: 'fijo' },
            { desde: 550001, hasta: 700000, pago: pagos[3], tipo: 'fijo' },
            { desde: 700001, hasta: 850000, pago: pagos[4], tipo: 'fijo' },
            { desde: 850001, hasta: 1000000, pago: pagos[5], tipo: 'fijo' },
            { desde: 1000001, hasta: 5000000, pago: percent, tipo: 'porcentaje' },
            { desde: 5000001, hasta: null, pago: percent, tipo: 'porcentaje' },
        ];
        const payoutRanges = {
            tramos_60: buildRanges(1, [1000, 2000, 4000, 6000, 8000, 9000]),
            tramos_70: buildRanges(0.75, [750, 1500, 3000, 4500, 6000, 6750]),
            tramos_80: buildRanges(0.5, [500, 1000, 2000, 3000, 4000, 4500]),
        };

        document.addEventListener('DOMContentLoaded', function () {
            const today = new Date();
            document.getElementById('fechaIni').value = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().slice(0, 10);
            document.getElementById('fechaFin').value = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().slice(0, 10);

            document.getElementById('formReporte').addEventListener('submit', generarReporte);
            document.getElementById('btnDescargarExcel').addEventListener('click', descargarExcel);
            document.getElementById('btnFaltantes').addEventListener('click', consultarFaltantes);
            document.getElementById('btnDesvinculados').addEventListener('click', consultarDesvinculados);
            document.getElementById('btnAplicarFaltantes').addEventListener('click', aplicarFaltantes);
            document.getElementById('btnAplicarDesvinculados').addEventListener('click', aplicarDesvinculados);
        });

        async function generarReporte(event) {
            event.preventDefault();
            const button = document.getElementById('btnGenerar');
            const tipoPago = document.getElementById('tipoPago').value;
            const params = new URLSearchParams({
                fecha_ini: document.getElementById('fechaIni').value,
                fecha_fin: document.getElementById('fechaFin').value,
                sistema: document.getElementById('sistema').value,
                min_dias_venta: document.getElementById('minDias').value,
                tipo_pago: tipoPago,
                rangos_pago: JSON.stringify(payoutRanges[tipoPago]),
            });

            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generando...';
            loading('Generando reporte...');

            try {
                const response = await fetch(`${dataUrl}?${params.toString()}`, { headers: { Accept: 'application/json' } });
                const payload = await parseJson(response);
                sourceRows = Array.isArray(payload.data) ? payload.data : [];
                cedulasExcluidas = new Set();
                empleadoIdsExcluidos = new Set();
                aplicarFiltros();
                document.getElementById('rangoEvaluado').textContent =
                    `Período: ${payload.meta.fecha_ini} al ${payload.meta.fecha_fin} · ${payload.meta.sistema}`;
                document.getElementById('btnDescargarExcel').disabled = sourceRows.length === 0;
                document.getElementById('btnFaltantes').disabled = sourceRows.length === 0;
                document.getElementById('btnDesvinculados').disabled = sourceRows.length === 0;
                Swal.fire({
                    title: 'Reporte generado',
                    text: `${Number(payload.meta.total_agentes || 0).toLocaleString('es-DO')} agentes fueron distribuidos en ${Number(payload.meta.total_agencias || 0).toLocaleString('es-DO')} agencias.`,
                    icon: 'success',
                });
            } catch (error) {
                Swal.fire({ title: 'No se pudo generar', text: error.message, icon: 'error' });
            } finally {
                button.disabled = false;
                button.innerHTML = '<i class="ri-file-chart-line me-1"></i>Generar reporte';
            }
        }

        function aplicarFiltros() {
            displayedRows = sourceRows.filter(row =>
                !cedulasExcluidas.has(key(row.cedula)) &&
                !empleadoIdsExcluidos.has(String(row.empleadoid || '').trim())
            );
            renderTable(displayedRows);
            updateTotals(displayedRows);
        }

        function renderTable(rows) {
            if (table) {
                table.destroy();
            }

            table = $('#tablaReporte').DataTable({
                data: rows,
                columns: [
                    { data: 'terminal', render: textRender },
                    { data: 'agencia', render: textRender },
                    { data: 'empresa', render: textRender },
                    { data: 'cedula', render: textRender },
                    { data: 'empleadoid', defaultContent: '-', render: textRender },
                    { data: 'nombre', render: textRender },
                    { data: 'ventas_total_cedula', className: 'text-end', render: moneyRender },
                    { data: 'ventas_agencia', className: 'text-end', render: moneyRender },
                    { data: 'participacion', className: 'text-end', render: percentRender },
                    { data: 'incentivo_total_agente', className: 'text-end', render: moneyRender },
                    { data: 'incentivo_agencia', className: 'text-end fw-semibold', render: integerMoneyRender },
                ],
                responsive: true,
                pageLength: 25,
                order: [[1, 'asc'], [5, 'asc']],
                dom: 'Bfrtip',
                buttons: [
                    'copy',
                    'csv',
                    {
                        extend: 'excelHtml5',
                        title: 'Gastos por Agencia de Incentivo',
                        filename: 'gastos_por_agencia_de_incentivo',
                        text: 'Excel',
                    },
                    'print',
                ],
                language: {
                    search: 'Buscar:',
                    lengthMenu: 'Mostrar _MENU_ registros',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                    infoEmpty: 'No hay datos',
                    zeroRecords: 'No hay resultados',
                    paginate: { next: 'Siguiente', previous: 'Anterior' },
                },
            });
        }

        function descargarExcel() {
            if (!table || !displayedRows.length) {
                Swal.fire({ title: 'Sin datos', text: 'Primero debes generar el reporte.', icon: 'warning' });
                return;
            }

            table.button('.buttons-excel').trigger();
        }

        function updateTotals(rows) {
            const agencies = new Set(rows.map(row => String(row.terminal)));
            const agents = new Set(rows.map(row => key(row.cedula)));
            document.getElementById('totalAgencias').textContent = agencies.size.toLocaleString('es-DO');
            document.getElementById('totalAgentes').textContent = agents.size.toLocaleString('es-DO');
            document.getElementById('totalVentas').textContent = money(rows.reduce((sum, row) => sum + Number(row.ventas_agencia || 0), 0));
            document.getElementById('totalIncentivo').textContent = money(rows.reduce((sum, row) => sum + Number(row.incentivo_agencia || 0), 0));
        }

        async function consultarFaltantes() {
            const cedulas = unique(displayedRows.map(row => key(row.cedula)));
            if (!cedulas.length) {
                return;
            }

            try {
                loading('Consultando faltantes...');
                const payload = await postJson(faltantesUrl, {
                    cedulas,
                    fecha_ini: document.getElementById('fechaIni').value,
                    fecha_fin: document.getElementById('fechaFin').value,
                });
                const rows = Array.isArray(payload.data) ? payload.data : [];
                faltantesActuales = new Set(rows.map(row => key(row.cedula)));
                document.getElementById('resumenFaltantes').textContent =
                    `${faltantesActuales.size} agentes tienen ${Number(payload.total_faltantes || 0)} faltantes por ${money(payload.total_monto || 0)}.`;
                document.getElementById('detalleFaltantes').innerHTML = rows.map(row => `
                    <tr><td>${escapeHtml(row.cedula)}</td><td>${escapeHtml(row.nombre)}</td>
                    <td>${Number(row.cantidad_faltantes || 0).toLocaleString('es-DO')}</td><td>${money(row.monto)}</td></tr>
                `).join('');
                Swal.close();
                new bootstrap.Modal(document.getElementById('modalFaltantes')).show();
            } catch (error) {
                Swal.fire({ title: 'Error', text: error.message, icon: 'error' });
            }
        }

        async function consultarDesvinculados() {
            const cedulas = unique(displayedRows.map(row => key(row.cedula)));
            const empleadoids = unique(displayedRows.map(row => String(row.empleadoid || '').trim()).filter(Boolean));
            if (!cedulas.length && !empleadoids.length) {
                return;
            }

            try {
                loading('Consultando desvinculados...');
                const payload = await postJson(desvinculadosUrl, { cedulas, empleadoids });
                const rows = Array.isArray(payload.data) ? payload.data : [];
                desvinculadosCedulasActuales = new Set(rows.map(row => key(row.cedula)));
                desvinculadosIdsActuales = new Set(rows.map(row => String(row.empleadoid || '').trim()).filter(Boolean));
                document.getElementById('resumenDesvinculados').textContent =
                    `${Number(payload.total_desvinculados || 0)} usuarios desvinculados; ${Number(payload.total_desactivados || 0)} desactivados.`;
                document.getElementById('detalleDesvinculados').innerHTML = rows.map(row => `
                    <tr><td>${escapeHtml(row.cedula)}</td><td>${escapeHtml(row.empleadoid || '-')}</td>
                    <td>${escapeHtml(row.nombre)}</td><td>${escapeHtml(row.estatus)}</td>
                    <td>${escapeHtml(row.fecha_salida || '-')}</td></tr>
                `).join('');
                Swal.close();
                new bootstrap.Modal(document.getElementById('modalDesvinculados')).show();
            } catch (error) {
                Swal.fire({ title: 'Error', text: error.message, icon: 'error' });
            }
        }

        function aplicarFaltantes() {
            faltantesActuales.forEach(cedula => cedulasExcluidas.add(cedula));
            bootstrap.Modal.getInstance(document.getElementById('modalFaltantes'))?.hide();
            aplicarFiltros();
            Swal.fire({ title: 'Faltantes aplicados', text: 'El gasto fue recalculado sin las cédulas con faltantes.', icon: 'success' });
        }

        function aplicarDesvinculados() {
            desvinculadosCedulasActuales.forEach(cedula => cedulasExcluidas.add(cedula));
            desvinculadosIdsActuales.forEach(id => empleadoIdsExcluidos.add(id));
            bootstrap.Modal.getInstance(document.getElementById('modalDesvinculados'))?.hide();
            aplicarFiltros();
            Swal.fire({ title: 'Desvinculados aplicados', text: 'El gasto fue recalculado sin los usuarios desvinculados.', icon: 'success' });
        }

        async function postJson(url, body) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            });

            return parseJson(response);
        }

        async function parseJson(response) {
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const validation = payload.errors ? Object.values(payload.errors).flat().join(' ') : '';
                throw new Error(validation || payload.message || 'Ocurrió un error procesando el reporte.');
            }

            return payload;
        }

        function loading(title) {
            Swal.fire({ title, allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
        }

        function money(value) {
            return Number(value || 0).toLocaleString('en-US', { style: 'currency', currency: 'USD' });
        }

        function moneyRender(data, type) {
            return type === 'display' ? money(data) : Number(data || 0);
        }

        function integerMoneyRender(data, type) {
            const value = Math.round(Number(data || 0));
            return type === 'display' ? `$${value.toLocaleString('en-US')}` : value;
        }

        function percentRender(data, type) {
            return type === 'display' ? `${Number(data || 0).toFixed(2)}%` : Number(data || 0);
        }

        function textRender(data, type) {
            return type === 'display' ? escapeHtml(data) : String(data ?? '');
        }

        function key(value) {
            const normalized = String(value || '').replace(/\D+/g, '').replace(/^0+/, '');
            return normalized || '0';
        }

        function unique(values) {
            return [...new Set(values.filter(Boolean))];
        }

        function escapeHtml(value) {
            const element = document.createElement('div');
            element.textContent = String(value ?? '');
            return element.innerHTML;
        }
    </script>
@endsection
