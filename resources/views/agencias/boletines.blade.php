@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-flex align-items-center justify-content-between">
                            <h4 class="mb-0">Boletines de cambios de agencias</h4>
                            <a href="{{ route('agencias.index') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="ri-arrow-left-line me-1"></i>Volver a agencias
                            </a>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-1">Historial guardado en el servidor</h5>
                                <p class="text-muted mb-0">Cada boletín muestra el estado encontrado antes de ejecutar cambios.</p>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Boletín</th>
                                                <th>Generado por</th>
                                                <th>Fecha</th>
                                                <th>Rango evaluado</th>
                                                <th class="text-end">Registros</th>
                                                <th class="text-end">Tamaño</th>
                                                <th class="text-center">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($boletines as $boletin)
                                                <tr>
                                                    <td>
                                                        <strong>{{ $boletin['titulo'] ?? 'Boletín de cambios' }}</strong>
                                                        <div class="small text-muted">{{ $boletin['descripcion'] ?? '' }}</div>
                                                    </td>
                                                    <td>{{ $boletin['generado_por'] ?? 'Usuario no identificado' }}</td>
                                                    <td>{{ \Carbon\Carbon::parse($boletin['generado_en'])->format('d/m/Y h:i:s A') }}</td>
                                                    <td>
                                                        @if (! empty($boletin['desde']) && ! empty($boletin['hasta']))
                                                            {{ $boletin['desde'] }} a {{ $boletin['hasta'] }}
                                                        @else
                                                            Estado actual
                                                        @endif
                                                    </td>
                                                    <td class="text-end">{{ number_format($boletin['total'] ?? 0) }}</td>
                                                    <td class="text-end">{{ number_format(($boletin['tamano'] ?? 0) / 1024, 1) }} KB</td>
                                                    <td class="text-center">
                                                        <a href="{{ $boletin['url'] }}" class="btn btn-sm btn-danger" target="_blank" rel="noopener">
                                                            <i class="ri-file-pdf-2-line me-1"></i>Ver PDF
                                                        </a>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted py-4">
                                                        Todavía no se han generado boletines de cambios.
                                                    </td>
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
@endsection
