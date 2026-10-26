@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Maestra de agencia</h4>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('contabilidad.index') }}">Contabilidad</a></li>
                            <li class="breadcrumb-item active">Maestra de agencia</li>
                        </ol>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <h5 class="card-title mb-1">Maestra de agencia</h5>
                            <p class="text-muted mb-0">{{ number_format($centrosDeCosto->total()) }} centros de costo</p>
                        </div>
                        <form method="GET" action="{{ route('contabilidad.maestra-agencia') }}" class="d-flex gap-2">
                            <input type="search" name="buscar" value="{{ $busqueda }}" class="form-control" placeholder="Buscar centro, terminal o grupo" aria-label="Buscar centro de costo, terminal o grupo">
                            <button type="submit" class="btn btn-primary">Buscar</button>
                            @if ($busqueda !== '')
                                <a href="{{ route('contabilidad.maestra-agencia') }}" class="btn btn-light">Limpiar</a>
                            @endif
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle mb-0">
                                <thead class="table-primary">
                                    <tr>
                                        <th scope="col">Empresa</th>
                                        <th scope="col">Centro de costo</th>
                                        <th scope="col">ID viejo</th>
                                        <th scope="col">Nombre de la terminal</th>
                                        <th scope="col">Grupo</th>
                                        <th scope="col">Subgrupo</th>
                                        <th scope="col">División</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($centrosDeCosto as $centroDeCosto)
                                        <tr>
                                            <td class="text-nowrap">{{ $centroDeCosto->company_id ?: '—' }}</td>
                                            <td class="text-nowrap fw-semibold">{{ $centroDeCosto->id_centro_costo }}</td>
                                            <td>{{ $centroDeCosto->id_viejo ?: '—' }}</td>
                                            <td>{{ $centroDeCosto->descripcion ?: '—' }}</td>
                                            <td>{{ $centroDeCosto->id_grupo ?: '—' }}</td>
                                            <td>{{ $centroDeCosto->id_sub_grupo ?: '—' }}</td>
                                            <td>{{ $centroDeCosto->id_division ?: '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="text-center text-muted py-4">No se encontraron centros de costo.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">{{ $centrosDeCosto->links() }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
