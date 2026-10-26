@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="page-title-box d-flex align-items-center justify-content-between">
                    <h4>Historial de Nómina Domingo</h4>
                    <a href="{{ route('recursos-humanos.nomina-domingo.index') }}" class="btn btn-light">Volver a nómina</a>
                </div>
                <div class="card"><div class="card-body">
                    <form method="GET" action="{{ route('recursos-humanos.nomina-domingo.auditoria') }}" class="d-flex gap-2 mb-3">
                        <input class="form-control" name="buscar" value="{{ $buscar }}" placeholder="Buscar usuario o terminal" aria-label="Buscar usuario o terminal">
                        <button class="btn btn-primary">Buscar</button>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Acción</th><th>Detalle</th><th>Origen</th></tr></thead>
                            <tbody>
                                @forelse ($auditorias as $auditoria)
                                    <tr>
                                        <td class="text-nowrap">{{ $auditoria->created_at->format('d/m/Y h:i:s A') }}</td>
                                        <td>{{ $auditoria->usuario_nombre }}<br><small>{{ $auditoria->usuario_email }}</small></td>
                                        <td>{{ ucfirst($auditoria->accion) }}</td>
                                        <td>
                                            @if ($auditoria->categoria === 'configuracion')
                                                Horas requeridas: {{ data_get($auditoria->antes, 'horas_requeridas') }} → {{ data_get($auditoria->despues, 'horas_requeridas') }}<br>
                                                Minutos doble turno: {{ data_get($auditoria->antes, 'minutos_requeridos_doble_turno', '-') }} → {{ data_get($auditoria->despues, 'minutos_requeridos_doble_turno', '-') }}<br>
                                                Monto fijo: {{ data_get($auditoria->antes, 'monto_fijo') }} → {{ data_get($auditoria->despues, 'monto_fijo') }}
                                            @else
                                                Terminal {{ $auditoria->terminal }} ({{ $auditoria->categoria === 'excluida' ? 'excluida' : 'doble turno' }})
                                            @endif
                                        </td>
                                        <td>IP: {{ $auditoria->ip ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted">No hay cambios registrados.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $auditorias->links() }}
                </div></div>
            </div>
        </div>
    </div>
@endsection
