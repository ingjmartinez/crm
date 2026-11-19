@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Novedades de feriados</h4>
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('recursos-humanos.index') }}">Recursos Humanos</a></li>
                        <li class="breadcrumb-item active">Novedades de feriados</li>
                    </ol>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Filtros</h5></div>
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="cedula" class="form-label">Cédula (opcional)</label>
                                <input type="text" id="cedula" class="form-control" placeholder="Vacío para consultar todos" inputmode="numeric" maxlength="13">
                            </div>
                            <div class="col-md-3">
                                <label for="empresa" class="form-label">Empresa</label>
                                <select id="empresa" class="form-control">
                                    <option value="">Todas las empresas</option>
                                    <option value="168">Grupo Joselito</option>
                                    <option value="169">Negosur</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="idturno" class="form-label">Turno</label>
                                <select id="idturno" class="form-control">
                                    <option value="">Todos los turnos</option>
                                    <option value="7">Matutino Banqueras - 7</option>
                                    <option value="8">Vespertino Banqueras - 8</option>
                                    <option value="14">Matutino Entrada Temprano - 14</option>
                                    <option value="9">Turno Completo Banquera - 9</option>
                                    <option value="1">Administrativo - 1</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="sistema" class="form-label">Sistema</label>
                                <select id="sistema" class="form-control">
                                    <option value="todos">Todos</option>
                                    <option value="lotonet">Lotonet</option>
                                    <option value="lotobet">Lotobet</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                                <input type="date" id="fecha_inicio" class="form-control" value="{{ today()->startOfMonth()->toDateString() }}">
                            </div>
                            <div class="col-md-2">
                                <label for="fecha_fin" class="form-label">Fecha Fin</label>
                                <input type="date" id="fecha_fin" class="form-control" value="{{ today()->toDateString() }}">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-primary w-100" id="btnBuscar"><i class="ri-search-line"></i> Consultar</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Resultados</h5></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="tablePoncheCedula" class="table table-bordered table-striped align-middle" style="width: 100%;">
                                <thead><tr><th>Cédula</th><th>ID empleado</th><th>Nombre</th><th>Día</th><th>Primer login</th><th>Último login</th><th>Salida calculada</th><th class="text-end">Horas del sistema</th><th class="text-end">Hora calculada</th><th>Primera venta</th><th>Justificación</th></tr></thead>
                                <tbody></tbody>
                                <tfoot><tr><th colspan="7" class="text-end">Horas del sistema</th><th id="totalHoras" class="text-end">0,00</th><th id="totalHorasCalculadas" class="text-end">0,00</th><th></th><th></th></tr></tfoot>
                            </table>
                        </div>
                        <small class="text-muted">Total de registros: <span id="totalRegistros" class="fw-semibold">0</span></small>
                        <p class="text-muted small mt-2 mb-0">La salida calculada considera cinco minutos después de la última venta de las tres categorías.</p>
                        <p class="text-muted small mt-1 mb-0">Hora calculada: duración en horas decimales desde el primer ponche hasta la salida calculada cuando no hay cruce de usuarios. Si falta la entrada o existe un cruce, se usa la primera venta menos dos minutos. Si falta el ponche de salida, se usan cinco minutos después de la última transacción.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalJustificacionFeriado" tabindex="-1" aria-labelledby="tituloJustificacionFeriado" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tituloJustificacionFeriado">Justificación del horario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-semibold mb-1" id="revisionEmpleado"></p>
                    <p class="text-muted small" id="revisionFecha"></p>
                    <div class="alert alert-warning" id="revisionMotivo"></div>
                    <dl class="row small mb-0">
                        <dt class="col-6">Entrada del ponche</dt><dd class="col-6" id="revisionEntrada"></dd>
                        <dt class="col-6">Salida calculada</dt><dd class="col-6" id="revisionSalida"></dd>
                        <dt class="col-6">Primera venta</dt><dd class="col-6" id="revisionPrimera"></dd>
                        <dt class="col-6">Entrada calculada</dt><dd class="col-6" id="revisionEntradaCalculada"></dd>
                        <dt class="col-6">Hora calculada</dt><dd class="col-6" id="revisionCalculada"></dd>
                        <dt class="col-6">Última transacción</dt><dd class="col-6" id="revisionUltima"></dd>
                        <dt class="col-6">Movimientos</dt><dd class="col-6" id="revisionMovimientos"></dd>
                    </dl>
                    <div id="revisionCoincidencias"></div>
                    <p class="text-muted small mt-3 mb-0">Las transacciones identifican actividad de la cuenta. La primera venta no confirma la hora exacta de entrada ni la presencia física. El motivo describe una inconsistencia posible, no una causa comprobada.</p>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button></div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        let table;

        function formatearHoras(valor) {
            return valor === null || valor === undefined ? '—' : Number(valor).toLocaleString('es-DO', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        function escaparTexto(valor) {
            return String(valor ?? '').replace(/[&<>"']/g, caracter => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[caracter]));
        }

        function etiquetaRevision(revision) {
            return {conflicto: 'Revisar cruce', sin_transacciones: 'Sin transacciones', sin_cruce_detectado: 'Ver evidencia'}[revision?.estado] || 'Pendiente';
        }

        function abrirJustificacion(fila) {
            const revision = fila.revision;
            const valores = {
                revisionEmpleado: `${fila.nombre} · ${fila.cedula}`,
                revisionFecha: `${fila.fecha} · ${fila.sistema}`,
                revisionMotivo: revision.motivo,
                revisionEntrada: fila.primer_login || 'Sin registro',
                revisionSalida: fila.salida_calculada || 'Sin registro',
                revisionPrimera: revision.primera_transaccion || 'Sin transacciones',
                revisionCalculada: formatearHoras(fila.hora_calculada),
                revisionEntradaCalculada: fila.entrada_calculada || 'Sin entrada disponible',
                revisionUltima: revision.ultima_transaccion || 'Sin transacciones',
                revisionMovimientos: revision.movimientos
            };
            Object.entries(valores).forEach(([id, valor]) => document.getElementById(id).textContent = valor);
            document.getElementById('revisionMotivo').className = revision.estado === 'conflicto' ? 'alert alert-warning' : 'alert alert-info';
            document.getElementById('revisionCoincidencias').innerHTML = revision.coincidencias.length
                ? '<h6 class="mt-3">Actividad coincidente en el terminal</h6>' + revision.coincidencias.map(actividad => `
                    <div class="border rounded p-2 mt-2 small">
                        <strong>Cédula ${escaparTexto(actividad.cedula)}</strong> · Terminal ${escaparTexto(actividad.terminal)}<br>
                        Primera: ${escaparTexto(actividad.primera)}<br>
                        Última: ${escaparTexto(actividad.ultima)}<br>
                        Movimientos: ${escaparTexto(actividad.cantidad)}
                    </div>`).join('')
                : '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalJustificacionFeriado')).show();
        }

        function cargarDatos() {
            const cedula = document.getElementById('cedula').value.trim();
            const sistema = document.getElementById('sistema').value;
            const empresa = document.getElementById('empresa').value;
            const idturno = document.getElementById('idturno').value;
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;

            if (cedula && !/^\d{11}$/.test(cedula.replace(/\D/g, ''))) {
                Swal.fire('Error', 'Ingrese una cédula de 11 dígitos.', 'error');
                return;
            }
            if (!fechaInicio || !fechaFin || fechaInicio > fechaFin) {
                Swal.fire('Error', 'Seleccione un rango de fechas válido.', 'error');
                return;
            }

            Swal.fire({title: 'Consultando...', allowOutsideClick: false, didOpen: () => Swal.showLoading()});
            if (table) table.destroy();

            table = $('#tablePoncheCedula').DataTable({
                ajax: {
                    url: @json(route('recursos-humanos.novedades-feriados.list')),
                    type: 'GET',
                    data: {cedula, empresa, idturno, sistema, fecha_inicio: fechaInicio, fecha_fin: fechaFin},
                    dataSrc: '',
                    complete: () => Swal.close(),
                    error: function(xhr) {
                        Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo consultar el reporte.', 'error');
                    }
                },
                columns: [
                    {data: 'cedula'},
                    {data: 'empleadoid', defaultContent: '—', render: data => data ?? '—'},
                    {data: 'nombre'},
                    {data: 'fecha'},
                    {data: 'primer_login', defaultContent: '—', render: data => data || '—'},
                    {data: 'ultimo_login', defaultContent: '—', render: data => data || '—'},
                    {data: 'salida_calculada', defaultContent: '—', render: data => data || '—'},
                    {data: 'total_horas', className: 'text-end', render: data => data === null ? '—' : Number(data).toLocaleString('es-DO', {minimumFractionDigits: 2, maximumFractionDigits: 2})},
                    {data: 'hora_calculada', className: 'text-end', render: (data, type) => type === 'display' ? formatearHoras(data) : data},
                    {data: 'primera_venta', defaultContent: '—', render: data => data ? data.slice(11) : '—'},
                    {data: 'revision', render: (data, type) => {
                        const etiqueta = etiquetaRevision(data);
                        if (type !== 'display') return data?.motivo || etiqueta;
                        const estilo = data?.estado === 'conflicto' ? 'btn-warning text-dark fw-semibold shadow-sm' : 'btn-outline-secondary';
                        return `<button type="button" class="btn btn-sm ${estilo} btn-justificacion" aria-label="Ver justificación del horario">${etiqueta}</button>`;
                    }}
                ],
                autoWidth: false,
                dom: 'Bfrtip',
                buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
                language: {url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'},
                order: [[3, 'asc'], [0, 'asc']],
                paging: false,
                searching: false,
                info: false,
                drawCallback: settings => {
                    document.getElementById('totalRegistros').textContent = settings.json?.length || 0;
                },
                footerCallback: function() {
                    const total = this.api().rows({search: 'applied'}).data().toArray()
                        .reduce((suma, row) => suma + Number(row.total_horas || 0), 0);
                    document.getElementById('totalHoras').textContent = total.toLocaleString('es-DO', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    const horasCalculadas = this.api().rows({search: 'applied'}).data().toArray()
                        .reduce((suma, row) => suma + Number(row.hora_calculada || 0), 0);
                    document.getElementById('totalHorasCalculadas').textContent = formatearHoras(horasCalculadas);
                }
            });
        }

        document.getElementById('btnBuscar').addEventListener('click', cargarDatos);
        document.getElementById('tablePoncheCedula').addEventListener('click', event => {
            const boton = event.target.closest('.btn-justificacion');
            if (boton && table) abrirJustificacion(table.row(boton.closest('tr')).data());
        });
        document.getElementById('cedula').addEventListener('keypress', event => {
            if (event.key === 'Enter') cargarDatos();
        });
    </script>
@endsection
