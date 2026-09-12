@extends('app')

@section('content')
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <div>
                            <h4 class="mb-sm-0">Ventas en Vivo</h4>
                            <small class="text-muted">Tablero gerencial de seguimiento por empresa, ciudad, ruta, terminal y producto.</small>
                        </div>
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('gerencia.index') }}">Gerencia</a></li>
                            <li class="breadcrumb-item active">Ventas en Vivo</li>
                        </ol>
                    </div>
                </div>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('gerencia.ventas-en-vivo') }}" class="row g-2 align-items-end" id="form-ventas-en-vivo">
                        <input type="hidden" name="consultar" value="1">
                        <div class="col-md-2">
                            <label class="form-label">Fecha</label>
                            <input type="date" class="form-control" name="fecha" value="{{ $fechaSeleccionada }}" max="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sistema</label>
                            <select class="form-select" name="sistema">
                                <option value="todos" @selected(($filtros['sistema'] ?? 'todos') === 'todos')>Todos</option>
                                <option value="lotobet" @selected(($filtros['sistema'] ?? '') === 'lotobet')>Lotobet</option>
                                <option value="lotonet" @selected(($filtros['sistema'] ?? '') === 'lotonet')>Lotonet</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Empresa</label>
                            <select class="form-select" name="empresa">
                                <option value="">Todas</option>
                                @foreach (($opciones['empresas'] ?? collect()) as $empresa)
                                    <option value="{{ $empresa }}" @selected(($filtros['empresa'] ?? '') === $empresa)>{{ $empresa }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Ciudad</label>
                            <select class="form-select" name="ciudad">
                                <option value="">Todas</option>
                                @foreach (($opciones['ciudades'] ?? collect()) as $ciudad)
                                    <option value="{{ $ciudad }}" @selected(($filtros['ciudad'] ?? '') === $ciudad)>{{ $ciudad }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Ruta</label>
                            <select class="form-select" name="ruta">
                                <option value="">Todas</option>
                                @foreach (($opciones['rutas'] ?? collect()) as $ruta)
                                    <option value="{{ $ruta }}" @selected(($filtros['ruta'] ?? '') === $ruta)>{{ $ruta }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tipo de venta</label>
                            <select class="form-select" name="tipo_producto">
                                <option value="todos" @selected(($filtros['tipo_producto'] ?? 'todos') === 'todos')>Todos</option>
                                <option value="tradicional" @selected(($filtros['tipo_producto'] ?? '') === 'tradicional')>Tradicional</option>
                                <option value="no_tradicional" @selected(($filtros['tipo_producto'] ?? '') === 'no_tradicional')>No Tradicional</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Buscar agencia, terminal o producto</label>
                            <input type="search" class="form-control" name="buscar" value="{{ $filtros['buscar'] ?? '' }}" placeholder="Ej.: Agencia Centro, 00123, Loto Real">
                        </div>
                        <div class="col-md-6 d-flex gap-2 justify-content-md-end">
                            <button class="btn btn-primary" type="submit" id="btn-consultar-vivo"><i class="ri-search-line me-1"></i>Consultar</button>
                            <a class="btn btn-light" href="{{ route('gerencia.ventas-en-vivo') }}">Limpiar</a>
                        </div>
                    </form>
                </div>
            </div>

            @if (! $debeConsultar)
                <div class="card">
                    <div class="card-body text-center py-5">
                        <div class="avatar-lg mx-auto mb-3"><span class="avatar-title rounded-circle bg-primary-subtle text-primary fs-2"><i class="ri-radar-line"></i></span></div>
                        <h5>Tablero listo para seguimiento</h5>
                        <p class="text-muted mb-0">Selecciona filtros y presiona <strong>Consultar</strong> para cargar ventas y ranking Top 10.</p>
                    </div>
                </div>
            @else
                @php
                    $fm = static fn ($value) => 'RD$ '.number_format((float) $value, 2);
                    $fp = static fn ($value) => number_format((float) $value, 1).'%';
                @endphp

                <div class="alert alert-info d-flex align-items-center gap-2">
                    <i class="ri-time-line fs-5"></i>
                    <span>Seguimiento de fecha {{ $resumen['fecha'] }} con filtros aplicados para toma de decisiones operativas.</span>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card mb-0 border-primary border-opacity-25 h-100"><div class="card-body"><small class="text-muted">Venta total</small><h4 class="mt-2 mb-0 text-primary">{{ $fm($resumen['total_ventas']) }}</h4></div></div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card mb-0 border-success border-opacity-25 h-100"><div class="card-body"><small class="text-muted">Tradicional</small><h4 class="mt-2 mb-0 text-success">{{ $fm($resumen['total_tradicional']) }}</h4></div></div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card mb-0 border-info border-opacity-25 h-100"><div class="card-body"><small class="text-muted">No Tradicional</small><h4 class="mt-2 mb-0 text-info">{{ $fm($resumen['total_no_tradicional']) }}</h4></div></div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card mb-0 border-warning border-opacity-25 h-100"><div class="card-body"><small class="text-muted">Participacion No Tradicional</small><h4 class="mt-2 mb-0 text-warning">{{ $fp($resumen['participacion_no_tradicional']) }}</h4></div></div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    @foreach (($por_tipo ?? []) as $tipo)
                        <div class="col-md-6">
                            <div class="card mb-0 h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">{{ $tipo['nombre'] }}</h6>
                                        <span class="badge bg-light text-dark">{{ $fp($tipo['porcentaje']) }}</span>
                                    </div>
                                    <div class="progress progress-sm mb-2">
                                        <div class="progress-bar {{ $tipo['key'] === 'tradicional' ? 'bg-success' : 'bg-info' }}" role="progressbar" style="width: {{ min(100, max(0, (float) $tipo['porcentaje'])) }}%"></div>
                                    </div>
                                    <div class="fw-semibold">{{ $fm($tipo['monto']) }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="row g-3">
                    <div class="col-xl-6">
                        <div class="card h-100 mb-0">
                            <div class="card-header"><h5 class="card-title mb-0">Top 10 mejores terminales</h5></div>
                            <div class="card-body table-responsive">
                                <table class="table table-sm table-striped align-middle mb-0">
                                    <thead><tr><th>Terminal</th><th>Agencia</th><th>Ubicacion</th><th>Sistema</th><th class="text-end">Monto</th></tr></thead>
                                    <tbody>
                                    @forelse (($top_terminales ?? []) as $item)
                                        <tr>
                                            <td class="fw-semibold">{{ $item['terminal'] }}</td>
                                            <td>{{ $item['nombre_agencia'] }}</td>
                                            <td><small class="text-muted">{{ $item['empresa'] }} · {{ $item['ciudad'] }} · {{ $item['ruta'] }}</small></td>
                                            <td>{{ $item['sistema'] }}</td>
                                            <td class="text-end">{{ $fm($item['monto']) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted py-4">Sin datos para mostrar.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-6">
                        <div class="card h-100 mb-0">
                            <div class="card-header"><h5 class="card-title mb-0">Top 10 mejores productos vendidos</h5></div>
                            <div class="card-body table-responsive">
                                <table class="table table-sm table-striped align-middle mb-0">
                                    <thead><tr><th>Producto</th><th class="text-end">Tradicional</th><th class="text-end">No Tradicional</th><th class="text-end">Monto</th></tr></thead>
                                    <tbody>
                                    @forelse (($top_productos ?? []) as $item)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $item['producto'] }}</div>
                                                <small class="text-muted">{{ $item['producto_id'] !== '' ? $item['producto_id'] : 'Sin ID' }}</small>
                                            </td>
                                            <td class="text-end">{{ $fm($item['tradicional']) }}</td>
                                            <td class="text-end">{{ $fm($item['no_tradicional']) }}</td>
                                            <td class="text-end">{{ $fm($item['monto']) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted py-4">Sin datos para mostrar.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-6">
                        <div class="card h-100 mb-0">
                            <div class="card-header"><h5 class="card-title mb-0">Top 10 peores agencias</h5></div>
                            <div class="card-body table-responsive">
                                <table class="table table-sm table-striped align-middle mb-0">
                                    <thead><tr><th>Terminal</th><th>Agencia</th><th>Ubicacion</th><th>Sistema</th><th class="text-end">Monto</th></tr></thead>
                                    <tbody>
                                    @forelse (($peores_agencias ?? []) as $item)
                                        <tr>
                                            <td class="fw-semibold">{{ $item['terminal'] }}</td>
                                            <td>{{ $item['nombre_agencia'] }}</td>
                                            <td><small class="text-muted">{{ $item['empresa'] }} · {{ $item['ciudad'] }} · {{ $item['ruta'] }}</small></td>
                                            <td>{{ $item['sistema'] }}</td>
                                            <td class="text-end text-danger">{{ $fm($item['monto']) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted py-4">Sin datos para mostrar.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-6">
                        <div class="card h-100 mb-0">
                            <div class="card-header"><h5 class="card-title mb-0">Top 10 peores productos</h5></div>
                            <div class="card-body table-responsive">
                                <table class="table table-sm table-striped align-middle mb-0">
                                    <thead><tr><th>Producto</th><th class="text-end">Tradicional</th><th class="text-end">No Tradicional</th><th class="text-end">Monto</th></tr></thead>
                                    <tbody>
                                    @forelse (($peores_productos ?? []) as $item)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $item['producto'] }}</div>
                                                <small class="text-muted">{{ $item['producto_id'] !== '' ? $item['producto_id'] : 'Sin ID' }}</small>
                                            </td>
                                            <td class="text-end">{{ $fm($item['tradicional']) }}</td>
                                            <td class="text-end">{{ $fm($item['no_tradicional']) }}</td>
                                            <td class="text-end text-danger">{{ $fm($item['monto']) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted py-4">Sin datos para mostrar.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <h5 class="card-title mb-0">Detalle de seguimiento</h5>
                        <small class="text-muted">Mostrando {{ $totales['filas_detalle'] ?? 0 }} filas priorizadas por monto.</small>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-bordered table-striped align-middle w-100" id="tabla-detalle-ventas-vivo">
                            <thead>
                                <tr>
                                    <th>Empresa</th>
                                    <th>Ciudad</th>
                                    <th>Ruta</th>
                                    <th>Agencia</th>
                                    <th>Terminal</th>
                                    <th>Sistema</th>
                                    <th>Tipo</th>
                                    <th>Producto</th>
                                    <th class="text-end">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (($detalle ?? []) as $fila)
                                    <tr>
                                        <td>{{ $fila['empresa'] }}</td>
                                        <td>{{ $fila['ciudad'] }}</td>
                                        <td>{{ $fila['ruta'] }}</td>
                                        <td>{{ $fila['nombre_agencia'] }}</td>
                                        <td>{{ $fila['terminal'] }}</td>
                                        <td>{{ $fila['sistema'] }}</td>
                                        <td>{{ $fila['tipo_categoria'] === 'tradicional' ? 'Tradicional' : ($fila['tipo_categoria'] === 'no_tradicional' ? 'No Tradicional' : 'Otros') }}</td>
                                        <td>{{ $fila['producto'] }}</td>
                                        <td class="text-end">{{ $fm($fila['monto']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">No hay datos para los filtros seleccionados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('form-ventas-en-vivo');
    form?.addEventListener('submit', function () {
        const button = document.getElementById('btn-consultar-vivo');
        if (button) {
            button.disabled = true;
        }
        if (window.Swal) {
            Swal.fire({
                title: 'Consultando ventas...',
                text: 'Procesando indicadores y Top 10 para el tablero gerencial.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => Swal.showLoading(),
            });
        }
    });

    if (window.$ && $.fn.DataTable && document.getElementById('tabla-detalle-ventas-vivo')) {
        $('#tabla-detalle-ventas-vivo').DataTable({
            pageLength: 25,
            order: [[8, 'desc']],
            language: {
                emptyTable: 'No hay datos para mostrar',
                info: 'Mostrando _START_ a _END_ de _TOTAL_',
                lengthMenu: 'Mostrar _MENU_',
                search: 'Buscar:',
                paginate: { next: 'Siguiente', previous: 'Anterior' }
            }
        });
    }
});
</script>
@endsection
