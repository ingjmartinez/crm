@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Rentabilidad de Agencia</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('gerencia.index') }}">Gerencia</a></li>
                                    <li class="breadcrumb-item active">Rentabilidad de Agencia</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="GET" action="{{ route('gerencia.rentabilidad-agencia') }}" class="row g-3 align-items-end" id="formConsultarRentabilidadAgencia">
                                    <div class="col-12 col-md-4 col-lg-3">
                                        <label for="mesRentabilidadAgencia" class="form-label">Mes</label>
                                        <input
                                            type="month"
                                            class="form-control @error('mes') is-invalid @enderror"
                                            id="mesRentabilidadAgencia"
                                            name="mes"
                                            value="{{ old('mes', $mesSeleccionado) }}"
                                            required
                                        >
                                        @error('mes')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-12 col-md-4 col-lg-3">
                                        <label for="empresaRentabilidadAgencia" class="form-label">Empresa</label>
                                        <select
                                            class="form-select @error('empresa') is-invalid @enderror"
                                            id="empresaRentabilidadAgencia"
                                            name="empresa"
                                        >
                                            <option value="">Todos</option>
                                            @foreach ($empresas as $empresa)
                                                <option value="{{ $empresa }}" @selected(old('empresa', $empresaSeleccionada) === $empresa)>
                                                    {{ $empresa }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('empresa')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-12 col-md-4 col-lg-3">
                                        <label for="ciudadRentabilidadAgencia" class="form-label">Ciudad</label>
                                        <select
                                            class="form-select @error('ciudad') is-invalid @enderror"
                                            id="ciudadRentabilidadAgencia"
                                            name="ciudad"
                                        >
                                            <option value="">Todos</option>
                                            @foreach ($ciudades as $ciudad)
                                                <option value="{{ $ciudad }}" @selected(old('ciudad', $ciudadSeleccionada) === $ciudad)>
                                                    {{ $ciudad }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('ciudad')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-12 col-md-4 col-lg-3">
                                        <label for="rutaRentabilidadAgencia" class="form-label">Ruta</label>
                                        <select
                                            class="form-select @error('ruta') is-invalid @enderror"
                                            id="rutaRentabilidadAgencia"
                                            name="ruta"
                                        >
                                            <option value="">Todos</option>
                                            @foreach ($rutas as $ruta)
                                                <option value="{{ $ruta }}" @selected(old('ruta', $rutaSeleccionada) === $ruta)>
                                                    {{ $ruta }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('ruta')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-12 col-md-auto">
                                        <button type="submit" class="btn btn-primary" id="btnConsultarRentabilidadAgencia">
                                            <i class="ri-search-line me-1"></i>Consultar
                                        </button>
                                        <a href="{{ route('gerencia.rentabilidad-agencia') }}" class="btn btn-light ms-1">
                                            Mes actual
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <div>
                                    <h5 class="card-title mb-1">Agencias activas vendiendo</h5>
                                    <p class="text-muted mb-0">
                                        @if ($consultaRealizada)
                                            Ventas brutas correspondientes a {{ \Carbon\Carbon::createFromFormat('Y-m', $mesSeleccionado)->translatedFormat('F Y') }}.
                                        @else
                                            Selecciona el mes y presiona Consultar para generar la información.
                                        @endif
                                    </p>
                                </div>
                                <div class="d-flex flex-wrap justify-content-end gap-2">
                                    <span @class([
                                        'badge fs-6',
                                        'bg-primary-subtle text-primary' => $consultaRealizada,
                                        'bg-secondary-subtle text-secondary' => ! $consultaRealizada,
                                    ])>
                                        {{ $consultaRealizada ? number_format($agencias->count()).' agencias' : 'Sin consultar' }}
                                    </span>
                                    @if ($consultaRealizada)
                                        <span class="badge bg-success-subtle text-success fs-6">
                                            Cumplen: {{ number_format($resumenCumplimiento['cumple']) }}
                                        </span>
                                        <span class="badge bg-danger-subtle text-danger fs-6">
                                            No cumplen: {{ number_format($resumenCumplimiento['no_cumple']) }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <label for="buscarAgenciaRentabilidad" class="form-label">Buscar agencia</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ri-search-line"></i></span>
                                            <input
                                                type="search"
                                                class="form-control"
                                                id="buscarAgenciaRentabilidad"
                                                placeholder="Terminal o nombre de agencia"
                                                @disabled(! $consultaRealizada)
                                            >
                                        </div>
                                        <small class="text-muted" id="estadoBusquedaAgencia">
                                            @if ($consultaRealizada)
                                                {{ number_format($agenciasDataTable->count()) }} agencias cargadas inicialmente. La búsqueda consulta todo el resultado.
                                            @endif
                                        </small>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover align-middle mb-0 w-100" id="tableRentabilidadAgencias">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Agencia</th>
                                                <th class="text-end">Venta bruta mensual</th>
                                                <th class="text-end">Costos</th>
                                                <th class="text-end">Gastos</th>
                                                <th class="text-end">Balance</th>
                                                <th class="text-center">Cumplimiento</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @foreach ([
                    ['tipo' => 'ciudad', 'titulo' => 'Rentabilidad por ciudad', 'columna' => 'Ciudad', 'total' => 'ciudades', 'tabla' => 'tableRentabilidadCiudades', 'buscador' => 'buscarCiudadRentabilidad', 'placeholder' => 'Buscar ciudad', 'paginador' => $ciudadesResumen, 'cumplimiento' => $resumenCiudades],
                    ['tipo' => 'ruta', 'titulo' => 'Rentabilidad por ruta', 'columna' => 'Ruta', 'total' => 'rutas', 'tabla' => 'tableRentabilidadRutas', 'buscador' => 'buscarRutaRentabilidad', 'placeholder' => 'Buscar ruta', 'paginador' => $rutasResumen, 'cumplimiento' => $resumenRutas],
                ] as $tablaResumen)
                    <div class="row">
                        <div class="col-12">
                            <div class="card" data-tabla-resumen>
                                <div class="card-header d-flex align-items-center justify-content-between">
                                    <h5 class="card-title mb-0">{{ $tablaResumen['titulo'] }}</h5>
                                    <div class="d-flex flex-wrap justify-content-end gap-2">
                                        @if ($consultaRealizada)
                                            <span class="badge bg-primary-subtle text-primary fs-6">
                                                {{ number_format($tablaResumen['paginador']->count()) }} {{ $tablaResumen['total'] }}
                                            </span>
                                            <span class="badge bg-success-subtle text-success fs-6">
                                                Cumplen: {{ number_format($tablaResumen['cumplimiento']['cumple']) }}
                                            </span>
                                            <span class="badge bg-danger-subtle text-danger fs-6">
                                                No cumplen: {{ number_format($tablaResumen['cumplimiento']['no_cumple']) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-12 col-md-6 col-lg-4">
                                            <label for="{{ $tablaResumen['buscador'] }}" class="form-label">
                                                Buscar {{ strtolower($tablaResumen['columna']) }}
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="ri-search-line"></i></span>
                                                <input
                                                    type="search"
                                                    class="form-control"
                                                    id="{{ $tablaResumen['buscador'] }}"
                                                    placeholder="{{ $tablaResumen['placeholder'] }}"
                                                    data-buscador-resumen
                                                    @disabled(! $consultaRealizada)
                                                >
                                            </div>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover align-middle mb-0 w-100" id="{{ $tablaResumen['tabla'] }}" data-tabla-resumen-elemento>
                                            <thead class="table-light">
                                                <tr>
                                                    <th>{{ $tablaResumen['columna'] }}</th>
                                                    <th class="text-center">Agencias</th>
                                                    <th class="text-end">Venta bruta mensual</th>
                                                    <th class="text-end">Costos</th>
                                                    <th class="text-end">Gastos</th>
                                                    <th class="text-end">Balance</th>
                                                    <th class="text-center">Cumplimiento</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($tablaResumen['paginador'] as $resumen)
                                                    <tr>
                                                        <td class="fw-semibold text-dark">{{ $resumen['nombre'] }}</td>
                                                        <td class="text-center">
                                                            <button
                                                                type="button"
                                                                class="btn btn-link p-0 fw-semibold text-decoration-underline"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#modalAgenciasGrupo"
                                                                data-agencias-grupo
                                                                data-tipo="{{ $tablaResumen['tipo'] }}"
                                                                data-indice="{{ $loop->index }}"
                                                                data-nombre-grupo="{{ $resumen['nombre'] }}"
                                                                title="Ver agencias de {{ $resumen['nombre'] }}"
                                                            >
                                                                {{ number_format($resumen['cantidad_agencias']) }}
                                                            </button>
                                                        </td>
                                                        <td class="text-end fw-semibold">
                                                            RD$ {{ number_format($resumen['venta_bruta_mes'], 2) }}
                                                        </td>
                                                        <td class="text-end fw-semibold text-warning">
                                                            RD$ {{ number_format($resumen['costos_mes'], 2) }}
                                                        </td>
                                                        <td class="text-end fw-semibold text-danger">
                                                            RD$ {{ number_format($resumen['gastos_mes'], 2) }}
                                                        </td>
                                                        <td @class([
                                                            'text-end fw-semibold',
                                                            'text-success' => $resumen['cumple'],
                                                            'text-danger' => ! $resumen['cumple'],
                                                        ])>
                                                            RD$ {{ number_format($resumen['balance_mes'], 2) }}
                                                        </td>
                                                        <td class="text-center">
                                                            <span @class([
                                                                'badge fs-6',
                                                                'bg-success-subtle text-success' => $resumen['cumple'],
                                                                'bg-danger-subtle text-danger' => ! $resumen['cumple'],
                                                            ])>
                                                                {{ $resumen['cumple'] ? 'Cumple' : 'No cumple' }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

            </div>
        </div>
    </div>

    <div class="modal fade" id="modalAgenciasGrupo" tabindex="-1" aria-labelledby="modalAgenciasGrupoTitulo" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="modalAgenciasGrupoTitulo">Agencias</h5>
                        <span class="badge bg-primary-subtle text-primary mt-1" id="modalAgenciasGrupoCantidad">0 agencias</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Agencia</th>
                                    <th>Terminal</th>
                                </tr>
                            </thead>
                            <tbody id="modalAgenciasGrupoFilas"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        (() => {
        const agenciasRentabilidadData = {{ Illuminate\Support\Js::from($agenciasDataTable) }};
        const agenciasDetalleGrupos = {{ Illuminate\Support\Js::from($agenciasDetalleGrupos) }};
        const buscarAgenciaUrl = {{ Illuminate\Support\Js::from(route('gerencia.rentabilidad-agencia.buscar')) }};

        const escapeHtml = (value) => {
            const element = document.createElement('div');
            element.textContent = String(value ?? '');

            return element.innerHTML;
        };

        const formatMoney = (value) => Number(value || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

        const dataTableLanguage = {
            url: '/json/es-DO.json',
            emptyTable: 'No hay información para mostrar.',
            zeroRecords: 'No se encontraron resultados para la búsqueda.',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            paginate: { next: 'Siguiente', previous: 'Anterior' },
        };

        if (typeof $ === 'function' && $.fn?.DataTable) {
            const agenciasTable = $('#tableRentabilidadAgencias').DataTable({
                data: agenciasRentabilidadData,
                deferRender: true,
                responsive: true,
                autoWidth: false,
                pageLength: 10,
                lengthChange: false,
                dom: 'rtip',
                language: dataTableLanguage,
                order: [[0, 'asc']],
                columns: [
                    {
                        data: 'nombre',
                        render: function (data, type, row) {
                            if (type === 'filter') {
                                return `${data ?? ''} ${row.terminal ?? ''}`;
                            }

                            if (type !== 'display') {
                                return data;
                            }

                            return `<div class="fw-semibold text-dark">${escapeHtml(data)}</div>`
                                + `<div class="small text-muted">Terminal: <span class="fw-medium">${escapeHtml(row.terminal)}</span></div>`;
                        },
                    },
                    {
                        data: 'venta_bruta',
                        className: 'text-end fw-semibold',
                        render: (data, type) => type === 'display' ? `RD$ ${formatMoney(data)}` : Number(data || 0),
                    },
                    {
                        data: 'costos',
                        className: 'text-end fw-semibold text-warning',
                        render: (data, type) => type === 'display' ? `RD$ ${formatMoney(data)}` : Number(data || 0),
                    },
                    {
                        data: 'gastos',
                        className: 'text-end fw-semibold text-danger',
                        render: (data, type) => type === 'display' ? `RD$ ${formatMoney(data)}` : Number(data || 0),
                    },
                    {
                        data: 'balance',
                        className: 'text-end fw-semibold',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return Number(data || 0);
                            }

                            const color = Number(data || 0) >= 0 ? 'text-success' : 'text-danger';

                            return `<span class="${color}">RD$ ${formatMoney(data)}</span>`;
                        },
                    },
                    {
                        data: 'cumple',
                        className: 'text-center',
                        render: function (data, type) {
                            const cumple = Boolean(data);

                            if (type !== 'display') {
                                return cumple ? 'Cumple' : 'No cumple';
                            }

                            const color = cumple
                                ? 'bg-success-subtle text-success'
                                : 'bg-danger-subtle text-danger';

                            return `<span class="badge fs-6 ${color}">${cumple ? 'Cumple' : 'No cumple'}</span>`;
                        },
                    },
                ],
            });

            const agenciaSearchInput = document.getElementById('buscarAgenciaRentabilidad');
            const agenciaSearchStatus = document.getElementById('estadoBusquedaAgencia');
            const agenciaSearchInitialStatus = agenciaSearchStatus?.textContent.trim() ?? '';
            let agenciaSearchTimeout;
            let agenciaSearchController;

            agenciaSearchInput?.addEventListener('input', function () {
                const searchTerm = this.value.trim();

                window.clearTimeout(agenciaSearchTimeout);
                agenciaSearchController?.abort();

                if (searchTerm.length < 2) {
                    agenciasTable.clear().rows.add(agenciasRentabilidadData).search(searchTerm).draw();

                    if (agenciaSearchStatus) {
                        agenciaSearchStatus.textContent = agenciaSearchInitialStatus;
                    }

                    return;
                }

                if (agenciaSearchStatus) {
                    agenciaSearchStatus.textContent = 'Buscando en todas las agencias...';
                }

                agenciaSearchTimeout = window.setTimeout(async () => {
                    agenciaSearchController = new AbortController();

                    try {
                        const reportForm = document.getElementById('formConsultarRentabilidadAgencia');
                        const query = new URLSearchParams(new FormData(reportForm));
                        query.set('buscar', searchTerm);

                        const response = await fetch(`${buscarAgenciaUrl}?${query.toString()}`, {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            signal: agenciaSearchController.signal,
                        });

                        if (!response.ok) {
                            throw new Error('No se pudo completar la búsqueda.');
                        }

                        const result = await response.json();

                        if (agenciaSearchInput.value.trim() !== searchTerm) {
                            return;
                        }

                        const rows = Array.isArray(result.data) ? result.data : [];
                        const total = Number(result.total || 0);
                        agenciasTable.search('').clear().rows.add(rows).draw();

                        if (agenciaSearchStatus) {
                            agenciaSearchStatus.textContent = total > rows.length
                                ? `Mostrando ${rows.length} de ${total} coincidencias. Especifica más la búsqueda.`
                                : `${total} coincidencias encontradas.`;
                        }
                    } catch (error) {
                        if (error.name === 'AbortError') {
                            return;
                        }

                        if (agenciaSearchStatus) {
                            agenciaSearchStatus.textContent = 'No se pudo completar la búsqueda. Inténtalo nuevamente.';
                        }
                    }
                }, 350);
            });

            document.querySelectorAll('[data-tabla-resumen]').forEach((container) => {
                const tableElement = container.querySelector('[data-tabla-resumen-elemento]');
                const searchInput = container.querySelector('[data-buscador-resumen]');

                if (!tableElement || !searchInput) {
                    return;
                }

                const summaryTable = $(tableElement).DataTable({
                    responsive: true,
                    autoWidth: false,
                    pageLength: 15,
                    lengthChange: false,
                    dom: 'rtip',
                    language: dataTableLanguage,
                    order: [[0, 'asc']],
                });

                searchInput.addEventListener('input', function () {
                    summaryTable.column(0).search(this.value).draw();
                });
            });
        }

        const modalAgenciasGrupo = document.getElementById('modalAgenciasGrupo');

        modalAgenciasGrupo?.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const tipo = button?.dataset.tipo;
            const indice = Number(button?.dataset.indice);
            const nombreGrupo = button?.dataset.nombreGrupo ?? '';
            const agencias = agenciasDetalleGrupos[tipo]?.[indice] ?? [];
            const title = document.getElementById('modalAgenciasGrupoTitulo');
            const count = document.getElementById('modalAgenciasGrupoCantidad');
            const rows = document.getElementById('modalAgenciasGrupoFilas');

            if (title) {
                title.textContent = `Agencias de ${tipo}: ${nombreGrupo}`;
            }
            if (count) {
                count.textContent = `${agencias.length} ${agencias.length === 1 ? 'agencia' : 'agencias'}`;
            }
            if (!rows) {
                return;
            }

            rows.replaceChildren();
            agencias.forEach((agencia) => {
                const row = document.createElement('tr');
                const nameCell = document.createElement('td');
                const terminalCell = document.createElement('td');

                nameCell.textContent = agencia.nombre;
                terminalCell.textContent = agencia.terminal;
                row.append(nameCell, terminalCell);
                rows.appendChild(row);
            });
        });

        document.getElementById('formConsultarRentabilidadAgencia')?.addEventListener('submit', function () {
            const button = document.getElementById('btnConsultarRentabilidadAgencia');

            if (button) {
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Consultando...';
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Consultando datos',
                    text: 'Calculando las ventas brutas del mes seleccionado...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading(),
                });
            }
        });
        })();
    </script>
@endsection
