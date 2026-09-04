@extends('app')

@section('content')
    <style>
        .admin-create-form {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
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

        .employee-picker {
            position: relative;
        }

        .employee-results {
            position: absolute;
            z-index: 1050;
            top: calc(100% + 2px);
            right: 0;
            left: 0;
            display: none;
            max-height: 320px;
            overflow-y: auto;
            border: 1px solid #ced4da;
            border-radius: .375rem;
            background: #fff;
            box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .12);
        }

        .employee-results.is-open {
            display: block;
        }

        .employee-option {
            display: block;
            width: 100%;
            padding: .55rem .75rem;
            border: 0;
            border-bottom: 1px solid #eef0f2;
            background: #fff;
            text-align: left;
        }

        .employee-option:hover,
        .employee-option:focus {
            background: #f3f6f9;
        }

        .employee-option:last-child {
            border-bottom: 0;
        }

        .employee-result-message {
            padding: .75rem;
            color: #878a99;
            text-align: center;
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

                                @php
                                    $registroEdicionId = (int) old('registro_id', 0);
                                @endphp
                                <form id="admin_create_form"
                                    action="{{ $registroEdicionId ? route('incentivos.incentivo-administrativo.update', $registroEdicionId) : route('incentivos.incentivo-administrativo.store') }}"
                                    method="POST" class="admin-create-form">
                                    @csrf
                                    <input type="hidden" name="_method" id="admin_form_method" value="PUT" {{ $registroEdicionId ? '' : 'disabled' }}>
                                    <input type="hidden" name="registro_id" id="admin_record_id" value="{{ $registroEdicionId ?: '' }}">
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
                                        <label class="form-label mb-1">Empresa</label>
                                        <select name="empresa" id="empresa_create" class="form-select" required>
                                            <option value="">Seleccione</option>
                                            @foreach($empresas as $empresa)
                                                <option value="{{ $empresa }}"
                                                    data-company-id="{{ $empresa === 'Consorcio Joselito' ? '168' : '169' }}"
                                                    {{ old('empresa') === $empresa ? 'selected' : '' }}>
                                                    {{ $empresa === 'Consorcio Joselito' ? 'Grupo Joselito' : $empresa }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label mb-1" for="departamento_create">Departamento</label>
                                        <select name="departamento" id="departamento_create" class="form-select" required disabled>
                                            <option value="">Seleccione primero una empresa</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label mb-1" for="empleado_search">Empleado activo</label>
                                        <div class="employee-picker" id="employee_picker">
                                            <input type="search" id="empleado_search" class="form-control"
                                                value="{{ old('nombre') }}" placeholder="Seleccione un departamento" autocomplete="off"
                                                aria-autocomplete="list" aria-controls="empleado_results" required disabled>
                                            <input type="hidden" name="empleado_id" id="empleado_id" value="{{ old('empleado_id') }}">
                                            <div id="empleado_results" class="employee-results" role="listbox"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="form-label mb-1">Nombre</label>
                                        <input type="text" name="nombre" id="nombre_create" class="form-control bg-light" value="{{ old('nombre') }}" readonly required>
                                    </div>
                                    <div>
                                        <label class="form-label mb-1">Cedula</label>
                                        <input type="text" name="cedula" id="cedula_create" class="form-control bg-light" value="{{ old('cedula') }}" readonly required>
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
                                        <button type="submit" id="admin_submit_button" class="btn btn-primary">
                                            Guardar
                                        </button>
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
                                        <a href="{{ route('incentivos.incentivo-administrativo.export', request()->only(['buscar_nombre', 'grupo_filter', 'empresa_filter', 'estatus_filter'])) }}" class="btn btn-success btn-sm">Excel</a>
                                    </div>
                                    <form action="{{ route('incentivos.incentivo-administrativo.index') }}" method="GET" class="row g-2" style="max-width: 1040px; width: 100%;">
                                        <div class="col-md-3">
                                            <input
                                                type="text"
                                                name="buscar_nombre"
                                                class="form-control form-control-sm"
                                                value="{{ $buscarNombre }}"
                                                placeholder="Buscar por nombre o cédula">
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
                                        <div class="col-md-2">
                                            <select name="empresa_filter" class="form-select form-select-sm">
                                                <option value="">Todas las empresas</option>
                                                @foreach($empresas as $empresa)
                                                    <option value="{{ $empresa }}" {{ $empresaFilter === $empresa ? 'selected' : '' }}>
                                                        {{ $empresa }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select name="estatus_filter" class="form-select form-select-sm">
                                                <option value="">Todos los estatus</option>
                                                <option value="activo" {{ $estatusFilter === 'activo' ? 'selected' : '' }}>Activos</option>
                                                <option value="no_activo" {{ $estatusFilter === 'no_activo' ? 'selected' : '' }}>No activos</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2 d-flex gap-2">
                                            <button type="submit" class="btn btn-primary btn-sm flex-fill">Buscar</button>
                                            @if($buscarNombre !== '' || $grupoFilter !== '' || $empresaFilter !== '' || $estatusFilter !== '')
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
                                                    <td>{{ $registro->grupo }}</td>
                                                    <td>{{ $registro->nombre }}</td>
                                                    <td>{{ $registro->cedula }}</td>
                                                    <td>{{ $registro->empresa === 'Consorcio Joselito' ? 'Grupo Joselito' : $registro->empresa }}</td>
                                                    <td class="text-end">
                                                        {{ number_format((float) $registro->pct_total, 2) }}
                                                        {{ in_array($registro->grupo, ['4. Operadores', '5. Servs. Tecnicos', '6. Seguridad'], true) ? 'RD$' : '%' }}
                                                    </td>
                                                    <td>
                                                        <div class="d-flex gap-1">
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
                                                        @if($buscarNombre !== '' || $grupoFilter !== '' || $empresaFilter !== '' || $estatusFilter !== '')
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
        const employeesUrl = @json(route('incentivos.incentivo-administrativo.empleados'));
        const departments = @json($departamentos);
        const initialDepartment = @json(old('departamento', ''));
        const departmentSelect = document.getElementById('departamento_create');
        const employeeSearch = document.getElementById('empleado_search');
        const employeeId = document.getElementById('empleado_id');
        const employeeResults = document.getElementById('empleado_results');
        const employeePicker = document.getElementById('employee_picker');
        const employeeName = document.getElementById('nombre_create');
        const employeeCedula = document.getElementById('cedula_create');
        const employeeCompany = document.getElementById('empresa_create');
        const adminForm = document.getElementById('admin_create_form');
        const adminFormMethod = document.getElementById('admin_form_method');
        const adminRecordId = document.getElementById('admin_record_id');
        const adminSubmitButton = document.getElementById('admin_submit_button');
        const groupCreate = document.getElementById('grupo_create');
        const pctTotalCreate = document.getElementById('pct_total_create');
        const adminBaseUrl = @json(url('/incentivos/incentivo-administrativo'));
        let employeeSearchTimer;
        let employeeRequestController;

        function closeEmployeeResults() {
            employeeResults.classList.remove('is-open');
        }

        function showEmployeeMessage(message) {
            employeeResults.innerHTML = '';
            const element = document.createElement('div');
            element.className = 'employee-result-message';
            element.textContent = message;
            employeeResults.appendChild(element);
            employeeResults.classList.add('is-open');
        }

        function clearSelectedEmployee(clearSearch = true) {
            setCreateMode();
            employeeId.value = '';
            employeeName.value = '';
            employeeCedula.value = '';

            if (clearSearch) {
                employeeSearch.value = '';
            }
        }

        function setCreateMode() {
            adminForm.action = adminBaseUrl;
            adminFormMethod.disabled = true;
            adminRecordId.value = '';
            adminSubmitButton.textContent = 'Guardar';
        }

        function setUpdateMode(incentivo) {
            adminForm.action = `${adminBaseUrl}/${incentivo.id}`;
            adminFormMethod.disabled = false;
            adminRecordId.value = incentivo.id;
            adminSubmitButton.textContent = 'Guardar';

            groupCreate.value = incentivo.grupo;
            updatePctInputMode(groupCreate);
            pctTotalCreate.value = incentivo.pct_total;
        }

        function selectEmployee(empleado) {
            setCreateMode();
            employeeId.value = empleado.id;
            employeeSearch.value = empleado.nombre;
            employeeName.value = empleado.nombre;
            employeeCedula.value = empleado.cedula;
            closeEmployeeResults();

            if (!empleado.incentivo) {
                return;
            }

            setUpdateMode(empleado.incentivo);
        }

        function submitAdministrativeForm() {
            adminSubmitButton.disabled = true;
            adminSubmitButton.textContent = 'Guardando...';
            HTMLFormElement.prototype.submit.call(adminForm);
        }

        adminForm.addEventListener('submit', function (event) {
            if (!adminRecordId.value) {
                return;
            }

            event.preventDefault();

            if (typeof Swal === 'undefined') {
                if (confirm(`Este empleado ya existe en ${employeeCompany.value}. ¿Desea actualizarlo?`)) {
                    submitAdministrativeForm();
                }
                return;
            }

            Swal.fire({
                icon: 'question',
                title: 'Actualizar empleado',
                text: `Este empleado ya está registrado en ${employeeCompany.value}. ¿Desea guardar los cambios?`,
                showCancelButton: true,
                confirmButtonText: 'Sí, actualizar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#0ab39c',
                cancelButtonColor: '#74788d'
            }).then(function (result) {
                if (result.isConfirmed) {
                    submitAdministrativeForm();
                }
            });
        });

        function selectedCompanyId() {
            const option = employeeCompany.selectedOptions[0];
            return option ? String(option.dataset.companyId || '') : '';
        }

        function populateDepartments(selectedDepartment = '') {
            const companyId = selectedCompanyId();
            departmentSelect.innerHTML = '';

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = companyId ? 'Seleccione' : 'Seleccione primero una empresa';
            departmentSelect.appendChild(placeholder);

            const visibleDepartments = new Set();
            departments.forEach(function (item) {
                if (String(item.companyid) !== companyId || visibleDepartments.has(item.departamento)) {
                    return;
                }

                visibleDepartments.add(item.departamento);
                const option = document.createElement('option');
                option.value = item.departamento;
                option.textContent = item.departamento;
                option.selected = item.departamento === selectedDepartment;
                departmentSelect.appendChild(option);
            });

            departmentSelect.disabled = !companyId;
        }

        function renderEmployees(empleados) {
            employeeResults.innerHTML = '';

            if (!empleados.length) {
                showEmployeeMessage('No se encontraron empleados activos.');
                return;
            }

            empleados.forEach(function (empleado) {
                const option = document.createElement('button');
                const name = document.createElement('span');
                const detail = document.createElement('small');

                option.type = 'button';
                option.className = 'employee-option';
                option.setAttribute('role', 'option');
                name.className = 'd-block fw-semibold';
                name.textContent = empleado.nombre;
                detail.className = 'd-block text-muted';
                detail.textContent = `${empleado.cedula} · ${empleado.empresa_nombre} · ID ${empleado.empleadoid}`;
                option.append(name, detail);
                option.addEventListener('click', function () {
                    selectEmployee(empleado);
                });
                employeeResults.appendChild(option);
            });

            employeeResults.classList.add('is-open');
        }

        function loadEmployees() {
            const company = employeeCompany.value;
            const department = departmentSelect.value;

            if (!company || !department) {
                closeEmployeeResults();
                return;
            }

            if (employeeRequestController) {
                employeeRequestController.abort();
            }

            employeeRequestController = new AbortController();
            showEmployeeMessage('Buscando empleados...');

            const params = new URLSearchParams({
                empresa: company,
                departamento: department,
                buscar: employeeSearch.value.trim()
            });

            fetch(`${employeesUrl}?${params}`, {
                headers: { 'Accept': 'application/json' },
                signal: employeeRequestController.signal
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('No se pudieron consultar los empleados.');
                    }

                    return response.json();
                })
                .then(function (data) {
                    renderEmployees(data.data || []);
                })
                .catch(function (error) {
                    if (error.name !== 'AbortError') {
                        showEmployeeMessage(error.message || 'No se pudieron consultar los empleados.');
                    }
                });
        }

        employeeCompany.addEventListener('change', function () {
            clearTimeout(employeeSearchTimer);

            if (employeeRequestController) {
                employeeRequestController.abort();
            }

            clearSelectedEmployee();
            populateDepartments();
            employeeSearch.disabled = true;
            employeeSearch.placeholder = 'Seleccione un departamento';
            closeEmployeeResults();
        });

        departmentSelect.addEventListener('change', function () {
            clearTimeout(employeeSearchTimer);
            clearSelectedEmployee();
            employeeSearch.disabled = !departmentSelect.value;
            employeeSearch.placeholder = departmentSelect.value
                ? 'Buscar por nombre, cédula o ID'
                : 'Seleccione un departamento';

            if (departmentSelect.value) {
                employeeSearch.focus();
            } else {
                closeEmployeeResults();
            }
        });

        employeeSearch.addEventListener('focus', function () {
            if (departmentSelect.value) {
                loadEmployees();
            }
        });

        employeeSearch.addEventListener('input', function () {
            clearSelectedEmployee(false);
            clearTimeout(employeeSearchTimer);
            employeeSearchTimer = setTimeout(loadEmployees, 300);
        });

        document.addEventListener('click', function (event) {
            if (!employeePicker.contains(event.target)) {
                closeEmployeeResults();
            }
        });

        populateDepartments(initialDepartment);

        if (employeeCompany.value && departmentSelect.value) {
            employeeSearch.disabled = false;
            employeeSearch.placeholder = 'Buscar por nombre, cédula o ID';
        }

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
