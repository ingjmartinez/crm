@extends('app')

@section('content')
    <style>
        .admin-create-form {
            display: grid;
            grid-template-columns: minmax(170px, 0.8fr) minmax(210px, 1.15fr) minmax(150px, 0.9fr) minmax(190px, 1fr) minmax(135px, 0.65fr) 124px;
            gap: .65rem;
            align-items: end;
        }

        .admin-create-form .form-label {
            font-size: .82rem;
        }

        .admin-create-form .btn {
            min-height: 38px;
        }

        .empleado-activo-row > td {
            background-color: #e8f7ee !important;
        }

        .empleado-inactivo-row > td {
            background-color: #fdecec !important;
        }

        @media (max-width: 1199.98px) {
            .admin-create-form {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .admin-create-form {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Incentivo Administrativo</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('incentivos.index') }}">Incentivos</a></li>
                                    <li class="breadcrumb-item active">Incentivo Administrativo</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Crear registro</h5>
                            </div>
                            <div class="card-body">
                                @if ($errors->any())
                                    <div class="alert alert-danger">
                                        <ul class="mb-0">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <form action="{{ route('incentivos.incentivo-administrativo.store') }}" method="POST" class="admin-create-form">
                                    @csrf
                                    <div>
                                        <label class="form-label mb-1">Grupo</label>
                                        <select name="grupo" id="grupo_create" class="form-select js-grupo-select" data-pct-target="pct_total_create" required>
                                            <option value="">Seleccione</option>
                                            @foreach($posiciones as $posicion)
                                                <option
                                                    value="{{ $posicion->posicion }}"
                                                    data-bono="{{ number_format((float) $posicion->bono_pct, 2, '.', '') }}"
                                                    {{ old('grupo') === $posicion->posicion ? 'selected' : '' }}>
                                                    {{ $posicion->posicion }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label mb-1">Nombre</label>
                                        <input type="text" name="nombre" class="form-control" value="{{ old('nombre') }}" required>
                                    </div>
                                    <div>
                                        <label class="form-label mb-1">Cedula</label>
                                        <input type="text" name="cedula" class="form-control js-cedula-input" value="{{ old('cedula') }}" maxlength="11" pattern="\d{11}" inputmode="numeric">
                                    </div>
                                    <div>
                                        <label class="form-label mb-1">Empresa</label>
                                        <select name="empresa" class="form-select" required>
                                            <option value="">Seleccione</option>
                                            @foreach($empresas as $empresa)
                                                <option value="{{ $empresa }}" {{ old('empresa') === $empresa ? 'selected' : '' }}>
                                                    {{ $empresa }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label mb-1">% Total / Monto fijo</label>
                                        <div class="input-group">
                                            <input type="number" id="pct_total_create" name="pct_total" min="0" max="9999999" step="0.01" class="form-control" value="{{ old('pct_total', 0) }}" required>
                                            <span class="input-group-text js-pct-suffix" data-target="pct_total_create">%</span>
                                        </div>
                                    </div>
                                    <div class="d-grid">
                                        <label class="form-label mb-1">&nbsp;</label>
                                        <button type="submit" class="btn btn-primary">Guardar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <h5 class="card-title mb-0">Listado</h5>
                                        <a href="{{ route('incentivos.incentivo-administrativo.export', request()->only(['buscar_nombre', 'grupo_filter', 'empresa_filter'])) }}" class="btn btn-success btn-sm">Excel</a>
                                    </div>
                                    <form action="{{ route('incentivos.incentivo-administrativo.index') }}" method="GET" class="row g-2" style="max-width: 860px; width: 100%;">
                                        <div class="col-md-4">
                                            <input
                                                type="text"
                                                name="buscar_nombre"
                                                class="form-control form-control-sm"
                                                value="{{ $buscarNombre }}"
                                                placeholder="Buscar por nombre">
                                        </div>
                                        <div class="col-md-3">
                                            <select name="grupo_filter" class="form-select form-select-sm">
                                                <option value="">Todos los grupos</option>
                                                @foreach($posiciones as $posicion)
                                                    <option value="{{ $posicion->posicion }}" {{ $grupoFilter === $posicion->posicion ? 'selected' : '' }}>
                                                        {{ $posicion->posicion }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <select name="empresa_filter" class="form-select form-select-sm">
                                                <option value="">Todas las empresas</option>
                                                @foreach($empresas as $empresa)
                                                    <option value="{{ $empresa }}" {{ $empresaFilter === $empresa ? 'selected' : '' }}>
                                                        {{ $empresa }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-2 d-flex gap-2">
                                            <button type="submit" class="btn btn-primary btn-sm flex-fill">Buscar</button>
                                            @if($buscarNombre !== '' || $grupoFilter !== '' || $empresaFilter !== '')
                                                <a href="{{ route('incentivos.incentivo-administrativo.index') }}" class="btn btn-light btn-sm">Limpiar</a>
                                            @endif
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="min-width: 180px;">Grupo</th>
                                                <th style="min-width: 260px;">Nombre</th>
                                                <th style="min-width: 160px;">Cedula</th>
                                                <th style="min-width: 160px;">Empresa</th>
                                                <th style="min-width: 160px;">% Total / Monto fijo</th>
                                                <th style="min-width: 180px;">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="incentivoAdministrativoTableBody">
                                            @forelse($registros as $registro)
                                                @php
                                                    $empleadoEstado = (string) ($registro->empleado_estado ?? 'no_existe');
                                                    $empleadoActivo = $empleadoEstado === 'activo';
                                                @endphp
                                                <tr class="{{ $empleadoActivo ? 'empleado-activo-row' : 'empleado-inactivo-row' }}">
                                                    <td>
                                                        <form id="form-adm-{{ $registro->id }}" action="{{ route('incentivos.incentivo-administrativo.update', $registro->id) }}" method="POST">
                                                            @csrf
                                                            @method('PUT')
                                                        </form>
                                                        <select
                                                            name="grupo"
                                                            form="form-adm-{{ $registro->id }}"
                                                            class="form-select form-select-sm js-grupo-select"
                                                            data-pct-target="pct_total_{{ $registro->id }}"
                                                            required>
                                                            @php
                                                                $grupoExiste = $posiciones->contains('posicion', $registro->grupo);
                                                            @endphp
                                                            @if(!$grupoExiste)
                                                                <option value="{{ $registro->grupo }}" selected>{{ $registro->grupo }}</option>
                                                            @endif
                                                            @foreach($posiciones as $posicion)
                                                                <option
                                                                    value="{{ $posicion->posicion }}"
                                                                    data-bono="{{ number_format((float) $posicion->bono_pct, 2, '.', '') }}"
                                                                    {{ $registro->grupo === $posicion->posicion ? 'selected' : '' }}>
                                                                    {{ $posicion->posicion }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="text" name="nombre" form="form-adm-{{ $registro->id }}" class="form-control form-control-sm" value="{{ $registro->nombre }}" required>
                                                    </td>
                                                    <td>
                                                        <input type="text" name="cedula" form="form-adm-{{ $registro->id }}" class="form-control form-control-sm js-cedula-input" value="{{ $registro->cedula }}" maxlength="11" pattern="\d{11}" inputmode="numeric">
                                                    </td>
                                                    <td>
                                                        <select name="empresa" form="form-adm-{{ $registro->id }}" class="form-select form-select-sm" required>
                                                            @php
                                                                $empresaExiste = $empresas->contains($registro->empresa);
                                                            @endphp
                                                            @if(!$empresaExiste)
                                                                <option value="{{ $registro->empresa }}" selected>{{ $registro->empresa }}</option>
                                                            @endif
                                                            @foreach($empresas as $empresa)
                                                                <option value="{{ $empresa }}" {{ $registro->empresa === $empresa ? 'selected' : '' }}>
                                                                    {{ $empresa }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <div class="input-group input-group-sm">
                                                            <input type="number" id="pct_total_{{ $registro->id }}" name="pct_total" form="form-adm-{{ $registro->id }}" min="0" max="9999999" step="0.01" class="form-control" value="{{ number_format((float) $registro->pct_total, 2, '.', '') }}" required>
                                                            <span class="input-group-text js-pct-suffix" data-target="pct_total_{{ $registro->id }}">%</span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex gap-1">
                                                            <button type="button" data-form-id="form-adm-{{ $registro->id }}" data-nombre="{{ $registro->nombre }}" class="btn btn-success btn-sm js-confirm-update">Actualizar</button>
                                                            <form action="{{ route('incentivos.incentivo-administrativo.destroy', $registro->id) }}" method="POST" class="js-confirm-delete" data-nombre="{{ $registro->nombre }}">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted">
                                                        @if($buscarNombre !== '' || $grupoFilter !== '' || $empresaFilter !== '')
                                                            No hay registros para los filtros seleccionados.
                                                        @else
                                                            No hay registros disponibles.
                                                        @endif
                                                    </td>
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
@endsection

@section('script')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const successMessage = @json(session('success'));

        const showActionSuccess = successMessage
            && (successMessage.includes('actualizado') || successMessage.includes('eliminado'));

        if (showActionSuccess && typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Listo',
                text: successMessage,
                timer: 2200,
                showConfirmButton: false
            });
        }

        function isMontoFijoGroup(value) {
            return ['4. Operadores', '5. Servs. Tecnicos', '6. Seguridad'].includes(String(value || '').trim());
        }

        function updatePctInputMode(selectEl) {
            const targetId = selectEl.dataset.pctTarget;
            if (!targetId) {
                return false;
            }

            const targetInput = document.getElementById(targetId);
            const suffix = document.querySelector(`.js-pct-suffix[data-target="${targetId}"]`);
            const isMontoFijo = isMontoFijoGroup(selectEl.value);

            if (targetInput) {
                targetInput.max = isMontoFijo ? '9999999' : '100';
            }

            if (suffix) {
                suffix.textContent = isMontoFijo ? 'RD$' : '%';
            }

            return isMontoFijo;
        }

        function syncPctFromGrupo(selectEl) {
            const targetId = selectEl.dataset.pctTarget;
            if (!targetId) {
                return;
            }

            const targetInput = document.getElementById(targetId);
            if (!targetInput) {
                return;
            }

            if (updatePctInputMode(selectEl)) {
                return;
            }

            const option = selectEl.selectedOptions && selectEl.selectedOptions.length > 0
                ? selectEl.selectedOptions[0]
                : null;
            const bono = option ? parseFloat(option.dataset.bono || '0') : 0;

            if (!Number.isNaN(bono)) {
                targetInput.value = bono.toFixed(2);
            }
        }

        document.querySelectorAll('.js-grupo-select').forEach(function (selectEl) {
            updatePctInputMode(selectEl);
            selectEl.addEventListener('change', function () {
                syncPctFromGrupo(selectEl);
            });
        });

        document.querySelectorAll('.js-cedula-input').forEach(function (input) {
            input.addEventListener('input', function () {
                input.value = input.value.replace(/\D/g, '').slice(0, 11);
            });
        });

        function getJsonErrorMessage(data, fallback) {
            if (data && data.message) {
                return data.message;
            }

            if (data && data.errors) {
                return Object.values(data.errors).flat().join('\n');
            }

            return fallback;
        }

        function updateRegistro(button, form) {
            const formData = new FormData(form);
            const originalText = button.textContent;

            button.disabled = true;
            button.textContent = 'Actualizando...';

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    return response.json()
                        .catch(function () {
                            return null;
                        })
                        .then(function (data) {
                            if (!response.ok) {
                                throw new Error(getJsonErrorMessage(data, 'No se pudo actualizar el registro.'));
                            }

                            return data;
                        });
                })
                .then(function (data) {
                    const registro = data.registro || {};

                    button.dataset.nombre = registro.nombre || button.dataset.nombre || '';
                    const deleteForm = button.closest('tr')?.querySelector('.js-confirm-delete');
                    if (deleteForm && registro.nombre) {
                        deleteForm.dataset.nombre = registro.nombre;
                    }

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Listo',
                            text: data.message || 'Registro actualizado correctamente.',
                            timer: 900,
                            showConfirmButton: false
                        }).then(function () {
                            window.location.reload();
                        });
                        return;
                    }

                    window.location.reload();
                })
                .catch(function (error) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message || 'No se pudo actualizar el registro.'
                        });
                        return;
                    }

                    alert(error.message || 'No se pudo actualizar el registro.');
                })
                .finally(function () {
                    button.disabled = false;
                    button.textContent = originalText;
                });
        }

        document.querySelectorAll('.js-confirm-update').forEach(function (button) {
            button.addEventListener('click', function () {
                const form = document.getElementById(button.dataset.formId);
                if (!form) {
                    return;
                }

                if (typeof Swal === 'undefined') {
                    if (confirm(`Seguro que deseas actualizar a ${button.dataset.nombre || 'este usuario'}?`)) {
                        updateRegistro(button, form);
                    }

                    return;
                }

                Swal.fire({
                    icon: 'question',
                    title: 'Actualizar registro',
                    text: `Seguro que deseas actualizar a ${button.dataset.nombre || 'este usuario'}?`,
                    showCancelButton: true,
                    confirmButtonText: 'Si, actualizar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#0ab39c',
                    cancelButtonColor: '#74788d'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        updateRegistro(button, form);
                    }
                });
            });
        });

        function showEmptyRowIfNeeded() {
            const tbody = document.getElementById('incentivoAdministrativoTableBody');
            if (!tbody || tbody.querySelector('tr')) {
                return;
            }

            const row = document.createElement('tr');
            row.innerHTML = '<td colspan="6" class="text-center text-muted">No hay registros para los filtros seleccionados.</td>';
            tbody.appendChild(row);
        }

        function deleteRegistro(form) {
            const button = form.querySelector('button[type="submit"]');
            const row = form.closest('tr');
            const formData = new FormData(form);

            if (button) {
                button.disabled = true;
                button.textContent = 'Eliminando...';
            }

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('No se pudo eliminar el registro.');
                    }

                    return response.json();
                })
                .then(function (data) {
                    if (row) {
                        row.remove();
                        showEmptyRowIfNeeded();
                    }

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Listo',
                            text: data.message || 'Registro eliminado correctamente.',
                            timer: 1800,
                            showConfirmButton: false
                        });
                    }
                })
                .catch(function (error) {
                    if (button) {
                        button.disabled = false;
                        button.textContent = 'Eliminar';
                    }

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message || 'No se pudo eliminar el registro.'
                        });
                        return;
                    }

                    alert(error.message || 'No se pudo eliminar el registro.');
                });
        }

        document.querySelectorAll('.js-confirm-delete').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();

                if (typeof Swal === 'undefined') {
                    if (confirm(`Seguro que deseas eliminar a ${form.dataset.nombre || 'este usuario'}?`)) {
                        deleteRegistro(form);
                    }

                    return;
                }

                Swal.fire({
                    icon: 'warning',
                    title: 'Eliminar registro',
                    text: `Seguro que deseas eliminar a ${form.dataset.nombre || 'este usuario'}?`,
                    showCancelButton: true,
                    confirmButtonText: 'Si, eliminar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#f06548',
                    cancelButtonColor: '#74788d'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        deleteRegistro(form);
                    }
                });
            });
        });
    });
</script>
@endsection
