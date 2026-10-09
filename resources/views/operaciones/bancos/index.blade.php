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
                                    <label for="empresa-banco" class="form-label">Empresa</label>
                                    <select name="empresa_id" id="empresa-banco" class="form-select empresa-banco @error('empresa_id') is-invalid @enderror" required>
                                        <option value="">Selecciona una empresa</option>
                                        <option value="168" @selected(old('empresa_id') === '168')>168 - Grupo Joselito</option>
                                        <option value="169" @selected(old('empresa_id') === '169')>169 - Negosur</option>
                                    </select>
                                    @error('empresa_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <label for="cuenta-banco" class="form-label mt-3">Cuenta bancaria</label>
                                    <select name="cuenta_codigo" id="cuenta-banco" class="form-select cuenta-banco @error('cuenta_codigo') is-invalid @enderror" data-selected="{{ old('cuenta_codigo') }}" required disabled>
                                        <option value="">Selecciona primero la empresa</option>
                                    </select>
                                    @error('cuenta_codigo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div class="form-text">El nombre se toma del catálogo contable de la empresa.</div>
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
                                                <th>Empresa</th>
                                                <th>Banco</th>
                                                <th>Cuenta contable</th>
                                                <th class="text-center">Registros asociados</th>
                                                <th class="text-end">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($bancos as $banco)
                                                <tr>
                                                    <td>{{ match ($banco['modelo']->empresa_id) { '168' => 'Grupo Joselito', '169' => 'Negosur', default => 'Sin asignar' } }}</td>
                                                    <td class="fw-semibold">{{ $banco['nombre'] }}</td>
                                                    <td>
                                                        <form method="POST" action="{{ route('operaciones.bancos.update', $banco['modelo']) }}" class="d-flex flex-wrap gap-2 align-items-center">
                                                            @csrf
                                                            @method('PUT')
                                                            <select name="empresa_id" class="form-select form-select-sm empresa-banco" aria-label="Empresa de {{ $banco['nombre'] }}" required>
                                                                <option value="">Empresa</option>
                                                                <option value="168" @selected($banco['modelo']->empresa_id === '168')>168 - Grupo Joselito</option>
                                                                <option value="169" @selected($banco['modelo']->empresa_id === '169')>169 - Negosur</option>
                                                            </select>
                                                            <select name="cuenta_codigo" class="form-select form-select-sm cuenta-banco" data-selected="{{ $banco['modelo']->cuenta_codigo }}" aria-label="Cuenta bancaria de {{ $banco['nombre'] }}" required disabled>
                                                                <option value="">Selecciona la empresa</option>
                                                            </select>
                                                            <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
                                                        </form>
                                                    </td>
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

@section('script')
    <script>
        const urlCuentasBanco = @json(route('operaciones.bancos.cuentas', ['empresaId' => '__EMPRESA__']));

        async function cargarCuentasBanco(formulario) {
            const empresa = formulario.querySelector('.empresa-banco').value;
            const selector = formulario.querySelector('.cuenta-banco');
            const seleccionada = selector.dataset.selected || '';
            selector.replaceChildren(new Option(empresa ? 'Cargando cuentas...' : 'Selecciona primero la empresa', ''));
            selector.disabled = true;
            if (!empresa) return;

            try {
                const respuesta = await fetch(urlCuentasBanco.replace('__EMPRESA__', empresa), { headers: { Accept: 'application/json' } });
                if (!respuesta.ok) throw new Error('No se pudo cargar el catálogo de la empresa.');
                const datos = await respuesta.json();
                selector.replaceChildren(new Option('Selecciona una cuenta bancaria', ''));
                (datos.cuentas || []).forEach(cuenta => selector.add(new Option(`${cuenta.cuenta} - ${cuenta.descripcion}`, cuenta.cuenta)));
                selector.value = seleccionada;
                selector.disabled = false;
            } catch (error) {
                selector.replaceChildren(new Option(error.message, ''));
            }
        }

        document.querySelectorAll('.empresa-banco').forEach(empresa => {
            const formulario = empresa.closest('form');
            empresa.addEventListener('change', () => {
                formulario.querySelector('.cuenta-banco').dataset.selected = '';
                cargarCuentasBanco(formulario);
            });
            if (empresa.value) cargarCuentasBanco(formulario);
        });
    </script>
@endsection
