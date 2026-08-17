@extends('app')

@section('content')
    <style>
        .module-hub-card {
            transition: transform 0.22s cubic-bezier(0.2, 0.8, 0.2, 1),
                box-shadow 0.22s ease,
                border-color 0.22s ease;
            will-change: transform;
        }

        .module-hub-card .module-hub-icon .avatar-title,
        .module-hub-card .module-hub-icon .avatar-title i {
            color: #fff !important;
            opacity: 1 !important;
            visibility: visible !important;
        }

        .module-hub-card .module-hub-icon .avatar-title {
            display: flex !important;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            background-color: var(--vz-primary) !important;
        }

        .module-hub-card .avatar-title,
        .module-hub-card .module-hub-arrow {
            transition: transform 0.22s cubic-bezier(0.2, 0.8, 0.2, 1),
                background-color 0.22s ease,
                color 0.22s ease;
        }

        .module-hub-card:hover {
            transform: translateY(-2px);
            border-color: rgba(64, 81, 137, 0.22) !important;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.07);
        }

        .module-hub-card:hover .avatar-title {
            transform: scale(1.02);
            background-color: var(--vz-primary) !important;
            color: #fff !important;
        }

        .module-hub-card:hover .module-hub-arrow {
            transform: translate(1px, -1px);
            color: var(--vz-primary) !important;
        }

        .module-hub-favorite {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 3;
        }

        .module-hub-item {
            position: relative;
        }
    </style>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">{{ $titulo }}</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item active">{{ $breadcrumb }}</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-lg-7">
                        <div class="search-box">
                            <input type="text" id="buscarModuleHub" class="form-control"
                                placeholder="Buscar por nombre, categoria o descripcion...">
                            <i class="ri-search-line search-icon"></i>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="d-flex flex-wrap gap-2 justify-content-lg-end" id="filtrosModuleHub">
                            <button type="button" class="btn btn-primary btn-sm module-hub-filtro active" data-categoria="todos">
                                Todos
                            </button>
                            @foreach ($categorias as $categoria)
                                <button type="button" class="btn btn-soft-primary btn-sm module-hub-filtro"
                                    data-categoria="{{ strtolower($categoria) }}">
                                    {{ $categoria }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="row g-3 g-xl-4" id="listaModuleHub">
                    @foreach ($items as $item)
                        @php
                            $textoBusqueda = strtolower(implode(' ', [
                                $item['nombre'] ?? '',
                                $item['descripcion'] ?? '',
                                $item['categoria'] ?? '',
                                implode(' ', $item['tags'] ?? []),
                            ]));
                        @endphp

                        <div class="col-xxl-3 col-xl-4 col-md-6 module-hub-item"
                            data-categoria="{{ strtolower($item['categoria'] ?? '') }}"
                            data-search="{{ $textoBusqueda }}">
                            <button type="button"
                                class="btn btn-icon btn-sm btn-ghost-warning rounded-circle module-hub-favorite btn-app-favorito"
                                data-favorito-key="{{ $item['favorito_key'] }}"
                                data-favorito-variant="icon"
                                aria-label="{{ $item['es_favorito'] ? 'Quitar de favoritos' : 'Agregar a favoritos' }}"
                                aria-pressed="{{ $item['es_favorito'] ? 'true' : 'false' }}"
                                title="{{ $item['es_favorito'] ? 'Quitar de favoritos' : 'Agregar a favoritos' }}">
                                <i class="{{ $item['es_favorito'] ? 'ri-star-fill text-warning' : 'ri-star-line text-muted' }} fs-18"></i>
                            </button>
                            <a href="{{ $item['url'] }}" class="text-decoration-none d-block h-100">
                                <div class="card border h-100 module-hub-card">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="avatar-sm flex-shrink-0 module-hub-icon">
                                                <span class="avatar-title bg-primary text-white rounded">
                                                    <i class="{{ $item['icono'] ?? 'ri-apps-2-line' }} fs-4 text-white"></i>
                                                </span>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-start justify-content-between gap-2 pe-4">
                                                    <h5 class="mb-1 text-dark">{{ $item['nombre'] }}</h5>
                                                    <i class="ri-arrow-right-up-line text-muted module-hub-arrow"></i>
                                                </div>
                                                <span class="badge bg-light text-muted mb-2">
                                                    {{ $item['categoria'] }}
                                                </span>
                                                <p class="text-muted mb-0">
                                                    {{ $item['descripcion'] }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>

                <div id="sinResultadosModuleHub" class="text-center py-5 d-none">
                    <div class="avatar-md mx-auto mb-3">
                        <div class="avatar-title bg-light text-muted rounded-circle fs-2">
                            <i class="ri-search-eye-line"></i>
                        </div>
                    </div>
                    <h5 class="mb-1">Sin resultados</h5>
                    <p class="text-muted mb-0">No hay opciones que coincidan con la busqueda actual.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const input = document.getElementById('buscarModuleHub');
            const filtros = document.querySelectorAll('.module-hub-filtro');
            const items = document.querySelectorAll('.module-hub-item');
            const empty = document.getElementById('sinResultadosModuleHub');
            let categoriaActiva = 'todos';

            function aplicarFiltros() {
                const texto = (input.value || '').toLowerCase().trim();
                let visibles = 0;

                items.forEach(function (item) {
                    const coincideTexto = !texto || (item.dataset.search || '').includes(texto);
                    const coincideCategoria = categoriaActiva === 'todos' || item.dataset.categoria === categoriaActiva;
                    const visible = coincideTexto && coincideCategoria;

                    item.classList.toggle('d-none', !visible);

                    if (visible) {
                        visibles++;
                    }
                });

                empty.classList.toggle('d-none', visibles > 0);
            }

            input.addEventListener('input', aplicarFiltros);

            filtros.forEach(function (button) {
                button.addEventListener('click', function () {
                    categoriaActiva = this.dataset.categoria || 'todos';

                    filtros.forEach(function (item) {
                        item.classList.remove('active', 'btn-primary');
                        item.classList.add('btn-soft-primary');
                    });

                    this.classList.add('active', 'btn-primary');
                    this.classList.remove('btn-soft-primary');

                    aplicarFiltros();
                });
            });
        });
    </script>

@endsection
