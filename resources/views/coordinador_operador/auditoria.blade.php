@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="page-title-box d-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-1">Auditoria de Coordinadores y Operadores</h4>
                        <p class="text-muted mb-0">Altas y eliminaciones visibles solo para Super Admin.</p>
                    </div>
                    <a href="{{ route('coordinador-operador.index') }}" class="btn btn-light">Volver al listado</a>
                </div>

                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="{{ route('coordinador-operador.auditoria') }}" class="row g-2">
                            <div class="col-lg-4">
                                <label for="buscar" class="form-label">Usuario o empleado</label>
                                <input id="buscar" name="buscar" class="form-control" value="{{ $buscar }}"
                                    placeholder="Nombre, correo, cedula o puesto">
                            </div>
                            <div class="col-md-4 col-lg-2">
                                <label for="accion" class="form-label">Accion</label>
                                <select id="accion" name="accion" class="form-select">
                                    <option value="">Todas</option>
                                    <option value="registrado" {{ $accion === 'registrado' ? 'selected' : '' }}>Registro</option>
                                    <option value="eliminado" {{ $accion === 'eliminado' ? 'selected' : '' }}>Eliminacion</option>
                                </select>
                            </div>
                            <div class="col-md-4 col-lg-2">
                                <label for="desde" class="form-label">Desde</label>
                                <input id="desde" type="date" name="desde" class="form-control" value="{{ $desde?->format('Y-m-d') }}">
                            </div>
                            <div class="col-md-4 col-lg-2">
                                <label for="hasta" class="form-label">Hasta</label>
                                <input id="hasta" type="date" name="hasta" class="form-control" value="{{ $hasta?->format('Y-m-d') }}">
                            </div>
                            <div class="col-lg-2 d-flex align-items-end gap-2">
                                <button class="btn btn-primary flex-fill">Filtrar</button>
                                <a href="{{ route('coordinador-operador.auditoria') }}" class="btn btn-light">Limpiar</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha y hora</th>
                                        <th>Accion</th>
                                        <th>Usuario responsable</th>
                                        <th>Empleado afectado</th>
                                        <th>Puesto</th>
                                        <th>Correo / Telefono</th>
                                        <th>Origen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($auditorias as $auditoria)
                                        <tr>
                                            <td class="text-nowrap">{{ $auditoria->created_at->format('d/m/Y h:i:s A') }}</td>
                                            <td>
                                                <span class="badge {{ $auditoria->accion === 'registrado' ? 'bg-success' : 'bg-danger' }}">
                                                    {{ $auditoria->accion === 'registrado' ? 'Registro' : 'Eliminacion' }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="fw-semibold">{{ $auditoria->usuario_nombre }}</div>
                                                <small class="text-muted">{{ $auditoria->usuario_email }}</small>
                                            </td>
                                            <td>
                                                <div class="fw-semibold">{{ $auditoria->empleado_nombre }}</div>
                                                <small class="text-muted">Cedula: {{ $auditoria->cedula ?: '-' }} · ID: {{ $auditoria->registro_id ?: '-' }}</small>
                                            </td>
                                            <td class="text-capitalize">{{ $auditoria->puesto }}</td>
                                            <td>
                                                <div>{{ data_get($auditoria->datos, 'correo', '-') ?: '-' }}</div>
                                                <small class="text-muted">{{ data_get($auditoria->datos, 'telefono', '-') ?: '-' }}</small>
                                            </td>
                                            <td>
                                                <div>IP: {{ $auditoria->ip ?: '-' }}</div>
                                                <small class="text-muted" title="{{ $auditoria->user_agent }}">
                                                    {{ \Illuminate\Support\Str::limit($auditoria->user_agent, 55) }}
                                                </small>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="py-4 text-center text-muted">No hay movimientos registrados.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{ $auditorias->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
