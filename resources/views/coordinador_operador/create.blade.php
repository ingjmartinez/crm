@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Nuevo Coordinador / Operador</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('coordinador-operador.index') }}">Coordinador / Operador</a></li>
                                    <li class="breadcrumb-item active">Crear</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8 mx-auto">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Datos del registro</h5>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('coordinador-operador.store') }}" method="POST">
                                    @csrf

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Nombre <span class="text-danger">*</span></label>
                                            <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre') }}" required>
                                            @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Apellido <span class="text-danger">*</span></label>
                                            <input type="text" name="apellido" class="form-control @error('apellido') is-invalid @enderror" value="{{ old('apellido') }}" required>
                                            @error('apellido')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Correo <span class="text-danger">*</span></label>
                                            <input type="email" name="correo" class="form-control @error('correo') is-invalid @enderror" value="{{ old('correo') }}" required>
                                            @error('correo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Cédula <span class="text-danger">*</span></label>
                                            <input type="number" name="cedula" class="form-control @error('cedula') is-invalid @enderror" value="{{ old('cedula') }}" min="10000000000" max="99999999999" step="1" required>
                                            <div class="form-text">Debe contener exactamente 11 dígitos.</div>
                                            @error('cedula')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Teléfono <span class="text-danger">*</span></label>
                                            <input type="number" name="telefono" class="form-control @error('telefono') is-invalid @enderror" value="{{ old('telefono') }}" min="1000000000" max="9999999999" step="1" required>
                                            <div class="form-text">Debe contener exactamente 10 dígitos.</div>
                                            @error('telefono')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Puesto <span class="text-danger">*</span></label>
                                            <select name="puesto" class="form-select @error('puesto') is-invalid @enderror" required>
                                                <option value="">Seleccione</option>
                                                <option value="coordinador" @selected(old('puesto') === 'coordinador')>Coordinador</option>
                                                <option value="operador" @selected(old('puesto') === 'operador')>Operador</option>
                                            </select>
                                            @error('puesto')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end gap-2 mt-4">
                                        <a href="{{ route('coordinador-operador.index') }}" class="btn btn-secondary">Cancelar</a>
                                        <button type="submit" class="btn btn-primary">Guardar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
