@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Distribución de Gastos de Ruta</h4>
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('operaciones.index') }}">Operaciones</a></li>
                                <li class="breadcrumb-item active">Distribución de Gastos de Ruta</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info border-0">
                    <i class="ri-information-line me-1"></i>
                    Combustible se divide en partes iguales entre las agencias participantes de la ruta. Las demás
                    cuentas se asignan completas a la terminal seleccionada. Los gastos heredados deben clasificarse
                    desde <strong>Movimientos por Ruta V2</strong> antes de entrar en esta distribución.
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-1">Configuración manual de rutas y socios</h5>
                        <p class="text-muted mb-0">Relacione la ruta del gasto con <strong>Ruta empresa (id_grupo)</strong> y <strong>Socio (id_sub_grupo)</strong>. Puede agregar varios socios a una misma ruta.</p>
                    </div>
                    <div class="card-body">
                        <form id="formMapeoRuta" class="row g-3 align-items-end">
                            @csrf
                            <div class="col-lg-4">
                                <label for="mapeoRutaKey" class="form-label">Ruta del gasto</label>
                                <select id="mapeoRutaKey" class="form-select" required>
                                    <option value="">Seleccione...</option>
                                    @foreach ($rutasDisponibles as $rutaDisponible)
                                        <option value="{{ $rutaDisponible->ruta_key }}">{{ $rutaDisponible->ruta }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-4 col-lg-2">
                                <label for="mapeoIdGrupo" class="form-label">ID Ruta empresa</label>
                                <input type="text" inputmode="numeric" id="mapeoIdGrupo" class="form-control" placeholder="Ej. 61" required>
                            </div>
                            <div class="col-sm-4 col-lg-2">
                                <label for="mapeoIdSubGrupo" class="form-label">ID Socio</label>
                                <input type="text" inputmode="numeric" id="mapeoIdSubGrupo" class="form-control" placeholder="Ej. 45" required>
                            </div>
                            <div class="col-sm-4 col-lg-2">
                                <label for="mapeoCompanyId" class="form-label">Empresa</label>
                                <select id="mapeoCompanyId" class="form-select" required>
                                    <option value="">Seleccione...</option>
                                    <option value="168">Grupo Joselito (168)</option>
                                    <option value="169">Negosur (169)</option>
                                </select>
                            </div>
                            <div class="col-lg-2 d-grid">
                                <button type="submit" class="btn btn-primary" id="btnGuardarMapeo">
                                    <i class="ri-add-line me-1"></i>Agregar socio
                                </button>
                            </div>
                        </form>

                        <div class="table-responsive mt-4">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead><tr><th>Ruta del gasto</th><th>Empresa</th><th>Ruta empresa</th><th>Socio</th><th class="text-center">Acción</th></tr></thead>
                                <tbody>
                                    @forelse ($mapeos as $mapeo)
                                        <tr>
                                            <td>{{ $mapeo->ruta_nombre }}</td>
                                            <td>{{ $mapeo->company_id }}</td>
                                            <td>{{ $mapeo->id_grupo }} - {{ $mapeo->nombre_grupo }}</td>
                                            <td>{{ $mapeo->id_sub_grupo }} - {{ $mapeo->nombre_socio }}</td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-mapeo" data-id="{{ $mapeo->id }}" title="Eliminar">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted py-3">Todavía no hay relaciones manuales.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <form id="formDistribucion" class="row g-3 align-items-end">
                            <div class="col-sm-6 col-lg-3">
                                <label for="fechaIni" class="form-label">Fecha inicial</label>
                                <input type="date" id="fechaIni" class="form-control" required>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label for="fechaFin" class="form-label">Fecha final</label>
                                <input type="date" id="fechaFin" class="form-control" required>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label for="empresa" class="form-label">Empresa</label>
                                <select id="empresa" class="form-select">
                                    <option value="todas">Todas</option>
                                    <option value="GJ">Grupo Joselito</option>
                                    <option value="NG">Negosur</option>
                                </select>
                            </div>
                            <div class="col-sm-6 col-lg-3 d-grid">
                                <button type="submit" class="btn btn-primary" id="btnGenerar">
                                    <i class="ri-calculator-line me-1"></i>Generar distribución
                                </button>
                            </div>
                        </form>
                        <div class="border-top mt-3 pt-3">
                            <div class="row g-3 align-items-end">
                                <div class="col-lg-8">
                                    <label for="rutaPdf" class="form-label">Ruta para informe PDF</label>
                                    <select id="rutaPdf" class="form-select">
                                        <option value="">Seleccione una ruta con gastos...</option>
                                        @foreach ($rutasDisponibles as $rutaDisponible)
                                            <option value="{{ $rutaDisponible->ruta_key }}">{{ $rutaDisponible->ruta }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">El PDF utilizará el período y la empresa seleccionados arriba.</div>
                                </div>
                                <div class="col-lg-4 d-grid">
                                    <button type="button" class="btn btn-danger" id="btnPdf">
                                        <i class="ri-file-pdf-2-line me-1"></i>Generar informe PDF
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    @foreach ([
                        ['Total de gastos', 'totalGastos', 'text-dark'],
                        ['Asignado a socios', 'totalAsignado', 'text-success'],
                        ['Pendiente', 'totalPendiente', 'text-danger'],
                        ['Pendiente de clasificar', 'totalPendienteClasificar', 'text-warning'],
                        ['Socios', 'totalSocios', 'text-primary'],
                    ] as [$titulo, $id, $clase])
                        <div class="col-sm-6 col-xl-3">
                            <div class="card mb-0 h-100">
                                <div class="card-body">
                                    <p class="text-muted mb-1">{{ $titulo }}</p>
                                    <h4 class="mb-0 {{ $clase }}" id="{{ $id }}">{{ $id === 'totalSocios' ? '0' : 'RD$ 0.00' }}</h4>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="card">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h5 class="card-title mb-1">Resultado de la distribución</h5>
                            <p class="text-muted mb-0" id="periodoReporte">Seleccione un período para generar el reporte.</p>
                        </div>
                        <button type="button" class="btn btn-success" id="btnExcel" disabled>
                            <i class="ri-file-excel-2-line me-1"></i>Descargar resumen
                        </button>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs nav-tabs-custom mb-3" role="tablist">
                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabSocios" type="button">Por socio</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabAgencias" type="button">Detalle por agencia</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabRutas" type="button">Control por ruta</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabIncidencias" type="button">Incidencias <span class="badge bg-danger ms-1" id="cantidadIncidencias">0</span></button></li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="tabSocios">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle w-100" id="tablaSocios">
                                        <thead><tr><th>Ruta</th><th>Empresa</th><th>Cuenta</th><th>Socio</th><th class="text-end">Agencias</th><th class="text-end">Gasto cuenta</th><th class="text-end">Participación</th><th class="text-end">Gasto socio</th><th>Estado</th></tr></thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="tabAgencias">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle w-100" id="tablaAgencias">
                                        <thead><tr><th>Ruta</th><th>Cuenta</th><th>Terminal</th><th>Agencia</th><th>Socio</th><th class="text-end">Agencias ruta</th><th class="text-end">Gasto cuenta</th><th class="text-end">Participación</th><th class="text-end">Gasto agencia</th><th>Estado</th></tr></thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="tabRutas">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle w-100" id="tablaRutas">
                                        <thead><tr><th>Ruta</th><th>Empresa</th><th class="text-end">Gastos</th><th class="text-end">Agencias</th><th class="text-end">Socios</th><th class="text-end">Gasto ruta</th><th class="text-end">Asignado</th><th class="text-end">Pendiente</th><th>Estado</th></tr></thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="tabIncidencias">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle w-100" id="tablaIncidencias">
                                        <thead><tr><th>Ruta</th><th>Terminal</th><th>Agencia</th><th>Tipo</th><th>Detalle</th><th class="text-end">Monto pendiente</th></tr></thead>
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
        const dataUrlDistribucion = @json(route('operaciones.distribucion-gastos-ruta.data'));
        const pdfUrlDistribucion = @json(route('operaciones.distribucion-gastos-ruta.pdf'));
        const storeMapeoUrl = @json(route('operaciones.distribucion-gastos-ruta.mapeos.store'));
        const destroyMapeoUrl = @json(route('operaciones.distribucion-gastos-ruta.mapeos.destroy', ['mapeo' => '__ID__']));
        const tablasDistribucion = {};

        document.addEventListener('DOMContentLoaded', function () {
            const hoy = new Date();
            const primerDia = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
            document.getElementById('fechaIni').value = fechaLocal(primerDia);
            document.getElementById('fechaFin').value = fechaLocal(hoy);
            document.getElementById('formDistribucion').addEventListener('submit', generarDistribucion);
            document.getElementById('btnPdf').addEventListener('click', generarPdfDistribucion);
            document.getElementById('formMapeoRuta').addEventListener('submit', guardarMapeoRuta);
            document.querySelectorAll('.btn-eliminar-mapeo').forEach((boton) => boton.addEventListener('click', eliminarMapeoRuta));
            document.getElementById('btnExcel').addEventListener('click', function () {
                tablasDistribucion.socios?.button('.buttons-excel').trigger();
            });
        });

        function generarPdfDistribucion() {
            const rutaKey = document.getElementById('rutaPdf').value;
            const fechaIni = document.getElementById('fechaIni').value;
            const fechaFin = document.getElementById('fechaFin').value;

            if (!rutaKey || !fechaIni || !fechaFin) {
                if (typeof Swal !== 'undefined') Swal.fire({ title: 'Datos incompletos', text: 'Seleccione el período y la ruta que desea imprimir.', icon: 'warning' });
                return;
            }

            const params = new URLSearchParams({
                fecha_ini: fechaIni,
                fecha_fin: fechaFin,
                empresa: document.getElementById('empresa').value,
                ruta_key: rutaKey,
            });
            window.open(`${pdfUrlDistribucion}?${params.toString()}`, '_blank');
        }

        async function guardarMapeoRuta(event) {
            event.preventDefault();
            const boton = document.getElementById('btnGuardarMapeo');
            boton.disabled = true;

            try {
                const response = await fetch(storeMapeoUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('#formMapeoRuta input[name="_token"]').value },
                    body: JSON.stringify({
                        ruta_key: document.getElementById('mapeoRutaKey').value,
                        id_grupo: document.getElementById('mapeoIdGrupo').value,
                        id_sub_grupo: document.getElementById('mapeoIdSubGrupo').value,
                        company_id: document.getElementById('mapeoCompanyId').value,
                    }),
                });
                const payload = await parsearJsonDistribucion(response);
                await Swal.fire({ title: 'Relación guardada', text: `${payload.terminales} terminal(es) serán incluidas.`, icon: 'success' });
                window.location.reload();
            } catch (error) {
                if (typeof Swal !== 'undefined') Swal.fire({ title: 'No se pudo guardar', text: error.message, icon: 'error' });
            } finally {
                boton.disabled = false;
            }
        }

        async function eliminarMapeoRuta(event) {
            const id = event.currentTarget.dataset.id;
            const confirmacion = await Swal.fire({ title: '¿Eliminar esta relación?', text: 'La ruta dejará de usar ese socio en la distribución.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar' });
            if (!confirmacion.isConfirmed) return;

            try {
                const response = await fetch(destroyMapeoUrl.replace('__ID__', id), {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('#formMapeoRuta input[name="_token"]').value },
                });
                await parsearJsonDistribucion(response);
                window.location.reload();
            } catch (error) {
                Swal.fire({ title: 'No se pudo eliminar', text: error.message, icon: 'error' });
            }
        }

        function fechaLocal(fecha) {
            const offset = fecha.getTimezoneOffset();
            return new Date(fecha.getTime() - (offset * 60000)).toISOString().slice(0, 10);
        }

        async function generarDistribucion(event) {
            event.preventDefault();
            const boton = document.getElementById('btnGenerar');
            const params = new URLSearchParams({
                fecha_ini: document.getElementById('fechaIni').value,
                fecha_fin: document.getElementById('fechaFin').value,
                empresa: document.getElementById('empresa').value,
            });

            boton.disabled = true;
            boton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generando...';
            if (typeof loading === 'function') loading('Distribuyendo gastos por agencia...');

            try {
                const response = await fetch(`${dataUrlDistribucion}?${params.toString()}`, { headers: { Accept: 'application/json' } });
                const payload = await parsearJsonDistribucion(response);
                renderizarReporte(payload);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ title: 'Distribución generada', text: `${Number(payload.meta.total_rutas || 0).toLocaleString('es-DO')} rutas procesadas.`, icon: payload.meta.total_incidencias ? 'warning' : 'success' });
                }
            } catch (error) {
                if (typeof Swal !== 'undefined') Swal.fire({ title: 'No se pudo generar', text: error.message, icon: 'error' });
            } finally {
                boton.disabled = false;
                boton.innerHTML = '<i class="ri-calculator-line me-1"></i>Generar distribución';
            }
        }

        async function parsearJsonDistribucion(response) {
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload) {
                const errores = payload?.errors ? Object.values(payload.errors).flat().join(' ') : '';
                throw new Error(errores || payload?.message || 'El servidor no devolvió una respuesta válida.');
            }
            return payload;
        }

        function renderizarReporte(payload) {
            const meta = payload.meta || {};
            document.getElementById('totalGastos').textContent = dinero(meta.total_gastos);
            document.getElementById('totalAsignado').textContent = dinero(meta.total_asignado_socios);
            document.getElementById('totalPendiente').textContent = dinero(meta.total_pendiente);
            document.getElementById('totalPendienteClasificar').textContent = dinero(meta.total_pendiente_clasificar);
            document.getElementById('totalSocios').textContent = Number(meta.total_socios || 0).toLocaleString('es-DO');
            document.getElementById('cantidadIncidencias').textContent = Number(meta.total_incidencias || 0).toLocaleString('es-DO');
            document.getElementById('periodoReporte').textContent = `Período: ${meta.fecha_ini} al ${meta.fecha_fin}`;

            tablasDistribucion.socios = crearTabla('#tablaSocios', payload.data || [], [
                { data: 'ruta', render: texto }, { data: 'empresa', render: texto },
                { data: null, render: fila => `${texto(fila.cuenta_codigo)} - ${texto(fila.cuenta_descripcion)}` }, { data: 'socio', render: texto },
                { data: 'agencias', className: 'text-end' }, { data: 'gasto_ruta', className: 'text-end', render: moneda },
                { data: 'participacion', className: 'text-end', render: porcentaje }, { data: 'gasto_socio', className: 'text-end fw-semibold', render: moneda },
                { data: 'estado', render: estado },
            ], 'distribucion_gastos_ruta_socios');
            tablasDistribucion.agencias = crearTabla('#tablaAgencias', payload.detalle || [], [
                { data: 'ruta', render: texto },
                { data: null, render: fila => `${texto(fila.cuenta_codigo)} - ${texto(fila.cuenta_descripcion)}` },
                { data: 'terminal', render: texto }, { data: 'agencia', render: texto }, { data: 'socio', render: texto },
                { data: 'total_agencias_ruta', className: 'text-end' }, { data: 'gasto_ruta', className: 'text-end', render: moneda },
                { data: 'participacion', className: 'text-end', render: porcentaje }, { data: 'gasto_agencia', className: 'text-end fw-semibold', render: moneda },
                { data: 'estado', render: estado },
            ], 'distribucion_gastos_ruta_agencias');
            tablasDistribucion.rutas = crearTabla('#tablaRutas', payload.rutas || [], [
                { data: 'ruta', render: texto }, { data: 'empresa', render: texto }, { data: 'gastos', className: 'text-end' },
                { data: 'agencias', className: 'text-end' }, { data: 'socios', className: 'text-end' }, { data: 'gasto_ruta', className: 'text-end', render: moneda },
                { data: 'asignado_socios', className: 'text-end', render: moneda }, { data: 'pendiente', className: 'text-end', render: moneda }, { data: 'estado', render: estado },
            ], 'distribucion_gastos_ruta_control');
            tablasDistribucion.incidencias = crearTabla('#tablaIncidencias', payload.incidencias || [], [
                { data: 'ruta', render: texto }, { data: 'terminal', render: texto }, { data: 'agencia', render: texto },
                { data: 'tipo', render: texto }, { data: 'detalle', render: texto }, { data: 'monto_pendiente', className: 'text-end', render: moneda },
            ], 'distribucion_gastos_ruta_incidencias');

            document.getElementById('btnExcel').disabled = !(payload.data || []).length;
        }

        function crearTabla(selector, filas, columnas, archivo) {
            if ($.fn.DataTable.isDataTable(selector)) $(selector).DataTable().clear().destroy();
            return $(selector).DataTable({
                data: filas,
                columns: columnas,
                responsive: true,
                pageLength: 25,
                order: [[0, 'asc']],
                dom: 'Bfrtip',
                buttons: [{ extend: 'excelHtml5', title: 'Distribución de Gastos de Ruta', filename: archivo, text: 'Excel' }],
                language: { search: 'Buscar:', info: 'Mostrando _START_ a _END_ de _TOTAL_', infoEmpty: 'No hay datos', zeroRecords: 'No hay resultados', paginate: { next: 'Siguiente', previous: 'Anterior' } },
            });
        }

        function dinero(valor) {
            return `RD$ ${Number(valor || 0).toLocaleString('es-DO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        }
        function moneda(valor, tipo) { return tipo === 'display' ? dinero(valor) : Number(valor || 0); }
        function porcentaje(valor, tipo) { return tipo === 'display' ? `${Number(valor || 0).toFixed(2)}%` : Number(valor || 0); }
        function texto(valor, tipo) { return tipo === 'display' ? $('<div>').text(valor ?? '').html() : (valor ?? ''); }
        function estado(valor, tipo) {
            if (tipo !== 'display') return valor || '';
            const correcto = valor === 'asignado' || valor === 'distribuida';
            return `<span class="badge ${correcto ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'}">${texto(valor)}</span>`;
        }
    </script>
@endsection
