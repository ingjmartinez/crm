@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">

                <!-- start page title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Editar Agencia</h4>

                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('agencias.index') }}">Agencias</a></li>
                                    <li class="breadcrumb-item active">Editar</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end page title -->

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Datos de la Agencia</h5>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('agencias.update', $agencia->id) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    
                                    <div class="row">
                                        <div class="col-12 col-md-6 mb-3">
                                            <label for="agencia" class="form-label">Agencia <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('agencia') is-invalid @enderror" 
                                                   id="agencia" name="agencia" value="{{ old('agencia', $agencia->agencia) }}" required>
                                            @error('agencia')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-12 col-md-6 mb-3">
                                            <label for="nombre_agencia" class="form-label">Nombre Agencia</label>
                                            <input type="text" class="form-control @error('nombre_agencia') is-invalid @enderror" 
                                                   id="nombre_agencia" name="nombre_agencia" value="{{ old('nombre_agencia', $agencia->nombre_agencia) }}">
                                            @error('nombre_agencia')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-12 col-md-6 mb-3">
                                            <label for="terminal" class="form-label">Terminal</label>
                                            <input type="text" class="form-control @error('terminal') is-invalid @enderror" 
                                                   id="terminal" name="terminal" value="{{ old('terminal', $agencia->terminal) }}">
                                            @error('terminal')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-12 col-md-6 mb-3">
                                            <label for="sistema" class="form-label">Sistema</label>
                                            <input type="text" class="form-control @error('sistema') is-invalid @enderror" 
                                                   id="sistema" name="sistema" value="{{ old('sistema', $agencia->sistema) }}">
                                            @error('sistema')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-12 col-md-6 mb-3">
                                            <label for="ciudad" class="form-label">Ciudad</label>
                                            <input type="text" class="form-control @error('ciudad') is-invalid @enderror" 
                                                   id="ciudad" name="ciudad" value="{{ old('ciudad', $agencia->ciudad) }}">
                                            @error('ciudad')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-12 col-12 col-md-6 mb-3">
                                            <label for="ruta" class="form-label">Ruta</label>
                                            <input type="text" class="form-control @error('ruta') is-invalid @enderror" 
                                                   id="ruta" name="ruta" value="{{ old('ruta', $agencia->ruta) }}">
                                            @error('ruta')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-12 col-md-6 mb-3">
                                            <label for="operador" class="form-label">Operador</label>
                                            <input type="text" class="form-control @error('operador') is-invalid @enderror" 
                                                   id="operador" name="operador" value="{{ old('operador', $agencia->operador) }}">
                                            @error('operador')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-12 col-md-6 mb-3">
                                            <label for="coordinador" class="form-label">Coordinador</label>
                                            <input type="text" class="form-control @error('coordinador') is-invalid @enderror" 
                                                   id="coordinador" name="coordinador" value="{{ old('coordinador', $agencia->coordinador) }}">
                                            @error('coordinador')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 mt-3">
                                        <a href="{{ route('agencias.index') }}" class="btn btn-secondary">
                                            <i class="ri-close-line align-bottom me-1"></i> Cancelar
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="ri-save-line align-bottom me-1"></i> Actualizar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div><!--end row-->
            </div>
            <!-- container-fluid -->
        </div>
        <!-- End Page-content -->

        <footer class="footer">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <script>
                            document.write(new Date().getFullYear())
                        </script> © CRM.
                    </div>
                </div>
            </div>
        </footer>
    </div>
@endsection
