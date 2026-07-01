@extends('app')

@section('content')
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

                                <form action="{{ route('incentivos.incentivo-administrativo.store') }}" method="POST" class="row g-2">
                                    @csrf
                                    <div class="col-md-3">
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
                                    <div class="col-md-3">
                                        <label class="form-label mb-1">Nombre</label>
                                        <input type="text" name="nombre" class="form-control" value="{{ old('nombre') }}" required>
                                    </div>
                                    <div class="col-md-3">
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
                                    <div class="col-md-2">
                                        <label class="form-label mb-1">% Total / Monto fijo</label>
                                        <div class="input-group">
                                            <input type="number" id="pct_total_create" name="pct_total" min="0" max="9999999" step="0.01" class="form-control" value="{{ old('pct_total', 0) }}" required>
                                            <span class="input-group-text js-pct-suffix" data-target="pct_total_create">%</span>
                                        </div>
                                    </div>
                                    <div class="col-md-1 d-grid">
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
                                <h5 class="card-title mb-0">Listado</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="min-width: 180px;">Grupo</th>
                                                <th style="min-width: 260px;">Nombre</th>
                                                <th style="min-width: 160px;">Empresa</th>
                                                <th style="min-width: 160px;">% Total / Monto fijo</th>
                                                <th style="min-width: 180px;">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($registros as $registro)
                                                <tr>
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
                                                    <td colspan="5" class="text-center text-muted">No hay registros disponibles.</td>
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

        document.querySelectorAll('.js-confirm-update').forEach(function (button) {
            button.addEventListener('click', function () {
                const form = document.getElementById(button.dataset.formId);
                if (!form) {
                    return;
                }

                if (typeof Swal === 'undefined') {
                    form.submit();
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
                        form.submit();
                    }
                });
            });
        });

        document.querySelectorAll('.js-confirm-delete').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (form.dataset.confirmed === '1') {
                    return;
                }

                event.preventDefault();

                if (typeof Swal === 'undefined') {
                    form.dataset.confirmed = '1';
                    form.submit();
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
                        form.dataset.confirmed = '1';
                        form.submit();
                    }
                });
            });
        });
    });
</script>
@endsection
