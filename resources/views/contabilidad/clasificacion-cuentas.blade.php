@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Clasificación de cuenta</h4>
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('contabilidad.index') }}">Contabilidad</a></li>
                                <li class="breadcrumb-item active">Clasificación de cuenta</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <h5 class="card-title mb-1">Clasificación de cuenta</h5>
                            <span class="text-muted">{{ $cuentas->count() }} cuentas · Nombres del catálogo de Inicio Contable</span>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <label class="mb-0" for="buscarClasificacionCuenta">
                                <span class="visually-hidden">Buscar cuenta o nombre</span>
                                <input id="buscarClasificacionCuenta" type="search" class="form-control" placeholder="Buscar cuenta o nombre">
                            </label>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAgregarClasificacionCuenta" @disabled($cuentasDisponibles->isEmpty())>
                                Agregar cuenta
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        @if (session('mensaje'))
                            <div class="alert alert-success" role="status">{{ session('mensaje') }}</div>
                        @endif
                        @if ($errors->any())
                            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                        @endif
                        @if ($cuentasFaltantes !== [])
                            <div class="alert alert-warning" role="alert">
                                No se encontraron en el catálogo las cuentas: {{ implode(', ', $cuentasFaltantes) }}.
                            </div>
                        @endif
                        @if ($cuentasConCriterioDistinto !== [])
                            <div class="alert alert-info" role="status">
                                Estas cuentas tienen un criterio diferente de su clasificación:
                                {{ implode(', ', $cuentasConCriterioDistinto) }}.
                            </div>
                        @endif

                        <div class="table-responsive">
                            <table id="tablaClasificacionCuentas" class="table table-bordered table-striped align-middle mb-0">
                                <thead class="table-primary">
                                    <tr>
                                        <th scope="col">Cuenta</th>
                                        <th scope="col">Nombre Cuenta</th>
                                        <th scope="col">Clasificación</th>
                                        <th scope="col">Criterio</th>
                                        <th scope="col">Observación</th>
                                        <th scope="col">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($cuentas as $cuenta)
                                        <tr>
                                            <td class="text-nowrap fw-semibold">{{ $cuenta['cuenta'] }}</td>
                                            <td>{{ $cuenta['nombre'] ?? 'No encontrada en el catálogo' }}</td>
                                            <td><span class="badge {{ $cuenta['clasificacion'] === 'FIJO' ? 'bg-primary' : 'bg-info' }}">{{ $cuenta['clasificacion'] }}</span></td>
                                            <td>{{ $cuenta['criterio'] }}</td>
                                            <td>{{ $cuenta['observacion'] }}</td>
                                            <td>
                                                <form method="POST" action="{{ route('contabilidad.clasificacion-cuentas.destroy', $cuenta['cuenta']) }}" onsubmit="return confirm('¿Quitar la cuenta {{ $cuenta['cuenta'] }} de esta clasificación?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">Quitar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p id="sinClasificacionCuentas" class="text-muted text-center py-4 mb-0 d-none">No hay cuentas que coincidan con la búsqueda.</p>
                    </div>
                </div>

                <div class="modal fade" id="modalAgregarClasificacionCuenta" tabindex="-1" aria-labelledby="tituloAgregarClasificacionCuenta" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('contabilidad.clasificacion-cuentas.store') }}">
                                @csrf
                                <div class="modal-header">
                                    <h5 class="modal-title" id="tituloAgregarClasificacionCuenta">Agregar cuenta</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label for="nuevaCuentaClasificacion" class="form-label">Cuenta</label>
                                        <select id="nuevaCuentaClasificacion" name="cuenta" class="form-select" required>
                                            <option value="">Selecciona una cuenta</option>
                                            @foreach ($cuentasDisponibles as $disponible)
                                                <option value="{{ $disponible->cuenta }}" @selected(old('cuenta') === $disponible->cuenta)>{{ $disponible->cuenta }} · {{ $disponible->descripcion }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="row g-3 mb-3">
                                        <div class="col-sm-6">
                                            <label for="nuevaClasificacion" class="form-label">Clasificación</label>
                                            <select id="nuevaClasificacion" name="clasificacion" class="form-select" required>
                                                <option value="FIJO" @selected(old('clasificacion') === 'FIJO')>FIJO</option>
                                                <option value="VARIABLE" @selected(old('clasificacion') === 'VARIABLE')>VARIABLE</option>
                                            </select>
                                        </div>
                                        <div class="col-sm-6">
                                            <label for="nuevoCriterio" class="form-label">Criterio</label>
                                            <select id="nuevoCriterio" name="criterio" class="form-select" required>
                                                <option value="FIJO" @selected(old('criterio') === 'FIJO')>Estructura recurrente</option>
                                                <option value="VARIABLE" @selected(old('criterio') === 'VARIABLE')>Variable o eventual</option>
                                                <option value="EXCLUIDA" @selected(old('criterio') === 'EXCLUIDA')>Excluida para factibilidad</option>
                                            </select>
                                        </div>
                                    </div>
                                    <label for="nuevaObservacion" class="form-label">Observación</label>
                                    <textarea id="nuevaObservacion" name="observacion" class="form-control" rows="2" maxlength="1000">{{ old('observacion') }}</textarea>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary">Agregar cuenta</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        const modalAgregarClasificacionCuenta = document.getElementById('modalAgregarClasificacionCuenta');
        document.body.appendChild(modalAgregarClasificacionCuenta);

        const clasificacionSelect = document.getElementById('nuevaClasificacion');
        const criterioSelect = document.getElementById('nuevoCriterio');
        clasificacionSelect.addEventListener('change', function () {
            criterioSelect.value = this.value;
        });

        @if ($errors->any())
            new bootstrap.Modal(modalAgregarClasificacionCuenta).show();
        @endif

        document.getElementById('buscarClasificacionCuenta').addEventListener('input', function () {
            const busqueda = this.value.trim().toLocaleLowerCase('es');
            let visibles = 0;

            document.querySelectorAll('#tablaClasificacionCuentas tbody tr').forEach(function (fila) {
                const coincide = fila.textContent.toLocaleLowerCase('es').includes(busqueda);
                fila.hidden = !coincide;
                if (coincide) visibles++;
            });

            document.getElementById('sinClasificacionCuentas').classList.toggle('d-none', visibles > 0);
        });
    </script>
@endsection
