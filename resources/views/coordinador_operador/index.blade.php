@extends('app')

@section('content')
    <style>
        .coordinador-create-form {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .65rem;
            align-items: end;
        }

        .coordinador-create-form .form-label {
            font-size: .82rem;
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

        .employee-result-message {
            padding: .75rem;
            color: #878a99;
            text-align: center;
        }

        @media (max-width: 991.98px) {
            .coordinador-create-form {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .coordinador-create-form {
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
                            <h4 class="mb-sm-0">Coordinador</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('mantenimiento.index') }}">Mantenimientos</a></li>
                                    <li class="breadcrumb-item active">Coordinador</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Crear coordinador</h5>
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
                                <form id="coordinador_create_form"
                                    action="{{ $registroEdicionId ? route('coordinador-operador.update', $registroEdicionId) : route('coordinador-operador.store') }}"
                                    method="POST" class="coordinador-create-form">
                                    @csrf
                                    <input type="hidden" name="_method" id="coordinador_form_method" value="PUT" {{ $registroEdicionId ? '' : 'disabled' }}>
                                    <input type="hidden" name="registro_id" id="coordinador_record_id" value="{{ $registroEdicionId ?: '' }}">

                                    <div>
                                        <label class="form-label mb-1" for="empresa_create">Empresa</label>
                                        <select name="empresa" id="empresa_create" class="form-select" required>
                                            <option value="">Seleccione</option>
                                            @foreach($empresas as $empresa)
                                                <option value="{{ $empresa }}"
                                                    data-company-id="{{ $empresa === 'Consorcio Joselito' ? '168' : '169' }}"
                                                    @selected(old('empresa') === $empresa)>
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
                                                value="{{ old('nombre') }}" placeholder="Seleccione un departamento"
                                                autocomplete="off" aria-autocomplete="list" aria-controls="empleado_results"
                                                required disabled>
                                            <input type="hidden" name="empleado_id" id="empleado_id" value="{{ old('empleado_id') }}">
                                            <div id="empleado_results" class="employee-results" role="listbox"></div>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="form-label mb-1" for="nombre_create">Nombre</label>
                                        <input type="text" name="nombre" id="nombre_create" class="form-control bg-light"
                                            value="{{ old('nombre') }}" readonly required>
                                    </div>

                                    <div>
                                        <label class="form-label mb-1" for="cedula_create">Cédula</label>
                                        <input type="text" name="cedula" id="cedula_create" class="form-control bg-light"
                                            value="{{ old('cedula') }}" readonly required>
                                    </div>

                                    <div>
                                        <label class="form-label mb-1" for="correo_create">Correo</label>
                                        <input type="email" name="correo" id="correo_create" class="form-control bg-light"
                                            value="{{ old('correo') }}" readonly>
                                    </div>

                                    <div class="d-grid">
                                        <label class="form-label mb-1">&nbsp;</label>
                                        <button type="submit" id="coordinador_submit_button" class="btn btn-primary">Guardar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Listado</h5>
                            </div>
                            <div class="card-body">
                                @if(session('success'))
                                    <div class="alert alert-success">{{ session('success') }}</div>
                                @endif

                                @if(session('error'))
                                    <div class="alert alert-danger">{{ session('error') }}</div>
                                @endif

                                <form method="GET" action="{{ route('coordinador-operador.index') }}" class="row g-2 align-items-end mb-3">
                                    <div class="col-12 col-md-5 col-lg-4">
                                        <label for="buscarCoordinador" class="form-label">Buscar coordinador</label>
                                        <input type="text" id="buscarCoordinador" name="buscar" class="form-control" value="{{ $buscar ?? '' }}" placeholder="Nombre, apellido o cedula...">
                                    </div>
                                    <div class="col-auto">
                                        <button type="submit" class="btn btn-primary">Buscar</button>
                                    </div>
                                    @if(!empty($buscar))
                                        <div class="col-auto">
                                            <a href="{{ route('coordinador-operador.index') }}" class="btn btn-light">Limpiar</a>
                                        </div>
                                    @endif
                                </form>

                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle mb-0" id="tablaCoordinadorOperador">
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
                                                <tr data-search="{{ strtolower(trim($item->nombre . ' ' . $item->apellido . ' ' . $item->cedula . ' ' . preg_replace('/\D+/', '', (string) $item->cedula))) }}">
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
                                                                data-coordinador-id="{{ $item->id }}"
                                                                data-nombre="{{ $item->nombre }} {{ $item->apellido }}"
                                                                data-asignadas='@json($item->agencias->pluck('id')->values())'>
                                                                <i class="ri-building-line"></i>
                                                            </button>
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
                    <input type="hidden" name="confirmar_reasignacion" id="confirmarReasignacion" value="0">
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
                            <label for="terminalesMasivos" class="form-label mb-1">Asignación masiva por terminal</label>
                            <textarea id="terminalesMasivos" class="form-control" rows="4" placeholder="Pega aqui los codigos de terminal desde Excel o TXT (uno por linea, columna o separados por coma)"></textarea>
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
        const asignacionesAgencia = @json($asignacionesAgencia ?? []);
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
        const buscarCoordinador = document.getElementById('buscarCoordinador');
        const filasTablaCoordinador = document.querySelectorAll('#tablaCoordinadorOperador tbody tr');
        const terminalesMasivos = document.getElementById('terminalesMasivos');
        const btnAplicarTerminalesMasivos = document.getElementById('btnAplicarTerminalesMasivos');
        const btnLimpiarTerminalesMasivos = document.getElementById('btnLimpiarTerminalesMasivos');
        const resumenTerminalesMasivos = document.getElementById('resumenTerminalesMasivos');
        const detalleTerminalesNoCoinciden = document.getElementById('detalleTerminalesNoCoinciden');
        const confirmarReasignacion = document.getElementById('confirmarReasignacion');
        const employeesUrl = @json(route('coordinador-operador.empleados'));
        const departments = @json($departamentos);
        const initialDepartment = @json(old('departamento', ''));
        const coordinatorBaseUrl = @json(url('/coordinador-operador'));
        const coordinatorForm = document.getElementById('coordinador_create_form');
        const coordinatorFormMethod = document.getElementById('coordinador_form_method');
        const coordinatorRecordId = document.getElementById('coordinador_record_id');
        const coordinatorSubmitButton = document.getElementById('coordinador_submit_button');
        const employeeCompany = document.getElementById('empresa_create');
        const departmentSelect = document.getElementById('departamento_create');
        const employeePicker = document.getElementById('employee_picker');
        const employeeSearch = document.getElementById('empleado_search');
        const employeeId = document.getElementById('empleado_id');
        const employeeResults = document.getElementById('empleado_results');
        const employeeName = document.getElementById('nombre_create');
        const employeeCedula = document.getElementById('cedula_create');
        const employeeEmail = document.getElementById('correo_create');
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

        function setCoordinatorCreateMode() {
            coordinatorForm.action = coordinatorBaseUrl;
            coordinatorFormMethod.disabled = true;
            coordinatorRecordId.value = '';
            coordinatorSubmitButton.textContent = 'Guardar';
        }

        function setCoordinatorUpdateMode(coordinator) {
            coordinatorForm.action = `${coordinatorBaseUrl}/${coordinator.id}`;
            coordinatorFormMethod.disabled = false;
            coordinatorRecordId.value = coordinator.id;
            coordinatorSubmitButton.textContent = 'Actualizar';
        }

        function clearSelectedEmployee(clearSearch = true) {
            setCoordinatorCreateMode();
            employeeId.value = '';
            employeeName.value = '';
            employeeCedula.value = '';
            employeeEmail.value = '';

            if (clearSearch) {
                employeeSearch.value = '';
            }
        }

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

        function selectEmployee(employee) {
            setCoordinatorCreateMode();
            employeeId.value = employee.id;
            employeeSearch.value = employee.nombre;
            employeeName.value = employee.nombre;
            employeeCedula.value = employee.cedula;
            employeeEmail.value = employee.correo || '';
            closeEmployeeResults();

            if (employee.coordinador) {
                setCoordinatorUpdateMode(employee.coordinador);
            }
        }

        function renderEmployees(employees) {
            employeeResults.innerHTML = '';

            if (!employees.length) {
                showEmployeeMessage('No se encontraron empleados activos.');
                return;
            }

            employees.forEach(function (employee) {
                const option = document.createElement('button');
                const name = document.createElement('span');
                const detail = document.createElement('small');

                option.type = 'button';
                option.className = 'employee-option';
                option.setAttribute('role', 'option');
                name.className = 'd-block fw-semibold';
                name.textContent = employee.nombre;
                detail.className = 'd-block text-muted';
                detail.textContent = `${employee.cedula} · ${employee.empresa_nombre} · ID ${employee.empleadoid}`;
                option.append(name, detail);
                option.addEventListener('click', function () {
                    selectEmployee(employee);
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

        coordinatorForm.addEventListener('submit', function (event) {
            if (!coordinatorRecordId.value) {
                coordinatorSubmitButton.disabled = true;
                coordinatorSubmitButton.textContent = 'Guardando...';
                return;
            }

            event.preventDefault();
            const submitUpdate = function () {
                coordinatorSubmitButton.disabled = true;
                coordinatorSubmitButton.textContent = 'Actualizando...';
                HTMLFormElement.prototype.submit.call(coordinatorForm);
            };

            if (typeof Swal === 'undefined') {
                if (confirm('Este empleado ya está registrado como coordinador. ¿Desea actualizarlo?')) {
                    submitUpdate();
                }
                return;
            }

            Swal.fire({
                icon: 'question',
                title: 'Actualizar coordinador',
                text: 'Este empleado ya está registrado. ¿Desea guardar los cambios?',
                showCancelButton: true,
                confirmButtonText: 'Sí, actualizar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#0ab39c',
                cancelButtonColor: '#74788d'
            }).then(function (result) {
                if (result.isConfirmed) {
                    submitUpdate();
                }
            });
        });

        populateDepartments(initialDepartment);

        if (employeeCompany.value && departmentSelect.value) {
            employeeSearch.disabled = false;
            employeeSearch.placeholder = 'Buscar por nombre, cédula o ID';
        }

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

        function aplicarTerminalesMasivos() {
            const terminales = extraerTerminalesPegadas(terminalesMasivos?.value || '');

            if (!terminales.length) {
                if (resumenTerminalesMasivos) {
                    resumenTerminalesMasivos.textContent = 'No se detectaron terminales para procesar.';
                }
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

            if (resumenTerminalesMasivos) {
                resumenTerminalesMasivos.textContent = `Terminales procesadas: ${terminales.length}. Coincidencias marcadas: ${encontradas}.`;
            }

            const noCoinciden = terminales.filter(function (terminal) {
                return !terminalesDisponibles.has(terminal);
            });

            if (detalleTerminalesNoCoinciden) {
                if (!noCoinciden.length) {
                    detalleTerminalesNoCoinciden.innerHTML = '<small class="text-success">Detalle: todos los codigos coinciden con la tabla de agencias.</small>';
                } else {
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
            }
        }

        function limpiarTerminalesMasivos() {
            if (terminalesMasivos) {
                terminalesMasivos.value = '';
            }
            if (resumenTerminalesMasivos) {
                resumenTerminalesMasivos.textContent = '';
            }
            if (detalleTerminalesNoCoinciden) {
                detalleTerminalesNoCoinciden.innerHTML = '';
            }
        }

        function obtenerConflictosSeleccionados(coordinadorIdActual) {
            const conflictos = [];
            const asignadasIniciales = new Set(
                JSON.parse(form?.dataset?.asignadasIniciales || '[]').map(function (id) {
                    return Number(id);
                })
            );

            itemsAgencia.forEach(function (item) {
                const checkbox = item.querySelector('.checkbox-agencia');
                if (!checkbox || !checkbox.checked) {
                    return;
                }

                const agenciaId = Number(item.dataset.agenciaId || checkbox.value || 0);

                // Solo validamos agencias nuevas marcadas en esta asignacion.
                if (asignadasIniciales.has(agenciaId)) {
                    return;
                }

                const terminal = item.dataset.terminal || '-';
                const asignados = Array.isArray(asignacionesAgencia[String(agenciaId)])
                    ? asignacionesAgencia[String(agenciaId)]
                    : [];

                const asignadosOtros = asignados.filter(function (owner) {
                    return Number(owner.id) !== Number(coordinadorIdActual);
                });

                if (asignadosOtros.length) {
                    conflictos.push({
                        terminal: terminal || '-',
                        asignadosOtros: asignadosOtros,
                    });
                }
            });

            return conflictos;
        }

        function escaparHtml(texto) {
            return String(texto || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function normalizarBusquedaCoordinador(valor) {
            return String(valor || '')
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/\s+/g, ' ')
                .trim();
        }

        function soloDigitos(valor) {
            return String(valor || '').replace(/\D+/g, '');
        }

        function filtrarCoordinadorTabla() {
            const terminoOriginal = buscarCoordinador?.value || '';
            const termino = normalizarBusquedaCoordinador(terminoOriginal);
            const tokens = termino.split(' ').filter(Boolean);

            filasTablaCoordinador.forEach(function (fila) {
                if (!fila.querySelectorAll('td').length) {
                    return;
                }

                const texto = normalizarBusquedaCoordinador(fila.dataset.search || fila.textContent || '');
                const textoDigitos = soloDigitos(fila.dataset.search || fila.textContent || '').replace(/^0+/, '');
                const coincide = !tokens.length || tokens.every(function (token) {
                    const tokenDigitos = soloDigitos(token).replace(/^0+/, '');
                    return texto.includes(token) || (tokenDigitos !== '' && textoDigitos.includes(tokenDigitos));
                });

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

                form.action = `/coordinador-operador/${id}/asignar-agencias`;
                form.dataset.coordinadorId = String(this.dataset.coordinadorId || id || '0');
                form.dataset.asignadasIniciales = JSON.stringify(asignadas);
                nombreAsignacion.textContent = nombre;

                if (confirmarReasignacion) {
                    confirmarReasignacion.value = '0';
                }

                checkboxes.forEach(function (checkbox) {
                    checkbox.checked = asignadas.includes(Number(checkbox.value));
                });

                if (buscarTerminalAgencia) {
                    buscarTerminalAgencia.value = '';
                    filtrarAgenciasModal();
                }

                limpiarTerminalesMasivos();

                modal.show();
            });
        });

        if (form) {
            form.addEventListener('submit', function (event) {
                if (form.dataset.confirmadoReasignacion === '1') {
                    form.dataset.confirmadoReasignacion = '0';
                    return;
                }

                const coordinadorIdActual = Number(form.dataset.coordinadorId || 0);
                const conflictos = obtenerConflictosSeleccionados(coordinadorIdActual);

                if (!conflictos.length) {
                    if (confirmarReasignacion) {
                        confirmarReasignacion.value = '0';
                    }
                    return;
                }

                const detalle = conflictos
                    .slice(0, 8)
                    .map(function (conflicto) {
                        const duenos = conflicto.asignadosOtros
                            .map(function (owner) { return owner.nombre || 'Coordinador'; })
                            .join(', ');
                        return {
                            terminal: conflicto.terminal,
                            duenos: duenos,
                        };
                    })
                    .map(function (item) {
                        return `<li class="mb-1"><strong>${escaparHtml(item.terminal)}</strong>: ${escaparHtml(item.duenos)}</li>`;
                    })
                    .join('');

                const excedente = conflictos.length > 8
                    ? `<p class="text-muted small mt-2 mb-0">... y ${conflictos.length - 8} mas.</p>`
                    : '';

                event.preventDefault();

                if (window.Swal && typeof window.Swal.fire === 'function') {
                    const htmlDetalle = `
                        <p class="mb-2">Estas agencias ya estan asignadas a otro coordinador:</p>
                        <ul class="text-start ps-3 mb-0">${detalle}</ul>
                        ${excedente}
                    `;

                    window.Swal.fire({
                        icon: 'warning',
                        title: 'Reasignar agencias',
                        html: htmlDetalle,
                        showCancelButton: true,
                        confirmButtonText: 'Si, mover agencias',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#0ab39c',
                        cancelButtonColor: '#f06548',
                        reverseButtons: true,
                    }).then(function (resultado) {
                        if (!resultado.isConfirmed) {
                            if (confirmarReasignacion) {
                                confirmarReasignacion.value = '0';
                            }
                            return;
                        }

                        if (confirmarReasignacion) {
                            confirmarReasignacion.value = '1';
                        }

                        form.dataset.confirmadoReasignacion = '1';
                        form.submit();
                    });
                    return;
                }

                // Si SweetAlert no esta disponible, no mostramos confirmacion nativa
                // para evitar el mensaje negro del navegador.
                if (confirmarReasignacion) {
                    confirmarReasignacion.value = '0';
                }
            });
        }

        if (buscarTerminalAgencia) {
            buscarTerminalAgencia.addEventListener('input', filtrarAgenciasModal);
        }

        if (buscarCoordinador) {
            buscarCoordinador.addEventListener('input', filtrarCoordinadorTabla);
        }

        if (btnAplicarTerminalesMasivos) {
            btnAplicarTerminalesMasivos.addEventListener('click', aplicarTerminalesMasivos);
        }

        if (btnLimpiarTerminalesMasivos) {
            btnLimpiarTerminalesMasivos.addEventListener('click', limpiarTerminalesMasivos);
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
