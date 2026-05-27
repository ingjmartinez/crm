@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Editar Acuerdo</h4>
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('contabilidad.index') }}">Contabilidad</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('contabilidad.reportes.comisiones') }}">Comisiones</a></li>
                                <li class="breadcrumb-item active">Editar</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8 mx-auto">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Actualizar acuerdo</h5>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('contabilidad.reportes.comisiones.acuerdos.update', $acuerdo->id) }}" method="POST">
                                    @csrf
                                    @method('PUT')

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Nombre <span class="text-danger">*</span></label>
                                            <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre', $acuerdo->nombre) }}" required>
                                            @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Apellido <span class="text-danger">*</span></label>
                                            <input type="text" name="apellido" class="form-control @error('apellido') is-invalid @enderror" value="{{ old('apellido', $acuerdo->apellido) }}" required>
                                            @error('apellido')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Correo <span class="text-danger">*</span></label>
                                            <input type="email" name="correo" class="form-control @error('correo') is-invalid @enderror" value="{{ old('correo', $acuerdo->correo) }}" required>
                                            @error('correo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Cedula</label>
                                            <input type="text" name="cedula" class="form-control @error('cedula') is-invalid @enderror" value="{{ old('cedula', $acuerdo->cedula) }}" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" oninput="this.value=this.value.replace(/\D/g,'').slice(0, 11); this.setCustomValidity('')">
                                            <div class="form-text">Si se completa, debe tener 11 digitos.</div>
                                            @error('cedula')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Telefono</label>
                                            <input type="text" name="telefono" class="form-control @error('telefono') is-invalid @enderror" value="{{ old('telefono', $acuerdo->telefono) }}" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" oninput="this.value=this.value.replace(/\D/g,'').slice(0, 10); this.setCustomValidity('')">
                                            <div class="form-text">Si se completa, debe tener 10 digitos.</div>
                                            @error('telefono')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">Porcentaje <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input type="number" name="porcentaje" class="form-control @error('porcentaje') is-invalid @enderror" value="{{ old('porcentaje', number_format((float) $acuerdo->porcentaje, 2, '.', '')) }}" min="0" max="100" step="0.01" required>
                                                <span class="input-group-text">%</span>
                                                @error('porcentaje')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">Estado <span class="text-danger">*</span></label>
                                            <select name="activo" class="form-select @error('activo') is-invalid @enderror" required>
                                                <option value="1" @selected((string) old('activo', $acuerdo->activo ? '1' : '0') === '1')>Activo</option>
                                                <option value="0" @selected((string) old('activo', $acuerdo->activo ? '1' : '0') === '0')>Inactivo</option>
                                            </select>
                                            @error('activo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end gap-2 mt-4">
                                        <a href="{{ route('contabilidad.reportes.comisiones') }}" class="btn btn-secondary">Cancelar</a>
                                        <button type="submit" class="btn btn-primary">Actualizar</button>
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
