@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Coordinador / Operador</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="javascript: void(0);">Administración</a></li>
                                    <li class="breadcrumb-item active">Coordinador / Operador</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Listado</h5>
                                <a href="{{ route('coordinador-operador.create') }}" class="btn btn-primary btn-sm">
                                    <i class="ri-add-line align-bottom me-1"></i>Nuevo
                                </a>
                            </div>
                            <div class="card-body">
                                @if(session('success'))
                                    <div class="alert alert-success">{{ session('success') }}</div>
                                @endif

                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center" style="width:80px;">ID</th>
                                                <th>Nombre</th>
                                                <th>Apellido</th>
                                                <th>Correo</th>
                                                <th>Cédula</th>
                                                <th>Teléfono</th>
                                                <th>Puesto</th>
                                                <th class="text-center">Agencias Asignadas</th>
                                                <th class="text-center" style="width:140px;">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($registros as $item)
                                                <tr>
                                                    <td class="text-center">{{ $item->id }}</td>
                                                    <td>{{ $item->nombre }}</td>
                                                    <td>{{ $item->apellido }}</td>
                                                    <td>{{ $item->correo }}</td>
                                                    <td>{{ $item->cedula }}</td>
                                                    <td>{{ $item->telefono }}</td>
                                                    <td class="text-capitalize">{{ $item->puesto }}</td>
                                                    <td class="text-center">
                                                        <button
                                                            type="button"
                                                            class="btn btn-info btn-sm btn-ver-agencias"
                                                            title="Ver agencias asignadas"
                                                            data-nombre="{{ $item->nombre }} {{ $item->apellido }}"
                                                            data-agencias='@json($item->agencias->map(fn($agencia) => ['terminal' => $agencia->terminal, 'nombre_agencia' => $agencia->nombre_agencia, 'agencia' => $agencia->agencia])->values())'>
                                                            {{ $item->agencias_count }}
                                                        </button>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="d-flex justify-content-center gap-1">
                                                            <button
                                                                type="button"
                                                                class="btn btn-info btn-sm btn-asignar-agencias"
                                                                title="Asignar agencias"
                                                                data-id="{{ $item->id }}"
                                                                data-nombre="{{ $item->nombre }} {{ $item->apellido }}"
                                                                data-asignadas='@json($item->agencias->pluck('id')->values())'>
                                                                <i class="ri-building-line"></i>
                                                            </button>
                                                            <a href="{{ route('coordinador-operador.edit', $item->id) }}" class="btn btn-success btn-sm" title="Editar">
                                                                <i class="ri-pencil-line"></i>
                                                            </a>
                                                            <form action="{{ route('coordinador-operador.destroy', $item->id) }}" method="POST" onsubmit="return confirm('¿Está seguro de eliminar este registro?')">
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
                                                    <td colspan="9" class="text-center text-muted">No hay registros disponibles.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-3">
                                    {{ $registros->links() }}
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

                        <div class="border rounded p-3" style="max-height: 380px; overflow-y: auto;">
                            <div class="row g-2" id="listaAgenciasAsignacion">
                                @forelse($agencias as $agencia)
                                    <div class="col-12 col-md-6 item-agencia" data-terminal="{{ strtolower($agencia->terminal ?? '') }}" data-texto="{{ strtolower(($agencia->terminal ?? '') . ' ' . ($agencia->nombre_agencia ?? '')) }}">
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
                        <button type="submit" class="btn btn-primary">Guardar asignación</button>
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
                    <p class="mb-2">Usuario: <strong id="nombreVerAgencias">-</strong></p>
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

                form.action = `/coordinador-operador/${id}/asignar-agencias`;
                nombreAsignacion.textContent = nombre;

                checkboxes.forEach(function (checkbox) {
                    checkbox.checked = asignadas.includes(Number(checkbox.value));
                });

                if (buscarTerminalAgencia) {
                    buscarTerminalAgencia.value = '';
                    filtrarAgenciasModal();
                }

                modal.show();
            });
        });

        if (buscarTerminalAgencia) {
            buscarTerminalAgencia.addEventListener('input', filtrarAgenciasModal);
        }

        document.querySelectorAll('.btn-ver-agencias').forEach(function (button) {
            button.addEventListener('click', function () {
                const nombre = this.dataset.nombre || '-';
                const agencias = JSON.parse(this.dataset.agencias || '[]');

                nombreVerAgencias.textContent = nombre;
                if (contadorVerAgencias) {
                    contadorVerAgencias.textContent = String(agencias.length || 0);
                }

                if (!agencias.length) {
                    contenidoVerAgencias.innerHTML = '<p class="text-muted mb-0">No tiene agencias asignadas.</p>';
                } else {
                    contenidoVerAgencias.innerHTML = agencias.map(function (agencia) {
                        const terminal = agencia.terminal || '-';
                        const nombreAgencia = agencia.nombre_agencia || agencia.agencia || 'Sin nombre';
                        return `<div class="py-1 border-bottom">${terminal} - ${nombreAgencia}</div>`;
                    }).join('');
                }

                modalVerAgencias.show();
            });
        });
    });
</script>
@endsection
