@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Agencias Cerradas Domingos</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('recursos-humanos.index') }}">Recursos Humanos</a></li>
                                    <li class="breadcrumb-item active">Agencias Cerradas Domingos</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                @if (isset($errors) && $errors->any())
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <div class="fw-semibold mb-1">No se pudo generar el reporte</div>
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                @endif

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle text-primary rounded">
                                    <i class="ri-calendar-check-line fs-4"></i>
                                </span>
                            </div>
                            <div>
                                <h5 class="card-title mb-1">Consultar domingo</h5>
                                <p class="text-muted mb-0">Una agencia se considera cerrada cuando no registra movimientos de venta ni primer ponche.</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ route('recursos-humanos.agencias-cerradas-domingos.index') }}" class="row g-3 align-items-end">
                            <input type="hidden" name="consultar" value="1">
                            <div class="col-lg-4 col-md-6">
                                <label for="fecha" class="form-label">Domingo a consultar</label>
                                <input type="date" id="fecha" name="fecha" class="form-control {{ isset($errors) && $errors->has('fecha') ? 'is-invalid' : '' }}"
                                    value="{{ old('fecha', $fechaSeleccionada) }}" max="{{ today()->toDateString() }}" required>
                                <div class="form-text">Solo se permiten fechas que correspondan a domingo.</div>
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ri-search-line me-1"></i> Generar consulta
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                @if ($reporte)
                    @if (! $reporte['datos_disponibles'])
                        <div class="alert alert-warning border-0 shadow-sm" role="alert">
                            <div class="d-flex gap-3">
                                <i class="ri-database-2-line fs-3"></i>
                                <div>
                                    <h5 class="alert-heading mb-1">Datos incompletos</h5>
                                    <p class="mb-0">{{ $reporte['mensaje_datos'] }}</p>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="row g-3 mb-4">
                            <div class="col-xl-3 col-md-6">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body">
                                        <p class="text-muted text-uppercase fw-semibold fs-12 mb-2">Fecha consultada</p>
                                        <h5 class="mb-0 text-capitalize">
                                            {{ \Illuminate\Support\Carbon::parse($reporte['fecha'])->locale('es')->translatedFormat('l d/m/Y') }}
                                        </h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body">
                                        <p class="text-muted text-uppercase fw-semibold fs-12 mb-2">Agencias activas</p>
                                        <h3 class="mb-0">{{ number_format($reporte['agencias_activas']) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body">
                                        <p class="text-muted text-uppercase fw-semibold fs-12 mb-2">Agencias cerradas</p>
                                        <h3 class="mb-0 text-danger">{{ number_format($reporte['agencias_cerradas']) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body">
                                        <p class="text-muted text-uppercase fw-semibold fs-12 mb-2">Registros fuente</p>
                                        <div class="d-flex justify-content-between">
                                            <span>Ventas</span>
                                            <strong>{{ number_format($reporte['movimientos_fuente']) }}</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mt-1">
                                            <span>Ponches</span>
                                            <strong>{{ number_format($reporte['ponches_fuente']) }}</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div>
                                    <h5 class="card-title mb-1">Agencias que no abrieron</h5>
                                    <p class="text-muted mb-0">Terminales sin ventas y sin ponches para la fecha seleccionada.</p>
                                </div>
                                <a href="{{ route('recursos-humanos.agencias-cerradas-domingos.exportar', ['fecha' => $reporte['fecha']]) }}"
                                    class="btn btn-success">
                                    <i class="ri-file-excel-2-line me-1"></i> Descargar Excel
                                </a>
                            </div>
                            <div class="card-body">
                                @if ($reporte['empresas']->isNotEmpty())
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        @foreach ($reporte['empresas'] as $empresa)
                                            <span class="badge bg-light text-dark border px-3 py-2">
                                                {{ $empresa['empresa'] }}: {{ $empresa['cerradas'] }} cerradas de {{ $empresa['activas'] }} activas
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="table-responsive">
                                    <table class="table table-hover table-striped align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Terminal</th>
                                                <th>Nombre de la agencia</th>
                                                <th>Empresa</th>
                                                <th>Ciudad</th>
                                                <th>Ruta</th>
                                                <th>Coordinador</th>
                                                <th class="text-center">Ventas</th>
                                                <th class="text-center">Ponches</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($reporte['filas'] as $fila)
                                                <tr>
                                                    <td class="fw-semibold">{{ $fila['terminal'] }}</td>
                                                    <td>{{ $fila['agencia'] }}</td>
                                                    <td>{{ $fila['empresa'] }}</td>
                                                    <td>{{ $fila['ciudad'] }}</td>
                                                    <td>{{ $fila['ruta'] }}</td>
                                                    <td>{{ $fila['coordinador'] }}</td>
                                                    <td class="text-center"><span class="badge bg-danger-subtle text-danger">0</span></td>
                                                    <td class="text-center"><span class="badge bg-danger-subtle text-danger">0</span></td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="8" class="text-center py-5">
                                                        <i class="ri-checkbox-circle-line fs-1 text-success d-block mb-2"></i>
                                                        <h5 class="mb-1">Todas las agencias registraron actividad</h5>
                                                        <p class="text-muted mb-0">No se encontraron agencias sin ventas y sin ponches.</p>
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endsection
