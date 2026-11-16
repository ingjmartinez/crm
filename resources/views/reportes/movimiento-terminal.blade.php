@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Movimiento de terminal</h4>
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('reportes.index') }}">Reportes</a></li>
                        <li class="breadcrumb-item active">Movimiento de terminal</li>
                    </ol>
                </div>
                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Filtros</h5></div>
                    <div class="card-body">
                        <form id="formMovimientoTerminal">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-2">
                                    <label for="empresa" class="form-label">Empresa</label>
                                    <select id="empresa" name="empresa" class="form-control" required>
                                        @forelse ($empresas as $empresa)
                                            <option value="{{ $empresa }}">{{ $empresa }}</option>
                                        @empty
                                            <option value="">Sin empresas registradas</option>
                                        @endforelse
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                                    <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" value="{{ now()->startOfMonth()->toDateString() }}" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="fecha_fin" class="form-label">Fecha Fin</label>
                                    <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" value="{{ now()->toDateString() }}" required>
                                </div>
                                <div class="col-md-2">
                                    <label for="periodo" class="form-label">Período</label>
                                    <select id="periodo" name="periodo" class="form-control" required>
                                        <option value="dia">Día</option>
                                        <option value="mes">Mes</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="terminal_codigo" class="form-label">Terminal</label>
                                    <input type="text" id="terminal_codigo" name="terminal" class="form-control" placeholder="Código" inputmode="numeric" pattern="[0-9]+" maxlength="30" required>
                                </div>
                                <div class="col-md-2">
                                    <label for="agencia_codigo" class="form-label">Agencia</label>
                                    <input type="text" id="agencia_codigo" class="form-control" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label for="agencia_nombre" class="form-label">Nombre Agencia</label>
                                    <input type="text" id="agencia_nombre" class="form-control" readonly>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary" id="btnBuscar"><i class="ri-search-line"></i> Buscar</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-1">Datos limpios</h5>
                        <p class="text-muted mb-0">Todos los movimientos de la terminal dentro del rango seleccionado, del más reciente al más antiguo.</p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle w-100" id="table-movimiento-terminal">
                                <thead class="table-light">
                                    <tr><th>Tipo</th><th>Terminal</th><th>Cédula</th><th>Empleado</th><th>Última transacción</th></tr>
                                </thead>
                                <tbody></tbody>
                            </table>
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
            const form = document.getElementById('formMovimientoTerminal');
            const empresa = document.getElementById('empresa');
            const terminal = document.getElementById('terminal_codigo');
            const agenciaCodigo = document.getElementById('agencia_codigo');
            const agenciaNombre = document.getElementById('agencia_nombre');
            const botonBuscar = document.getElementById('btnBuscar');
            const textRenderer = $.fn.dataTable.render.text();
            let consultaAgencia;
            const tabla = $('#table-movimiento-terminal').DataTable({
                data: [],
                responsive: true,
                deferRender: true,
                pageLength: 25,
                lengthMenu: [[25, 50, 100, 250, 500], [25, 50, 100, 250, 500]],
                order: [[4, 'desc']],
                columns: [
                    {
                        data: 'tipo',
                        render: function (value, type) {
                            if (type !== 'display') {
                                return value;
                            }
                            const color = value === 'Tradicional' ? 'bg-primary' : (value === 'Recargas' ? 'bg-success' : 'bg-info');
                            return `<span class="badge ${color}">${textRenderer.display(value)}</span>`;
                        }
                    },
                    { data: 'terminal', render: textRenderer },
                    { data: 'cedula', render: textRenderer },
                    { data: 'empleado', render: textRenderer },
                    { data: 'fecha', render: textRenderer }
                ],
                language: {
                    emptyTable: 'Seleccione los filtros y pulse Buscar para consultar los movimientos.',
                    zeroRecords: 'No se encontraron movimientos.',
                    search: 'Buscar:',
                    lengthMenu: 'Mostrar _MENU_ registros',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                    infoEmpty: 'Sin movimientos',
                    infoFiltered: '(filtrados de _MAX_ registros)',
                    paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' }
                }
            });

            async function buscarAgencia() {
                consultaAgencia?.abort();
                agenciaCodigo.value = '';
                agenciaNombre.value = '';
                if (!empresa.value || !terminal.value.trim()) {
                    return;
                }
                consultaAgencia = new AbortController();
                try {
                    const parametros = new URLSearchParams({ empresa: empresa.value, terminal: terminal.value.trim() });
                    const respuesta = await fetch(@json(route('reportes.movimiento-terminal.agencia')) + '?' + parametros, {
                        headers: { Accept: 'application/json' }, signal: consultaAgencia.signal
                    });
                    if (!respuesta.ok) {
                        return;
                    }
                    const agencia = await respuesta.json();
                    agenciaCodigo.value = agencia?.agencia || '';
                    agenciaNombre.value = agencia?.nombre_agencia || '';
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        agenciaNombre.value = '';
                    }
                }
            }

            empresa.addEventListener('change', buscarAgencia);
            terminal.addEventListener('change', buscarAgencia);
            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                if (document.getElementById('fecha_inicio').value > document.getElementById('fecha_fin').value) {
                    Swal.fire({ title: 'Revise las fechas', text: 'La fecha de inicio no puede ser mayor a la fecha fin.', icon: 'warning' });
                    return;
                }
                botonBuscar.disabled = true;
                botonBuscar.textContent = 'Consultando...';
                tabla.clear().draw();
                try {
                    const parametros = new URLSearchParams(new FormData(form));
                    const respuesta = await fetch(@json(route('reportes.movimiento-terminal.list')) + '?' + parametros, {
                        headers: { Accept: 'application/json' }
                    });
                    const datos = await respuesta.json();
                    if (!respuesta.ok) {
                        throw new Error(Object.values(datos.errors || {}).flat()[0] || datos.message || 'No se pudo consultar el reporte.');
                    }
                    tabla.settings()[0].oLanguage.sEmptyTable = 'No se encontraron movimientos para los filtros seleccionados.';
                    tabla.rows.add(datos).draw();
                } catch (error) {
                    Swal.fire({ title: 'No se pudo consultar', text: error.message, icon: 'error' });
                } finally {
                    botonBuscar.disabled = false;
                    botonBuscar.innerHTML = '<i class="ri-search-line"></i> Buscar';
                }
            });
        });
    </script>
@endsection
