@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Gasto por agencia</h4>
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('contabilidad.index') }}">Contabilidad</a></li>
                        <li class="breadcrumb-item active">Gasto por agencia</li>
                    </ol>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-1">Gasto por agencia</h5>
                        <p class="text-muted mb-0">Movimientos archivados del mayor en las cuentas activas de clasificación. Importe = débito − crédito.</p>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ route('contabilidad.gasto-agencia') }}" class="row g-3 align-items-end mb-4">
                            <div class="col-sm-6 col-lg-2">
                                <label for="gastoFechaInicio" class="form-label">Desde</label>
                                <input id="gastoFechaInicio" type="date" name="fecha_inicio" value="{{ $fechaInicio }}" class="form-control" required>
                            </div>
                            <div class="col-sm-6 col-lg-2">
                                <label for="gastoFechaFin" class="form-label">Hasta</label>
                                <input id="gastoFechaFin" type="date" name="fecha_fin" value="{{ $fechaFin }}" class="form-control" required>
                            </div>
                            <div class="col-sm-6 col-lg-2">
                                <label for="gastoEmpresa" class="form-label">Empresa</label>
                                <select id="gastoEmpresa" name="empresa" class="form-select">
                                    <option value="">Todas</option>
                                    <option value="168" @selected($empresa === '168')>168</option>
                                    <option value="169" @selected($empresa === '169')>169</option>
                                </select>
                            </div>
                            <div class="col-sm-6 col-lg-4">
                                <label for="gastoBuscar" class="form-label">Agencia</label>
                                <input id="gastoBuscar" type="search" name="buscar" value="{{ $busqueda }}" class="form-control" placeholder="Terminal, nombre o centro de costo">
                            </div>
                            <div class="col-lg-2">
                                <button type="submit" class="btn btn-primary w-100">Consultar</button>
                            </div>
                        </form>

                        @if ($errors->any())
                            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle mb-0">
                                <thead class="table-primary">
                                    <tr>
                                        <th scope="col">Empresa</th>
                                        <th scope="col">Terminal</th>
                                        <th scope="col">Nombre de agencia</th>
                                        <th scope="col">Centro de costo</th>
                                        <th scope="col" class="text-end">Gasto neto (DOP)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($agencias as $agencia)
                                        @php($gasto = $montos->get($agencia->company_id.'|'.$agencia->id_centro_costo)?->gasto ?? 0)
                                        <tr>
                                            <td>{{ $agencia->company_id }}</td>
                                            <td>{{ $agencia->id_viejo ?: '—' }}</td>
                                            <td>{{ $agencia->descripcion ?: '—' }}</td>
                                            <td class="fw-semibold">{{ $agencia->id_centro_costo }}</td>
                                            <td class="text-end fw-semibold">{{ number_format((float) $gasto, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted py-4">No se encontraron agencias.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">{{ $agencias->links() }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
