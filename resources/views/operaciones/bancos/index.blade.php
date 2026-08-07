@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <div>
                                <h4 class="mb-sm-0">Banco</h4>
                                <p class="text-muted mb-0">Catálogo disponible para depósitos y reportes de Operaciones.</p>
                            </div>
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('operaciones.index') }}">Operaciones</a></li>
                                <li class="breadcrumb-item active">Banco</li>
                            </ol>
                        </div>
                    </div>
                </div>

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <div class="row g-4">
                    <div class="col-xl-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-1">Agregar banco</h5>
                                <p class="text-muted mb-0">El banco aparecerá en los formularios de Operaciones.</p>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('operaciones.bancos.store') }}">
                                    @csrf
                                    <label for="nombre-banco" class="form-label">Nombre del banco</label>
                                    <input type="text" name="nombre" id="nombre-banco"
                                        class="form-control @error('nombre') is-invalid @enderror"
                                        value="{{ old('nombre') }}" maxlength="150"
                                        placeholder="Ej.: Banco Popular" required autofocus>
                                    @error('nombre')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="d-grid mt-3">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="ri-add-line me-1"></i>Agregar banco
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-8">
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <div>
                                    <h5 class="card-title mb-1">Bancos disponibles</h5>
                                    <p class="text-muted mb-0">Eliminar un banco no modifica los depósitos ni reportes históricos.</p>
                                </div>
                                <span class="badge bg-primary-subtle text-primary">{{ $bancos->count() }} bancos</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Banco</th>
                                                <th class="text-center">Registros asociados</th>
                                                <th class="text-end">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($bancos as $banco)
                                                <tr>
                                                    <td class="fw-semibold">{{ $banco['nombre'] }}</td>
                                                    <td class="text-center">{{ number_format($banco['usos']) }}</td>
                                                    <td class="text-end">
                                                        <form method="POST" action="{{ route('operaciones.bancos.destroy', $banco['modelo']) }}" class="d-inline"
                                                            onsubmit="return confirm('¿Deseas eliminar este banco del catálogo? Los registros históricos conservarán su nombre.');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-soft-danger">
                                                                <i class="ri-delete-bin-line me-1"></i>Eliminar
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @if ($bancos->isEmpty())
                                    <div class="text-center text-muted py-4">No hay bancos registrados.</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
