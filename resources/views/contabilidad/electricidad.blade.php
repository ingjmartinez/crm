@extends('app')

@section('content')
    <style>
        .excel-head {
            background-color: #9ea3a8;
            color: #111;
            font-weight: 700;
            text-transform: uppercase;
        }

        .excel-band {
            background: #fff200;
            color: #111;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            text-align: center;
            padding: 0.45rem;
            border: 1px solid #dee2e6;
            margin-bottom: 0.75rem;
        }
    </style>
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
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Seguimiento por dia</h5>
                                <div class="d-flex gap-2">
                                    <input type="date" class="form-control form-control-sm" id="filtroSegDesde" style="max-width: 170px;" placeholder="Desde">
                                    <input type="date" class="form-control form-control-sm" id="filtroSegHasta" style="max-width: 170px;" placeholder="Hasta">
                                    <button type="button" class="btn btn-sm btn-info" id="btnFiltrarSeguimiento">Filtrar</button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-12 col-md-5">
                                        <label class="form-label">Buscar por codigo o nombre de agencia</label>
                                        <input type="text" class="form-control" id="buscarSegAgencia" placeholder="Ej: AG001 o Los Mina">
                                    </div>
                                </div>

                                <form id="formSeguimientoDia" class="row g-2 mb-3">
                                    <div class="col-md-2">
                                        <label class="form-label">Fecha de solicitud *</label>
                                        <input type="date" class="form-control" id="segFechaSolicitud" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Distribuidora *</label>
                                        <input type="text" class="form-control" id="segDistribuidora" maxlength="120" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">NIC *</label>
                                        <input type="text" class="form-control" id="segNic" maxlength="80" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Agencia *</label>
                                        <input type="text" class="form-control" id="segAgencia" maxlength="150" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Ruta *</label>
                                        <input type="text" class="form-control" id="segRuta" maxlength="150" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Observaciones</label>
                                        <input type="text" class="form-control" id="segObservaciones" maxlength="1000">
                                    </div>
                                    <div class="col-12 d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary">Guardar seguimiento</button>
                                    </div>
                                </form>

                                <div class="table-responsive">
                                    <table id="tablaSeguimientoDia" class="table table-bordered table-striped align-middle mb-0" style="width:100%">
                                        <thead>
                                            <tr class="excel-head">
                                                <th>Fecha de solicitud</th>
                                                <th>Distribuidoras</th>
                                                <th>NIC</th>
                                                <th>Agencia</th>
                                                <th>Ruta</th>
                                                <th>Observaciones</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Reporte de Averias por dia</h5>
                                <div class="d-flex gap-2">
                                    <input type="date" class="form-control form-control-sm" id="filtroAveDesde" style="max-width: 170px;" placeholder="Desde">
                                    <input type="date" class="form-control form-control-sm" id="filtroAveHasta" style="max-width: 170px;" placeholder="Hasta">
                                    <button type="button" class="btn btn-sm btn-info" id="btnFiltrarAverias">Filtrar</button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="excel-band">REPORTES DE AVERIA X DIAS</div>
                                <div class="row mb-3">
                                    <div class="col-12 col-md-5">
                                        <label class="form-label">Buscar por codigo o nombre de agencia</label>
                                        <input type="text" class="form-control" id="buscarAveAgencia" placeholder="Ej: AG001 o Los Mina">
                                    </div>
                                </div>

                                <form id="formAveriasDia" class="row g-2 mb-3">
                                    <div class="col-md-2">
                                        <label class="form-label">Fecha del reporte *</label>
                                        <input type="date" class="form-control" id="aveFechaReporte" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Reporte *</label>
                                        <input type="text" class="form-control" id="aveReporte" maxlength="120" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Distribuidoras *</label>
                                        <input type="text" class="form-control" id="aveDistribuidora" maxlength="120" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">NIC *</label>
                                        <input type="text" class="form-control" id="aveNic" maxlength="80" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Agencia *</label>
                                        <input type="text" class="form-control" id="aveAgencia" maxlength="150" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Ruta *</label>
                                        <input type="text" class="form-control" id="aveRuta" maxlength="150" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Coordinadores</label>
                                        <input type="text" class="form-control" id="aveCoordinadores" maxlength="180">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Agente de venta AM</label>
                                        <input type="text" class="form-control" id="aveAgenteAm" maxlength="180">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Agente de venta PM</label>
                                        <input type="text" class="form-control" id="aveAgentePm" maxlength="180">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Observaciones</label>
                                        <input type="text" class="form-control" id="aveObservaciones" maxlength="1000">
                                    </div>
                                    <div class="col-12 d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary">Guardar averia</button>
                                    </div>
                                </form>

                                <div class="table-responsive">
                                    <table id="tablaAveriasDia" class="table table-bordered table-striped align-middle mb-0" style="width:100%">
                                        <thead>
                                            <tr class="excel-head">
                                                <th>Fecha del reporte</th>
                                                <th>Reporte</th>
                                                <th>Distribuidoras</th>
                                                <th>NIC</th>
                                                <th>Agencia</th>
                                                <th>Ruta</th>
                                                <th>Coordinadores</th>
                                                <th>Agente de venta AM</th>
                                                <th>Agente de venta PM</th>
                                                <th>Observaciones</th>
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
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const csrfToken = '{{ csrf_token() }}';

            const segDesde = document.getElementById('filtroSegDesde');
            const segHasta = document.getElementById('filtroSegHasta');
            const btnFiltrarSeguimiento = document.getElementById('btnFiltrarSeguimiento');
            const formSeguimiento = document.getElementById('formSeguimientoDia');
            const tablaSeguimientoBody = document.querySelector('#tablaSeguimientoDia tbody');
            const buscarSegAgencia = document.getElementById('buscarSegAgencia');

            const aveDesde = document.getElementById('filtroAveDesde');
            const aveHasta = document.getElementById('filtroAveHasta');
            const btnFiltrarAverias = document.getElementById('btnFiltrarAverias');
            const formAverias = document.getElementById('formAveriasDia');
            const tablaAveriasBody = document.querySelector('#tablaAveriasDia tbody');
            const buscarAveAgencia = document.getElementById('buscarAveAgencia');

            let dtSeguimiento = null;
            let dtAverias = null;

            function initDataTable(selector) {
                if ($.fn.DataTable.isDataTable(selector)) {
                    $(selector).DataTable().destroy();
                }

                return $(selector).DataTable({
                    responsive: true,
                    scrollX: true,
                    order: [[0, 'desc']],
                    dom: 'Bfrtip',
                    buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
                });
            }

            function getQuery(fechaDesde, fechaHasta) {
                const params = new URLSearchParams();
                if ((fechaDesde || '').trim() !== '') {
                    params.set('fecha_desde', fechaDesde.trim());
                }
                if ((fechaHasta || '').trim() !== '') {
                    params.set('fecha_hasta', fechaHasta.trim());
                }
                const query = params.toString();
                return query !== '' ? ('?' + query) : '';
            }

            async function cargarSeguimientoDia() {
                const url = '/contabilidad/electricidad/seguimiento-dia/data' + getQuery(segDesde.value, segHasta.value);

                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('No se pudo cargar seguimiento por dia.');
                }

                const payload = await response.json();
                tablaSeguimientoBody.innerHTML = '';

                (payload.data || []).forEach(function (item) {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${item.fecha_solicitud || ''}</td>
                        <td>${item.distribuidora || ''}</td>
                        <td>${item.nic || ''}</td>
                        <td>${item.agencia || ''}</td>
                        <td>${item.ruta || ''}</td>
                        <td>${item.observaciones || ''}</td>
                    `;

                    tablaSeguimientoBody.appendChild(row);
                });

                dtSeguimiento = initDataTable('#tablaSeguimientoDia');

                const termino = (buscarSegAgencia?.value || '').trim();
                dtSeguimiento.column(3).search(termino).draw();
            }

            async function cargarAveriasDia() {
                const url = '/contabilidad/electricidad/averias-dia/data' + getQuery(aveDesde.value, aveHasta.value);

                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('No se pudo cargar reporte de averias por dia.');
                }

                const payload = await response.json();
                tablaAveriasBody.innerHTML = '';

                (payload.data || []).forEach(function (item) {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${item.fecha_reporte || ''}</td>
                        <td>${item.reporte || ''}</td>
                        <td>${item.distribuidora || ''}</td>
                        <td>${item.nic || ''}</td>
                        <td>${item.agencia || ''}</td>
                        <td>${item.ruta || ''}</td>
                        <td>${item.coordinadores || ''}</td>
                        <td>${item.agente_venta_am || ''}</td>
                        <td>${item.agente_venta_pm || ''}</td>
                        <td>${item.observaciones || ''}</td>
                    `;

                    tablaAveriasBody.appendChild(row);
                });

                dtAverias = initDataTable('#tablaAveriasDia');

                const termino = (buscarAveAgencia?.value || '').trim();
                dtAverias.column(4).search(termino).draw();
            }

            formSeguimiento?.addEventListener('submit', async function (event) {
                event.preventDefault();

                const payload = {
                    fecha_solicitud: document.getElementById('segFechaSolicitud').value,
                    distribuidora: document.getElementById('segDistribuidora').value.trim(),
                    nic: document.getElementById('segNic').value.trim(),
                    agencia: document.getElementById('segAgencia').value.trim(),
                    ruta: document.getElementById('segRuta').value.trim(),
                    observaciones: document.getElementById('segObservaciones').value.trim(),
                };

                if (!payload.fecha_solicitud || !payload.distribuidora || !payload.nic || !payload.agencia || !payload.ruta) {
                    alert('Completa todos los campos obligatorios de seguimiento.');
                    return;
                }

                const response = await fetch('/contabilidad/electricidad/seguimiento-dia', {
                    method: 'POST',
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
                    throw new Error(firstValidation || json.message || 'No se pudo guardar el seguimiento.');
                }

                formSeguimiento.reset();
                await cargarSeguimientoDia();
            });

            formAverias?.addEventListener('submit', async function (event) {
                event.preventDefault();

                const payload = {
                    fecha_reporte: document.getElementById('aveFechaReporte').value,
                    reporte: document.getElementById('aveReporte').value.trim(),
                    distribuidora: document.getElementById('aveDistribuidora').value.trim(),
                    nic: document.getElementById('aveNic').value.trim(),
                    agencia: document.getElementById('aveAgencia').value.trim(),
                    ruta: document.getElementById('aveRuta').value.trim(),
                    coordinadores: document.getElementById('aveCoordinadores').value.trim(),
                    agente_venta_am: document.getElementById('aveAgenteAm').value.trim(),
                    agente_venta_pm: document.getElementById('aveAgentePm').value.trim(),
                    observaciones: document.getElementById('aveObservaciones').value.trim(),
                };

                if (!payload.fecha_reporte || !payload.reporte || !payload.distribuidora || !payload.nic || !payload.agencia || !payload.ruta) {
                    alert('Completa todos los campos obligatorios de averias.');
                    return;
                }

                const response = await fetch('/contabilidad/electricidad/averias-dia', {
                    method: 'POST',
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
                    throw new Error(firstValidation || json.message || 'No se pudo guardar el reporte de averia.');
                }

                formAverias.reset();
                await cargarAveriasDia();
            });

            btnFiltrarSeguimiento?.addEventListener('click', function () {
                cargarSeguimientoDia().catch(function (error) {
                    alert(error.message || 'No se pudo filtrar seguimiento.');
                });
            });

            btnFiltrarAverias?.addEventListener('click', function () {
                cargarAveriasDia().catch(function (error) {
                    alert(error.message || 'No se pudo filtrar averias.');
                });
            });

            buscarSegAgencia?.addEventListener('input', function () {
                if (!dtSeguimiento) {
                    return;
                }

                dtSeguimiento.column(3).search((this.value || '').trim()).draw();
            });

            buscarAveAgencia?.addEventListener('input', function () {
                if (!dtAverias) {
                    return;
                }

                dtAverias.column(4).search((this.value || '').trim()).draw();
            });
        });
    </script>
@endsection
