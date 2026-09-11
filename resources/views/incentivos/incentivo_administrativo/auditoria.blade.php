@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="page-title-box d-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-1">Historial de Incentivo Administrativo</h4>
                        <p class="text-muted mb-0">Registro de altas y eliminaciones visible solo para Super Admin.</p>
                    </div>
                    <a href="{{ route('incentivos.incentivo-administrativo.index') }}" class="btn btn-light">
                        Volver al listado
                    </a>
                </div>

                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="{{ route('incentivos.incentivo-administrativo.auditoria') }}" class="row g-2">
                            <div class="col-lg-4">
                                <label for="buscar" class="form-label">Usuario o empleado</label>
                                <input id="buscar" name="buscar" class="form-control" value="{{ $buscar }}"
                                    placeholder="Nombre, correo, cedula o empresa">
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
                                <a href="{{ route('incentivos.incentivo-administrativo.auditoria') }}" class="btn btn-light">Limpiar</a>
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
                                        <th>Grupo</th>
                                        <th>Empresa</th>
                                        <th>Valor</th>
                                        <th>Origen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($auditorias as $auditoria)
                                        <tr>
                                            <td class="text-nowrap">
                                                {{ $auditoria->created_at->format('d/m/Y h:i:s A') }}
                                            </td>
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
                                                <small class="text-muted">Cedula: {{ $auditoria->cedula ?: '-' }} · ID registro: {{ $auditoria->registro_id ?: '-' }}</small>
                                            </td>
                                            <td>{{ data_get($auditoria->datos, 'grupo', '-') }}</td>
                                            <td>{{ $auditoria->empresa }}</td>
                                            <td>{{ number_format((float) data_get($auditoria->datos, 'pct_total', 0), 2) }}</td>
                                            <td>
                                                <div>IP: {{ $auditoria->ip ?: '-' }}</div>
                                                <small class="text-muted" title="{{ $auditoria->user_agent }}">
                                                    {{ \Illuminate\Support\Str::limit($auditoria->user_agent, 55) }}
                                                </small>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="py-4 text-center text-muted">No hay movimientos registrados.</td>
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
