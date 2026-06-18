@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Gestion de agencias</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('reportes.index') }}">Reportes</a></li>
                                    <li class="breadcrumb-item active">Gestion de agencias</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                @if (isset($errors) && $errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (!empty($errores))
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errores as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (!empty($resumen))
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <div>
                                        <h5 class="card-title mb-1">Consulta por agencia</h5>
                                        <p class="text-muted mb-0">Busca por terminal o nombre de agencia usando la data cargada.</p>
                                    </div>
                                    <div class="col-xl-4 col-lg-5 col-md-6">
                                        <input type="search" class="form-control" id="consultaAgenciaInput" list="consultaAgenciaOptions" placeholder="Buscar agencia o terminal">
                                        <datalist id="consultaAgenciaOptions"></datalist>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                        <div>
                                            <p class="text-muted mb-1">Agencia seleccionada</p>
                                            <h5 class="mb-0" id="consultaAgenciaNombre">Selecciona una agencia</h5>
                                        </div>
                                        <span class="badge bg-light text-dark" id="consultaAgenciaTerminal">Terminal</span>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-xl-4 col-md-6">
                                            <div class="border rounded p-3 h-100">
                                                <p class="text-muted mb-1">Tradicionales</p>
                                                <h3 class="mb-1" id="consultaTradicionalTotal">0.00</h3>
                                                <small class="text-muted" id="consultaTradicionalUltima">Ult trans N/D</small>
                                            </div>
                                        </div>
                                        <div class="col-xl-4 col-md-6">
                                            <div class="border rounded p-3 h-100">
                                                <p class="text-muted mb-1">No tradicionales</p>
                                                <h3 class="mb-1" id="consultaNoTradicionalTotal">0.00</h3>
                                                <small class="text-muted" id="consultaNoTradicionalUltima">Ult trans N/D</small>
                                            </div>
                                        </div>
                                        <div class="col-xl-4 col-md-12">
                                            <div class="border rounded p-3 h-100">
                                                <p class="text-muted mb-1">Total</p>
                                                <h3 class="mb-1" id="consultaTotalAgencia">0.00</h3>
                                                <small class="text-muted">Tradicional + No tradicional</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div>
                                    <h5 class="card-title mb-1">Carga y limpieza de agencias</h5>
                                    <p class="text-muted mb-0">Se conservaran solo filas con estatus Validos y las columnas necesarias. Puedes cargar XLSX o CSV.</p>
                                </div>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('reportes.gestion-agencias.procesar') }}" enctype="multipart/form-data" class="row g-3 align-items-end" id="gestionAgenciasForm">
                                    @csrf
                                    <div class="col-xl-5 col-lg-6">
                                        <label for="tradicional" class="form-label">Tradicional</label>
                                        <input type="file" class="form-control" id="tradicional" name="tradicional" accept=".xlsx,.csv,.txt,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain" required>
                                    </div>
                                    <div class="col-xl-5 col-lg-6">
                                        <label for="no_tradicional" class="form-label">No Tradicional</label>
                                        <input type="file" class="form-control" id="no_tradicional" name="no_tradicional" accept=".xlsx,.csv,.txt,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain" required>
                                    </div>
                                    <div class="col-xl-2 col-lg-12">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="ri-filter-3-line align-bottom me-1"></i>
                                            Limpiar
                                        </button>
                                    </div>
                                </form>

                                @if (!empty($archivos))
                                    <div class="alert alert-info mt-3 mb-0">
                                        <div><strong>Tradicional:</strong> {{ $archivos['tradicional'] ?? '-' }}</div>
                                        <div><strong>No Tradicional:</strong> {{ $archivos['no_tradicional'] ?? '-' }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                @if (!empty($resumen))
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body">
                                    <p class="text-muted mb-1">Filas cargadas</p>
                                    <h4 class="mb-0">{{ number_format($resumen['total_cargadas'] ?? 0) }}</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body">
                                    <p class="text-muted mb-1">Agencias con ventas</p>
                                    <h4 class="mb-0 text-success">{{ number_format($resumen['total_validas'] ?? 0) }}</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card cursor-pointer" id="cardAgenciasSinVentaGestion" role="button" tabindex="0" aria-label="Ver agencias sin ventas">
                                <div class="card-body">
                                    <p class="text-muted mb-1">Agencias sin ventas <i class="ri-search-eye-line align-middle ms-1"></i></p>
                                    <h4 class="mb-0 text-danger">{{ number_format($resumen['total_eliminadas'] ?? 0) }}</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body">
                                    <p class="text-muted mb-1">Total apostado</p>
                                    <h4 class="mb-0">{{ number_format($resumen['total_apostado'] ?? 0, 2) }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div>
                                    <h5 class="card-title mb-1">Datos limpios</h5>
                                    @if (!empty($resumen))
                                        <p class="text-muted mb-0">
                                            Tradicional: {{ number_format($resumen['tradicional_validas'] ?? 0) }} |
                                            No Tradicional: {{ number_format($resumen['no_tradicional_validas'] ?? 0) }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle w-100" id="table-gestion-agencias">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Tipo</th>
                                                <th>Fecha</th>
                                                <th>Agencia</th>
                                                <th>Terminal</th>
                                                <th>Usr. Venta</th>
                                                <th>Estatus</th>
                                                <th class="text-end">Total Apostado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @if (empty($resumen))
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted">Carga ambos archivos XLSX o CSV para ver la data limpia.</td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalAgenciasSinVentaGestion" tabindex="-1" aria-labelledby="modalAgenciasSinVentaGestionLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header d-flex justify-content-between align-items-center">
                    <h5 class="modal-title" id="modalAgenciasSinVentaGestionLabel">Agencias sin ventas</h5>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-success btn-sm" id="btnDescargarAgenciasSinVentaGestionExcel">
                            <i class="ri-file-excel-2-line me-1"></i>Descargar Excel
                        </button>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table id="tableModalAgenciasSinVentaGestion" class="table table-striped table-bordered w-100">
                            <thead>
                                <tr>
                                    <th>Agencia</th>
                                    <th>Terminal</th>
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
@endsection

@section('script')
    <script>
        window.gestionAgenciasData = {
            agenciasSinVentas: @json($agenciasSinVentas ?? []),
            ventasPorAgencia: @json($ventasPorAgencia ?? []),
            tieneResultado: @json(!empty($resumen)),
            dataUrl: @json(route('reportes.gestion-agencias.data')),
        };
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const table = $('#table-gestion-agencias');
            const form = document.getElementById('gestionAgenciasForm');
            const agenciasSinVentas = Array.isArray(window.gestionAgenciasData?.agenciasSinVentas)
                ? window.gestionAgenciasData.agenciasSinVentas
                : [];
            const ventasPorAgencia = Array.isArray(window.gestionAgenciasData?.ventasPorAgencia)
                ? window.gestionAgenciasData.ventasPorAgencia
                : [];
            let dtGestionAgencias = null;
            let dtModalAgenciasSinVentaGestion = null;

            const escapeHtml = (value) => (value ?? '').toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
            const money = (value) => Number(value || 0).toLocaleString('es-DO', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });

            const formatoUltimaTransaccion = (ultima) => {
                if (!ultima) return 'Ult trans N/D';

                return `Ult trans ${ultima.hora || 'N/D'} por ${money(ultima.monto)}`;
            };

            const bootConsultaAgencia = () => {
                const input = document.getElementById('consultaAgenciaInput');
                const options = document.getElementById('consultaAgenciaOptions');
                const nombre = document.getElementById('consultaAgenciaNombre');
                const terminal = document.getElementById('consultaAgenciaTerminal');
                const tradicionalTotal = document.getElementById('consultaTradicionalTotal');
                const tradicionalUltima = document.getElementById('consultaTradicionalUltima');
                const noTradicionalTotal = document.getElementById('consultaNoTradicionalTotal');
                const noTradicionalUltima = document.getElementById('consultaNoTradicionalUltima');
                const totalAgencia = document.getElementById('consultaTotalAgencia');

                if (!input || !options || !ventasPorAgencia.length) return;

                options.innerHTML = ventasPorAgencia
                    .slice(0, 5000)
                    .map((item) => `<option value="${escapeHtml(item.label)}"></option>`)
                    .join('');

                const render = (item) => {
                    if (!item) {
                        if (nombre) nombre.textContent = 'Agencia no encontrada';
                        if (terminal) terminal.textContent = 'Terminal';
                        if (tradicionalTotal) tradicionalTotal.textContent = '0.00';
                        if (tradicionalUltima) tradicionalUltima.textContent = 'Ult trans N/D';
                        if (noTradicionalTotal) noTradicionalTotal.textContent = '0.00';
                        if (noTradicionalUltima) noTradicionalUltima.textContent = 'Ult trans N/D';
                        if (totalAgencia) totalAgencia.textContent = '0.00';
                        return;
                    }

                    if (nombre) nombre.textContent = item.agencia || item.label || 'SIN AGENCIA';
                    if (terminal) terminal.textContent = item.terminal || 'SIN TERMINAL';
                    if (tradicionalTotal) tradicionalTotal.textContent = money(item.tradicional?.total);
                    if (tradicionalUltima) tradicionalUltima.textContent = formatoUltimaTransaccion(item.tradicional?.ultima);
                    if (noTradicionalTotal) noTradicionalTotal.textContent = money(item.no_tradicional?.total);
                    if (noTradicionalUltima) noTradicionalUltima.textContent = formatoUltimaTransaccion(item.no_tradicional?.ultima);
                    if (totalAgencia) totalAgencia.textContent = money(item.total);
                };

                const buscar = () => {
                    const query = input.value.trim().toLowerCase();
                    if (!query) {
                        render(ventasPorAgencia[0] || null);
                        return;
                    }

                    const exacta = ventasPorAgencia.find((item) => (item.label || '').toLowerCase() === query);
                    const parcial = exacta || ventasPorAgencia.find((item) => (item.busqueda || item.label || '').toLowerCase().includes(query));

                    render(parcial || null);
                };

                input.addEventListener('input', buscar);
                input.addEventListener('change', buscar);
                render(ventasPorAgencia[0] || null);
            };

            const limpiarCacheDataTable = () => {
                try {
                    if (table.length && typeof $ === 'function' && $.fn?.DataTable?.isDataTable(table)) {
                        table.DataTable().clear().destroy();
                    }
                } catch (error) {
                    console.warn('No se pudo destruir el DataTable anterior.', error);
                }

                try {
                    Object.keys(localStorage)
                        .filter((key) => key.toLowerCase().includes('table-gestion-agencias'))
                        .forEach((key) => localStorage.removeItem(key));
                    Object.keys(sessionStorage)
                        .filter((key) => key.toLowerCase().includes('table-gestion-agencias'))
                        .forEach((key) => sessionStorage.removeItem(key));
                } catch (error) {
                    console.warn('No se pudo limpiar el cache local del DataTable.', error);
                }
            };

            const limpiarVistaReporte = () => {
                limpiarCacheDataTable();

                const tbody = document.querySelector('#table-gestion-agencias tbody');
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Limpiando vista y preparando nueva carga...</td></tr>';
                }

                const input = document.getElementById('consultaAgenciaInput');
                const options = document.getElementById('consultaAgenciaOptions');
                if (input) input.value = '';
                if (options) options.innerHTML = '';

                const setText = (id, value) => {
                    const element = document.getElementById(id);
                    if (element) element.textContent = value;
                };

                setText('consultaAgenciaNombre', 'Procesando nueva carga');
                setText('consultaAgenciaTerminal', 'Terminal');
                setText('consultaTradicionalTotal', '0.00');
                setText('consultaTradicionalUltima', 'Ult trans N/D');
                setText('consultaNoTradicionalTotal', '0.00');
                setText('consultaNoTradicionalUltima', 'Ult trans N/D');
                setText('consultaTotalAgencia', '0.00');
            };

            const showCargaReporte = () => {
                if (typeof Swal === 'undefined' || typeof Swal.fire !== 'function') return;

                sessionStorage.setItem('gestionAgenciasProcesando', '1');
                let progress = 12;

                Swal.fire({
                    title: 'Procesando reporte',
                    html: `
                        <div class="progress" style="height: 10px;">
                            <div id="gestionAgenciasProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 12%"></div>
                        </div>
                        <div class="text-muted mt-2" id="gestionAgenciasProgressText">Limpiando vista y cache anterior...</div>
                    `,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        const bar = document.getElementById('gestionAgenciasProgressBar');
                        const text = document.getElementById('gestionAgenciasProgressText');
                        const interval = setInterval(() => {
                            progress = Math.min(progress + Math.floor(Math.random() * 7) + 2, 94);
                            if (bar) bar.style.width = `${progress}%`;
                            if (text) text.textContent = progress < 25
                                ? 'Limpiando vista y cache anterior...'
                                : progress < 45
                                    ? 'Subiendo documentos al servidor...'
                                    : progress < 70
                                        ? 'Leyendo y validando archivos...'
                                        : progress < 88
                                            ? 'Calculando agencias con ventas y sin ventas...'
                                            : 'Esperando respuesta para reconstruir DataTable...';
                        }, 700);

                        const popup = Swal.getPopup();
                        if (popup) popup.__gestionAgenciasInterval = interval;
                    },
                    willClose: () => {
                        const popup = Swal.getPopup();
                        if (popup?.__gestionAgenciasInterval) {
                            clearInterval(popup.__gestionAgenciasInterval);
                        }
                    }
                });
            };

            const prepararEnvioReporte = (event) => {
                if (form?.dataset.submitting === '1') return;

                if (form && !form.checkValidity()) {
                    return;
                }

                event.preventDefault();
                form.dataset.submitting = '1';
                limpiarVistaReporte();
                showCargaReporte();

                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        HTMLFormElement.prototype.submit.call(form);
                    });
                });
            };

            const estaProcesandoReporte = () => sessionStorage.getItem('gestionAgenciasProcesando') === '1';

            const mostrarPreparandoDataTable = () => {
                if (!estaProcesandoReporte() || typeof Swal === 'undefined' || typeof Swal.fire !== 'function') return;

                Swal.fire({
                    title: 'Preparando DataTable',
                    html: `
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 96%"></div>
                        </div>
                        <div class="text-muted mt-2">Renderizando tabla en el navegador...</div>
                    `,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                });
            };

            const esperarPintadoNavegador = () => new Promise((resolve) => {
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        setTimeout(resolve, 250);
                    });
                });
            });

            const esperarDataTableVisible = () => new Promise((resolve) => {
                let intentos = 0;
                const revisar = () => {
                    const wrapperListo = document.querySelector('#table-gestion-agencias_wrapper');

                    if (wrapperListo || intentos >= 80) {
                        resolve();
                        return;
                    }

                    intentos++;
                    setTimeout(revisar, 150);
                };

                revisar();
            });

            const finalizarCargaReporte = () => {
                if (sessionStorage.getItem('gestionAgenciasProcesando') !== '1') return;
                sessionStorage.removeItem('gestionAgenciasProcesando');

                if (typeof Swal === 'undefined' || typeof Swal.fire !== 'function') return;

                Swal.fire({
                    title: 'Carga completada',
                    html: `
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 100%"></div>
                        </div>
                        <div class="text-muted mt-2">DataTable listo al 100%.</div>
                    `,
                    icon: 'success',
                    timer: 900,
                    showConfirmButton: false,
                });
            };

            if (form) {
                form.addEventListener('submit', prepararEnvioReporte);
            }

            if (window.gestionAgenciasData?.tieneResultado) {
                mostrarPreparandoDataTable();
            }

            let dataTableReady = Promise.resolve();

            if (window.gestionAgenciasData?.tieneResultado && table.length && !table.find('tbody tr td[colspan]').length) {
                dataTableReady = new Promise((resolve) => {
                    let resolved = false;
                    const resolveOnce = () => {
                        if (resolved) return;
                        resolved = true;
                        resolve();
                    };

                    setTimeout(resolveOnce, 60000);

                    dtGestionAgencias = table.DataTable({
                        processing: true,
                        serverSide: true,
                        ajax: {
                            url: window.gestionAgenciasData.dataUrl,
                            type: 'GET',
                        },
                        responsive: true,
                        deferRender: true,
                        pageLength: 25,
                        lengthMenu: [[25, 50, 100, 250, 500], [25, 50, 100, 250, 500]],
                        order: [[1, 'asc']],
                        columns: [
                            {
                                data: 'tipo',
                                render: function (data) {
                                    const badge = data === 'Tradicional' ? 'bg-primary' : 'bg-info';
                                    return `<span class="badge ${badge}">${escapeHtml(data)}</span>`;
                                }
                            },
                            { data: 'fecha' },
                            { data: 'agencia' },
                            { data: 'terminal' },
                            { data: 'usuario_venta' },
                            { data: 'estatus' },
                            {
                                data: 'total_apostado',
                                className: 'text-end',
                            },
                        ],
                        language: {
                            search: 'Buscar:',
                            lengthMenu: 'Mostrar _MENU_ registros',
                            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                            infoEmpty: 'Mostrando 0 a 0 de 0 registros',
                            emptyTable: 'No hay datos disponibles',
                            paginate: {
                                first: 'Primero',
                                last: 'Ultimo',
                                next: 'Siguiente',
                                previous: 'Anterior'
                            }
                        },
                        initComplete: () => {
                            resolveOnce();
                        }
                    });
                });
            }

            if (window.gestionAgenciasData?.tieneResultado) {
                dataTableReady
                    .then(esperarDataTableVisible)
                    .then(esperarPintadoNavegador)
                    .then(finalizarCargaReporte);
            }

            const descargarAgenciasSinVentaExcel = () => {
                if (!agenciasSinVentas.length) {
                    Swal.fire({ title: 'Sin datos', text: 'No hay agencias sin venta para descargar.', icon: 'info' });
                    return;
                }

                const filas = agenciasSinVentas.map((item) => {
                    const nombre = escapeHtml(item?.nombre_agencia ?? item?.agencia_id ?? 'SIN AGENCIA');
                    const terminal = escapeHtml(item?.terminal ?? item?.agencia_id ?? 'SIN TERMINAL');

                    return `<tr><td>${nombre}</td><td>${terminal}</td></tr>`;
                }).join('');

                const tablaHtml = `
                    <table>
                        <thead>
                            <tr>
                                <th>Agencia</th>
                                <th>Terminal</th>
                            </tr>
                        </thead>
                        <tbody>${filas}</tbody>
                    </table>
                `;

                const blob = new Blob(['\ufeff', tablaHtml], { type: 'application/vnd.ms-excel;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const enlace = document.createElement('a');
                enlace.href = url;
                enlace.download = 'agencias_sin_ventas_gestion.xls';
                document.body.appendChild(enlace);
                enlace.click();
                document.body.removeChild(enlace);
                URL.revokeObjectURL(url);
            };

            const abrirModalAgenciasSinVenta = () => {
                if (!agenciasSinVentas.length) {
                    Swal.fire({ title: 'Sin datos', text: 'No hay agencias sin venta para mostrar.', icon: 'info' });
                    return;
                }

                if (dtModalAgenciasSinVentaGestion) {
                    dtModalAgenciasSinVentaGestion.destroy();
                    dtModalAgenciasSinVentaGestion = null;
                }

                const tbody = document.querySelector('#tableModalAgenciasSinVentaGestion tbody');
                if (!tbody) return;

                tbody.innerHTML = '';

                agenciasSinVentas.forEach((item) => {
                    const nombre = (item?.nombre_agencia ?? item?.agencia_id ?? 'SIN AGENCIA').toString().trim() || 'SIN AGENCIA';
                    const terminal = (item?.terminal ?? item?.agencia_id ?? 'SIN TERMINAL').toString().trim() || 'SIN TERMINAL';
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${escapeHtml(nombre)}</td>
                        <td>${escapeHtml(terminal)}</td>
                    `;
                    tbody.appendChild(tr);
                });

                if (typeof $ === 'function' && $.fn?.DataTable) {
                    dtModalAgenciasSinVentaGestion = $('#tableModalAgenciasSinVentaGestion').DataTable({
                        destroy: true,
                        responsive: true,
                        language: {
                            url: '/json/es-DO.json',
                            search: 'Buscar:',
                            lengthMenu: 'Mostrar _MENU_ registros',
                            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                            paginate: { first: 'Primera', last: 'Ultima', next: 'Siguiente', previous: 'Anterior' }
                        },
                        order: [[0, 'asc']],
                    });
                }

                const modal = new bootstrap.Modal(document.getElementById('modalAgenciasSinVentaGestion'));
                modal.show();
            };

            const cardAgenciasSinVenta = document.getElementById('cardAgenciasSinVentaGestion');
            const btnExcel = document.getElementById('btnDescargarAgenciasSinVentaGestionExcel');

            if (cardAgenciasSinVenta) {
                cardAgenciasSinVenta.addEventListener('click', abrirModalAgenciasSinVenta);
                cardAgenciasSinVenta.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter' && event.key !== ' ') return;
                    event.preventDefault();
                    abrirModalAgenciasSinVenta();
                });
            }

            if (btnExcel) {
                btnExcel.addEventListener('click', descargarAgenciasSinVentaExcel);
            }

            bootConsultaAgencia();
        });
    </script>
@endsection
