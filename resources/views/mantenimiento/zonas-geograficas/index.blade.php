@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Zonas Geográficas</h4>
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('mantenimiento.index') }}">Mantenimiento</a></li>
                                <li class="breadcrumb-item active">Zonas Geográficas</li>
                            </ol>
                        </div>
                    </div>
                </div>

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                <div class="card">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h5 class="card-title mb-1">División territorial</h5>
                            <span class="text-muted">{{ number_format($zonas->total()) }} combinaciones registradas</span>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrearZona">
                            <i class="ri-add-line me-1"></i>Nueva zona
                        </button>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-2 mb-4">
                            <div class="col-lg-8">
                                <input type="search" name="buscar" class="form-control" value="{{ request('buscar') }}"
                                    placeholder="Buscar región, provincia, municipio, ciudad, sector, barrio o paraje">
                            </div>
                            <div class="col-lg-4 d-flex gap-2">
                                <button class="btn btn-dark" type="submit"><i class="ri-search-line me-1"></i>Buscar</button>
                                <a class="btn btn-light" href="{{ route('mantenimiento.zonas-geograficas.index') }}">Limpiar</a>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Región</th>
                                        <th>Provincia</th>
                                        <th>Municipio</th>
                                        <th>Ciudad o sección</th>
                                        <th>Sector, barrio o paraje</th>
                                        <th class="text-center" style="width: 150px">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($zonas as $zona)
                                        <tr>
                                            <td>{{ $zona->region }}</td>
                                            <td>{{ $zona->provincia }}</td>
                                            <td>{{ $zona->municipio }}</td>
                                            <td>{{ $zona->ciudad_seccion }}</td>
                                            <td>{{ $zona->sector_barrio_paraje }}</td>
                                            <td class="text-center text-nowrap">
                                                <button type="button" class="btn btn-sm btn-warning btn-editar-zona"
                                                    data-url="{{ route('mantenimiento.zonas-geograficas.update', $zona) }}"
                                                    data-region="{{ $zona->region }}"
                                                    data-provincia="{{ $zona->provincia }}"
                                                    data-municipio="{{ $zona->municipio }}"
                                                    data-ciudad-seccion="{{ $zona->ciudad_seccion }}"
                                                    data-sector-barrio-paraje="{{ $zona->sector_barrio_paraje }}">
                                                    <i class="ri-pencil-line"></i>
                                                </button>
                                                @unless (auth()->user()?->hasRole('admin2'))
                                                    <form method="POST" action="{{ route('mantenimiento.zonas-geograficas.destroy', $zona) }}" class="d-inline form-eliminar-zona">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-danger"><i class="ri-delete-bin-line"></i></button>
                                                    </form>
                                                @endunless
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center text-muted py-4">No se encontraron zonas geográficas.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">{{ $zonas->links() }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @php
        $camposZona = [
            'region' => 'Región',
            'provincia' => 'Provincia',
            'municipio' => 'Municipio',
            'ciudad_seccion' => 'Ciudad o sección',
            'sector_barrio_paraje' => 'Sector, barrio o paraje',
        ];
    @endphp

    @foreach (['crear' => 'Nueva zona geográfica', 'editar' => 'Editar zona geográfica'] as $tipoModal => $tituloModal)
        <div class="modal fade" id="modal{{ ucfirst($tipoModal) }}Zona" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST" class="form-zona" id="form-{{ $tipoModal }}-zona"
                        action="{{ $tipoModal === 'crear' ? route('mantenimiento.zonas-geograficas.store') : '' }}">
                        @csrf
                        @if ($tipoModal === 'editar') @method('PUT') @endif
                        <div class="modal-header">
                            <h5 class="modal-title">{{ $tituloModal }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info py-2">Complete la ubicación respetando el orden territorial.</div>
                            <div class="row g-3">
                                @foreach ($camposZona as $campo => $etiqueta)
                                    <div class="{{ $campo === 'sector_barrio_paraje' ? 'col-12' : 'col-md-6' }}">
                                        <label class="form-label">{{ $etiqueta }}</label>
                                        <input type="text" name="{{ $campo }}" class="form-control campo-zona"
                                            data-campo="{{ $campo }}" list="lista-{{ $tipoModal }}-{{ $campo }}"
                                            maxlength="{{ ['region' => 80, 'provincia' => 100, 'municipio' => 120, 'ciudad_seccion' => 160, 'sector_barrio_paraje' => 190][$campo] }}" required>
                                        <datalist id="lista-{{ $tipoModal }}-{{ $campo }}">
                                            @if ($campo === 'region')
                                                @foreach ($regiones as $region)<option value="{{ $region }}"></option>@endforeach
                                            @endif
                                        </datalist>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const orden = ['region', 'provincia', 'municipio', 'ciudad_seccion', 'sector_barrio_paraje'];
            const urlOpciones = @json(route('mantenimiento.zonas-geograficas.opciones'));

            document.querySelectorAll('.form-zona').forEach(function (formulario) {
                formulario.querySelectorAll('.campo-zona').forEach(function (campo) {
                    campo.addEventListener('change', async function () {
                        const indice = orden.indexOf(campo.dataset.campo);
                        const siguiente = orden[indice + 1];
                        if (!siguiente) return;

                        const parametros = new URLSearchParams({ nivel: siguiente });
                        orden.slice(0, indice + 1).forEach(function (nombre) {
                            const valor = formulario.querySelector(`[name="${nombre}"]`).value.trim();
                            if (valor) parametros.set(nombre, valor);
                        });
                        const respuesta = await fetch(`${urlOpciones}?${parametros.toString()}`);
                        if (!respuesta.ok) return;
                        const opciones = await respuesta.json();
                        const lista = document.getElementById(formulario.querySelector(`[name="${siguiente}"]`).getAttribute('list'));
                        lista.replaceChildren(...opciones.map(function (opcion) {
                            const elemento = document.createElement('option');
                            elemento.value = opcion;
                            return elemento;
                        }));
                    });
                });
            });

            document.querySelectorAll('.btn-editar-zona').forEach(function (boton) {
                boton.addEventListener('click', function () {
                    const formulario = document.getElementById('form-editar-zona');
                    formulario.action = boton.dataset.url;
                    orden.forEach(function (campo) {
                        const clave = campo.replaceAll('_', '-');
                        formulario.querySelector(`[name="${campo}"]`).value = boton.dataset[clave.replace(/-([a-z])/g, (_, letra) => letra.toUpperCase())] || '';
                    });
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditarZona')).show();
                });
            });

            document.querySelectorAll('.form-eliminar-zona').forEach(function (formulario) {
                formulario.addEventListener('submit', function (evento) {
                    if (!confirm('¿Deseas eliminar esta zona geográfica?')) evento.preventDefault();
                });
            });
        });
    </script>
@endpush
