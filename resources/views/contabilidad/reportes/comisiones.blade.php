@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Acuerdos de Comisiones</h4>
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('contabilidad.index') }}">Contabilidad</a></li>
                                <li class="breadcrumb-item active">Comisiones</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Acuerdos</h5>
                                <div class="d-flex flex-wrap gap-2">
                                    <form action="{{ route('contabilidad.reportes.comisiones.calcular-todas') }}" method="POST" class="d-inline form-generar-comision" data-tipo="todas">
                                        @csrf
                                        <input type="hidden" name="fecha_inicio" value="{{ $fechaInicio }}">
                                        <input type="hidden" name="fecha_fin" value="{{ $fechaFin }}">
                                        <button type="submit" class="btn btn-sm text-white" style="background-color:#0f766e;border-color:#0f766e;">
                                            <i class="ri-calculator-line align-bottom me-1"></i>Generar pagos
                                        </button>
                                    </form>
                                    <a href="{{ route('contabilidad.reportes.comisiones.acuerdos.create') }}" class="btn btn-primary btn-sm">
                                        <i class="ri-add-line align-bottom me-1"></i>Nuevo
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                @if(session('success'))
                                    <div class="alert alert-success">{{ session('success') }}</div>
                                @endif

                                @if(session('error'))
                                    <div class="alert alert-danger">{{ session('error') }}</div>
                                @endif

                                <form action="{{ route('contabilidad.reportes.comisiones') }}" method="GET" class="row g-3 align-items-end mb-3">
                                    <div class="col-12 col-md-3 col-lg-2">
                                        <label for="fechaInicioComision" class="form-label">Fecha inicio</label>
                                        <input type="date" id="fechaInicioComision" name="fecha_inicio" class="form-control" value="{{ $fechaInicio }}">
                                    </div>
                                    <div class="col-12 col-md-3 col-lg-2">
                                        <label for="fechaFinComision" class="form-label">Fecha fin</label>
                                        <input type="date" id="fechaFinComision" name="fecha_fin" class="form-control" value="{{ $fechaFin }}">
                                    </div>
                                    <div class="col-12 col-md-3 col-lg-2">
                                        <button type="submit" class="btn btn-secondary w-100">
                                            <i class="ri-filter-3-line align-bottom me-1"></i>Aplicar
                                        </button>
                                    </div>
                                </form>

                                <div class="row mb-3">
                                    <div class="col-12 col-md-5 col-lg-4">
                                        <label for="buscarAcuerdo" class="form-label">Buscar acuerdo</label>
                                        <input type="text" id="buscarAcuerdo" class="form-control" placeholder="Escribe nombre o cedula...">
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle mb-0" id="tablaAcuerdosComision">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center" style="width:80px;">ID</th>
                                                <th>Nombre</th>
                                                <th>Apellido</th>
                                                <th>Correo</th>
                                                <th>Cedula</th>
                                                <th>Telefono</th>
                                                <th class="text-end">Porcentaje</th>
                                                <th class="text-end">Venta base</th>
                                                <th class="text-end">Comision</th>
                                                <th class="text-center">Estado</th>
                                                <th class="text-center">Agencias</th>
                                                <th class="text-center" style="width:180px;">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($acuerdos as $acuerdo)
                                                <tr>
                                                    <td class="text-center">{{ $acuerdo->id }}</td>
                                                    <td>{{ $acuerdo->nombre }}</td>
                                                    <td>{{ $acuerdo->apellido }}</td>
                                                    <td>{{ $acuerdo->correo }}</td>
                                                    <td>{{ $acuerdo->cedula }}</td>
                                                    <td>{{ $acuerdo->telefono }}</td>
                                                    <td class="text-end">{{ number_format((float) $acuerdo->porcentaje, 2) }}%</td>
                                                    <td class="text-end">
                                                        @if(isset($calculos[$acuerdo->id]))
                                                            ${{ number_format((float) $calculos[$acuerdo->id]['venta_base'], 2) }}
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-end">
                                                        @if(isset($calculos[$acuerdo->id]))
                                                            <strong>${{ number_format((float) $calculos[$acuerdo->id]['monto_comision'], 2) }}</strong>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge {{ $acuerdo->activo ? 'bg-success' : 'bg-secondary' }}">
                                                            {{ $acuerdo->activo ? 'Activo' : 'Inactivo' }}
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <button
                                                            type="button"
                                                            class="btn btn-info btn-sm btn-ver-agencias"
                                                            title="Ver agencias asignadas"
                                                            data-nombre="{{ $acuerdo->nombre }} {{ $acuerdo->apellido }}"
                                                            data-agencias='@json($acuerdo->agencias->map(fn($agencia) => ['terminal' => $agencia->terminal, 'nombre_agencia' => $agencia->nombre_agencia, 'agencia' => $agencia->agencia])->values())'>
                                                            {{ $acuerdo->agencias_count }}
                                                        </button>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="d-flex justify-content-center gap-1">
                                                            <form action="{{ route('contabilidad.reportes.comisiones.acuerdos.calcular', $acuerdo->id) }}" method="POST" class="form-generar-comision" data-tipo="individual" data-acuerdo="{{ $acuerdo->nombre }} {{ $acuerdo->apellido }}">
                                                                @csrf
                                                                <input type="hidden" name="fecha_inicio" value="{{ $fechaInicio }}">
                                                                <input type="hidden" name="fecha_fin" value="{{ $fechaFin }}">
                                                                <button type="submit" class="btn btn-sm text-white" style="background-color:#0f766e;border-color:#0f766e;" title="Generar pago independiente">
                                                                    <i class="ri-calculator-line"></i>
                                                                </button>
                                                            </form>
                                                            <button
                                                                type="button"
                                                                class="btn btn-info btn-sm btn-asignar-agencias"
                                                                title="Asignar agencias"
                                                                data-id="{{ $acuerdo->id }}"
                                                                data-acuerdo-id="{{ $acuerdo->id }}"
                                                                data-nombre="{{ $acuerdo->nombre }} {{ $acuerdo->apellido }}"
                                                                data-asignadas='@json($acuerdo->agencias->pluck('id')->values())'>
                                                                <i class="ri-building-line"></i>
                                                            </button>
                                                            <a href="{{ route('contabilidad.reportes.comisiones.acuerdos.edit', $acuerdo->id) }}" class="btn btn-success btn-sm" title="Editar">
                                                                <i class="ri-pencil-line"></i>
                                                            </a>
                                                            <form action="{{ route('contabilidad.reportes.comisiones.acuerdos.clone', $acuerdo->id) }}" method="POST">
                                                                @csrf
                                                                <button type="submit" class="btn btn-warning btn-sm" title="Clonar acuerdo">
                                                                    <i class="ri-file-copy-line"></i>
                                                                </button>
                                                            </form>
                                                            <form action="{{ route('contabilidad.reportes.comisiones.acuerdos.destroy', $acuerdo->id) }}" method="POST" onsubmit="return confirm('Esta seguro de eliminar este acuerdo?')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-danger btn-sm" title="Eliminar">
                                                                    <i class="ri-delete-bin-line"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="12" class="text-center text-muted">No hay acuerdos disponibles.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-3">
                                    {{ $acuerdos->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Calculo de comisiones</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Acuerdo</th>
                                                <th class="text-center">Agencias</th>
                                                <th class="text-end">Porcentaje</th>
                                                <th class="text-end">Venta base</th>
                                                <th class="text-end">Comision estimada</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($acuerdos as $acuerdo)
                                                <tr>
                                                    <td>{{ $acuerdo->nombre }} {{ $acuerdo->apellido }}</td>
                                                    <td class="text-center">{{ $acuerdo->agencias_count }}</td>
                                                    <td class="text-end">{{ number_format((float) $acuerdo->porcentaje, 2) }}%</td>
                                                    <td class="text-end">
                                                        @if(isset($calculos[$acuerdo->id]))
                                                            ${{ number_format((float) $calculos[$acuerdo->id]['venta_base'], 2) }}
                                                        @else
                                                            <span class="text-muted">Sin calcular</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-end">
                                                        @if(isset($calculos[$acuerdo->id]))
                                                            <strong>${{ number_format((float) $calculos[$acuerdo->id]['monto_comision'], 2) }}</strong>
                                                        @else
                                                            <span class="text-muted">Sin calcular</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted">Sin acuerdos para calcular.</td>
                                                </tr>
                                            @endforelse
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

    <div class="modal fade" id="asignarAgenciasModal" tabindex="-1" aria-labelledby="asignarAgenciasModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form id="formAsignarAgencias" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="asignarAgenciasModalLabel">Asignar agencias</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">
                            Asignando agencias a: <strong id="nombreAsignacion">-</strong>
                        </p>

                        <div class="mb-3">
                            <label for="buscarTerminalAgencia" class="form-label mb-1">Buscar por terminal</label>
                            <input type="text" id="buscarTerminalAgencia" class="form-control" placeholder="Escribe una terminal para filtrar...">
                        </div>

                        <div class="mb-3">
                            <label for="terminalesMasivos" class="form-label mb-1">Asignacion masiva por terminal</label>
                            <textarea id="terminalesMasivos" class="form-control" rows="4" placeholder="Pega aqui los codigos de terminal desde Excel o TXT"></textarea>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btnAplicarTerminalesMasivos">Marcar terminales pegadas</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnLimpiarTerminalesMasivos">Limpiar pegado</button>
                            </div>
                            <small class="text-muted d-block mt-1" id="resumenTerminalesMasivos"></small>
                            <div class="mt-2" id="detalleTerminalesNoCoinciden"></div>
                        </div>

                        <div class="border rounded p-3" style="max-height: 380px; overflow-y: auto;">
                            <div class="row g-2" id="listaAgenciasAsignacion">
                                @forelse($agencias as $agencia)
                                    <div class="col-12 col-md-6 item-agencia" data-agencia-id="{{ $agencia->id }}" data-terminal="{{ strtolower($agencia->terminal ?? '') }}" data-texto="{{ strtolower(($agencia->terminal ?? '') . ' ' . ($agencia->nombre_agencia ?? '')) }}">
                                        <div class="form-check">
                                            <input class="form-check-input checkbox-agencia" type="checkbox" name="agencias[]" value="{{ $agencia->id }}" id="agencia_{{ $agencia->id }}">
                                            <label class="form-check-label" for="agencia_{{ $agencia->id }}">
                                                {{ $agencia->terminal ?: '-' }} - {{ $agencia->nombre_agencia ?: 'Sin nombre' }}
                                            </label>
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-12 text-muted">
                                        No hay agencias disponibles para asignar.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar asignacion</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="verAgenciasAsignadasModal" tabindex="-1" aria-labelledby="verAgenciasAsignadasModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="verAgenciasAsignadasModalLabel">Agencias asignadas (<span id="contadorVerAgencias">0</span>)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Acuerdo: <strong id="nombreVerAgencias">-</strong></p>
                    <div id="contenidoVerAgencias" class="border rounded p-2" style="max-height: 320px; overflow-y: auto;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalElement = document.getElementById('asignarAgenciasModal');
        const modal = new bootstrap.Modal(modalElement);
        const modalVerAgenciasElement = document.getElementById('verAgenciasAsignadasModal');
        const modalVerAgencias = new bootstrap.Modal(modalVerAgenciasElement);
        const form = document.getElementById('formAsignarAgencias');
        const nombreAsignacion = document.getElementById('nombreAsignacion');
        const nombreVerAgencias = document.getElementById('nombreVerAgencias');
        const contadorVerAgencias = document.getElementById('contadorVerAgencias');
        const contenidoVerAgencias = document.getElementById('contenidoVerAgencias');
        const checkboxes = document.querySelectorAll('.checkbox-agencia');
        const buscarTerminalAgencia = document.getElementById('buscarTerminalAgencia');
        const itemsAgencia = document.querySelectorAll('.item-agencia');
        const buscarAcuerdo = document.getElementById('buscarAcuerdo');
        const filasTablaAcuerdos = document.querySelectorAll('#tablaAcuerdosComision tbody tr');
        const terminalesMasivos = document.getElementById('terminalesMasivos');
        const btnAplicarTerminalesMasivos = document.getElementById('btnAplicarTerminalesMasivos');
        const btnLimpiarTerminalesMasivos = document.getElementById('btnLimpiarTerminalesMasivos');
        const resumenTerminalesMasivos = document.getElementById('resumenTerminalesMasivos');
        const detalleTerminalesNoCoinciden = document.getElementById('detalleTerminalesNoCoinciden');

        function normalizarTerminal(valor) {
            return String(valor || '').trim().toLowerCase();
        }

        function extraerTerminalesPegadas(texto) {
            return Array.from(
                new Set(
                    String(texto || '')
                        .split(/[\s,;|]+/)
                        .map(normalizarTerminal)
                        .filter(Boolean)
                )
            );
        }

        function escaparHtml(texto) {
            return String(texto || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function aplicarTerminalesMasivos() {
            const terminales = extraerTerminalesPegadas(terminalesMasivos?.value || '');

            if (!terminales.length) {
                resumenTerminalesMasivos.textContent = 'No se detectaron terminales para procesar.';
                return;
            }

            const mapaTerminales = new Set(terminales);
            const terminalesDisponibles = new Set(
                Array.from(itemsAgencia)
                    .map(function (item) { return normalizarTerminal(item.dataset.terminal || ''); })
                    .filter(Boolean)
            );
            let encontradas = 0;

            itemsAgencia.forEach(function (item) {
                const terminalItem = normalizarTerminal(item.dataset.terminal || '');
                const checkbox = item.querySelector('.checkbox-agencia');

                if (checkbox && terminalItem && mapaTerminales.has(terminalItem)) {
                    checkbox.checked = true;
                    encontradas++;
                }
            });

            resumenTerminalesMasivos.textContent = `Terminales procesadas: ${terminales.length}. Coincidencias marcadas: ${encontradas}.`;

            const noCoinciden = terminales.filter(function (terminal) {
                return !terminalesDisponibles.has(terminal);
            });

            if (!noCoinciden.length) {
                detalleTerminalesNoCoinciden.innerHTML = '<small class="text-success">Detalle: todos los codigos coinciden con la tabla de agencias.</small>';
                return;
            }

            const listado = noCoinciden
                .map(function (terminal) {
                    return `<li>${escaparHtml(terminal)}</li>`;
                })
                .join('');

            detalleTerminalesNoCoinciden.innerHTML = `
                <details>
                    <summary class="text-danger" style="cursor:pointer;">Detalle: ${noCoinciden.length} terminal(es) no coinciden con la tabla de agencias</summary>
                    <div class="small text-muted mt-2" style="max-height: 130px; overflow-y: auto;">
                        <ul class="mb-0 ps-3">${listado}</ul>
                    </div>
                </details>
            `;
        }

        function limpiarTerminalesMasivos() {
            terminalesMasivos.value = '';
            resumenTerminalesMasivos.textContent = '';
            detalleTerminalesNoCoinciden.innerHTML = '';
        }

        function filtrarAcuerdosTabla() {
            const termino = (buscarAcuerdo?.value || '').toLowerCase().trim();

            filasTablaAcuerdos.forEach(function (fila) {
                const celdas = fila.querySelectorAll('td');
                if (!celdas.length || celdas.length < 5) {
                    return;
                }

                const nombre = (celdas[1]?.textContent || '').toLowerCase();
                const apellido = (celdas[2]?.textContent || '').toLowerCase();
                const cedula = (celdas[4]?.textContent || '').toLowerCase();
                const coincide = !termino || nombre.includes(termino) || apellido.includes(termino) || `${nombre} ${apellido}`.includes(termino) || cedula.includes(termino);

                fila.style.display = coincide ? '' : 'none';
            });
        }

        function filtrarAgenciasModal() {
            const termino = (buscarTerminalAgencia?.value || '').toLowerCase().trim();

            itemsAgencia.forEach(function (item) {
                const texto = item.dataset.texto || '';
                item.style.display = texto.includes(termino) ? '' : 'none';
            });
        }

        document.querySelectorAll('.btn-asignar-agencias').forEach(function (button) {
            button.addEventListener('click', function () {
                const id = this.dataset.id;
                const nombre = this.dataset.nombre || '-';
                const asignadas = JSON.parse(this.dataset.asignadas || '[]');

                form.action = `/contabilidad/reportes/comisiones/acuerdos/${id}/asignar-agencias`;
                form.dataset.acuerdoId = String(this.dataset.acuerdoId || id || '0');
                form.dataset.asignadasIniciales = JSON.stringify(asignadas);
                nombreAsignacion.textContent = nombre;

                checkboxes.forEach(function (checkbox) {
                    checkbox.checked = asignadas.includes(Number(checkbox.value));
                });

                buscarTerminalAgencia.value = '';
                filtrarAgenciasModal();
                limpiarTerminalesMasivos();

                modal.show();
            });
        });

        buscarTerminalAgencia.addEventListener('input', filtrarAgenciasModal);
        buscarAcuerdo.addEventListener('input', filtrarAcuerdosTabla);
        btnAplicarTerminalesMasivos.addEventListener('click', aplicarTerminalesMasivos);
        btnLimpiarTerminalesMasivos.addEventListener('click', limpiarTerminalesMasivos);

        document.querySelectorAll('.form-generar-comision').forEach(function (formGenerar) {
            formGenerar.addEventListener('submit', function (event) {
                if (formGenerar.dataset.enviando === '1') {
                    return;
                }

                const tipo = formGenerar.dataset.tipo || 'individual';
                const esMasivo = tipo === 'todas';
                const acuerdo = formGenerar.dataset.acuerdo || 'este acuerdo';
                const titulo = esMasivo ? 'Generar todos los pagos' : 'Generar pago independiente';
                const texto = esMasivo
                    ? 'Se calcularan las comisiones de todos los acuerdos creados para el periodo seleccionado.'
                    : `Se calculara la comision solo para ${acuerdo} en el periodo seleccionado.`;

                event.preventDefault();

                if (window.Swal && typeof window.Swal.fire === 'function') {
                    window.Swal.fire({
                        icon: 'question',
                        title: titulo,
                        text: texto,
                        showCancelButton: true,
                        confirmButtonText: esMasivo ? 'Si, generar todos' : 'Si, generar independiente',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#0f766e',
                        cancelButtonColor: '#f06548',
                        reverseButtons: true,
                    }).then(function (resultado) {
                        if (!resultado.isConfirmed) {
                            return;
                        }

                        window.Swal.fire({
                            title: 'Consultando ventas',
                            text: esMasivo
                                ? 'Generando pagos segun las agencias de todos los acuerdos y el periodo seleccionado...'
                                : 'Generando pago segun las agencias del acuerdo y el periodo seleccionado...',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            didOpen: function () {
                                window.Swal.showLoading();
                            },
                        });

                        formGenerar.dataset.enviando = '1';
                        formGenerar.submit();
                    });
                    return;
                }

                if (confirm(texto)) {
                    formGenerar.dataset.enviando = '1';
                    formGenerar.submit();
                }
            });
        });

        document.querySelectorAll('.btn-ver-agencias').forEach(function (button) {
            button.addEventListener('click', function () {
                const nombre = this.dataset.nombre || '-';
                const agencias = JSON.parse(this.dataset.agencias || '[]');

                nombreVerAgencias.textContent = nombre;
                contadorVerAgencias.textContent = String(agencias.length || 0);

                if (!agencias.length) {
                    contenidoVerAgencias.innerHTML = '<p class="text-muted mb-0">No tiene agencias asignadas.</p>';
                } else {
                    contenidoVerAgencias.innerHTML = agencias.map(function (agencia) {
                        const terminal = agencia.terminal || '-';
                        const nombreAgencia = agencia.nombre_agencia || agencia.agencia || 'Sin nombre';
                        return `<div class="py-1 border-bottom">${escaparHtml(terminal)} - ${escaparHtml(nombreAgencia)}</div>`;
                    }).join('');
                }

                modalVerAgencias.show();
            });
        });
    });
</script>
@endsection
