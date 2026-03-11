@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Meta Incentivo</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="/">Inicio</a></li>
                                    <li class="breadcrumb-item">Comercial</li>
                                    <li class="breadcrumb-item active">Meta Incentivo</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="GET" action="{{ route('comercial.meta-incentivo') }}" class="row g-2 align-items-end" id="form-filtro-meta-incentivo">
                                    <input type="hidden" name="aplicar" value="1">
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label class="form-label">Año</label>
                                        <input type="number" min="2000" max="2100" name="anio" class="form-control" value="{{ $anio }}" required>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label class="form-label">Mes</label>
                                        <select name="mes" class="form-select" required>
                                            @for($m = 1; $m <= 12; $m++)
                                                <option value="{{ $m }}" {{ (int) $mes === $m ? 'selected' : '' }}>
                                                    {{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}
                                                </option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label class="form-label">Sistema</label>
                                        <select name="sistema" class="form-select">
                                            <option value="">Todos</option>
                                            @foreach($sistemas as $itemSistema)
                                                <option value="{{ $itemSistema }}" {{ $sistema === $itemSistema ? 'selected' : '' }}>{{ $itemSistema }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label class="form-label">Coordinador</label>
                                        <select name="coordinador" class="form-select">
                                            <option value="">Todos</option>
                                            @foreach(($coordinadores ?? []) as $itemCoordinador)
                                                <option value="{{ $itemCoordinador }}" {{ ($coordinador ?? '') === $itemCoordinador ? 'selected' : '' }}>{{ $itemCoordinador }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2 d-grid">
                                        <button type="submit" class="btn btn-primary w-100" id="btn-filtrar-meta-incentivo">
                                            <i class="ri-filter-3-line me-1"></i>Filtrar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        @if($filtrosAplicados ?? false)
                            <div class="alert alert-info py-2 mb-3" role="alert">
                                <strong>Rango aplicado (3 meses):</strong> {{ $fechaInicio }} al {{ $fechaFin }}
                            </div>
                        @else
                            <div class="alert alert-warning py-2 mb-3" role="alert">
                                <strong>Sin resultados cargados:</strong> selecciona los filtros y presiona <strong>Filtrar</strong> para consultar la información.
                            </div>
                        @endif
                    </div>

                    <div class="col-12">
                        <div class="card">
                            @php
                                $estadoAgencias = [];

                                foreach (($reporte ?? collect()) as $itemReporte) {
                                    $agenciaId = (string) ($itemReporte->agencia_id ?? '');
                                    $metaItem = (float) ($itemReporte->meta_incremental ?? 0);
                                    $ventaPosteriorItem = (float) ($itemReporte->total_venta_mes_posterior ?? 0);
                                    $cumpleItem = $metaItem <= 0 || $ventaPosteriorItem >= $metaItem;

                                    if (!array_key_exists($agenciaId, $estadoAgencias)) {
                                        $estadoAgencias[$agenciaId] = true;
                                    }

                                    if (!$cumpleItem) {
                                        $estadoAgencias[$agenciaId] = false;
                                    }
                                }

                                $agenciasCumplen = count(array_filter($estadoAgencias, fn($estado) => $estado === true));
                                $agenciasNoCumplen = count(array_filter($estadoAgencias, fn($estado) => $estado === false));
                                $totalAgenciasEvaluadas = $agenciasCumplen + $agenciasNoCumplen;
                                $porcentajeGlobalCumplimientoAgencias = $totalAgenciasEvaluadas > 0
                                    ? ($agenciasCumplen / $totalAgenciasEvaluadas) * 100
                                    : 0;

                                $metaGlobalTotal = collect($reporte ?? [])->sum(function ($item) {
                                    return (float) ($item->meta_incremental ?? 0);
                                });

                                $ventaGlobalPosteriorTotal = collect($reporte ?? [])->sum(function ($item) {
                                    return (float) ($item->total_venta_mes_posterior ?? 0);
                                });

                                if ($metaGlobalTotal <= 0) {
                                    $porcentajeGlobalMeta = 100;
                                } else {
                                    $porcentajeGlobalMeta = ($ventaGlobalPosteriorTotal / $metaGlobalTotal) * 100;
                                }

                                $mesPosteriorNombre = \Carbon\Carbon::create((int) $anio, (int) $mes, 1)
                                    ->addMonth()
                                    ->locale('es')
                                    ->translatedFormat('F');
                                $etiquetaMesPosterior = 'Ventas de ' . ucfirst($mesPosteriorNombre);

                                $claseBadgeGlobalMeta = $porcentajeGlobalMeta >= 100
                                    ? 'bg-success'
                                    : ($porcentajeGlobalMeta >= 80 ? 'bg-warning text-dark' : 'bg-danger');
                            @endphp
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <div>
                                    <h5 class="card-title mb-0">Proceso del Calculo de Meta</h5>
                                    <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
                                        <span class="badge bg-success">Agencias que cumplen: {{ number_format($agenciasCumplen, 0) }}</span>
                                        <span class="badge bg-danger">Agencias que no cumplen: {{ number_format($agenciasNoCumplen, 0) }}</span>
                                        <span class="badge {{ $claseBadgeGlobalMeta }}">Cumplimiento global meta: {{ number_format($porcentajeGlobalMeta, 2) }}%</span>
                                        <span class="badge bg-primary">Cumplimiento global agencias: {{ number_format($porcentajeGlobalCumplimientoAgencias, 2) }}%</span>
                                    </div>
                                </div>
                                <div class="row g-2 w-100 mt-2 mt-md-0 justify-content-md-end">
                                    <div class="col-12 col-md-6 col-xl-3 d-grid">
                                        <select class="form-select form-select-sm h-100" id="filtro-cumplimiento-meta-incentivo" name="cumplimiento" form="form-filtro-meta-incentivo">
                                            <option value="" {{ ($cumplimiento ?? '') === '' ? 'selected' : '' }}>Todas las agencias</option>
                                            <option value="cumple" {{ ($cumplimiento ?? '') === 'cumple' ? 'selected' : '' }}>Agencias que cumplen</option>
                                            <option value="no-cumple" {{ ($cumplimiento ?? '') === 'no-cumple' ? 'selected' : '' }}>Agencias que no cumplen</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-3 d-grid">
                                        <input type="text" class="form-control form-control-sm h-100" id="buscar-agencia-meta-incentivo" placeholder="Buscar por nombre o código">
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-3 d-grid">
                                        <span class="badge bg-primary-subtle text-primary d-flex align-items-center justify-content-center w-100">{{ number_format(($reporte ?? collect())->count(), 0) }} registros</span>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-3 d-grid">
                                        @if(($filtrosAplicados ?? false) && ($reporte ?? collect())->count() > 0)
                                            <a href="{{ route('comercial.meta-incentivo.export', ['anio' => $anio, 'mes' => $mes, 'sistema' => $sistema, 'coordinador' => ($coordinador ?? ''), 'cumplimiento' => ($cumplimiento ?? '')]) }}" class="btn btn-success btn-sm w-100">
                                                <i class="ri-file-excel-2-line me-1"></i>Exportar a Excel
                                            </a>
                                        @else
                                            <button type="button" class="btn btn-success btn-sm w-100" disabled>
                                                <i class="ri-file-excel-2-line me-1"></i>Exportar a Excel
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle mb-0" id="table-meta-incentivo">
                                        <thead>
                                            <tr>
                                                <th>Agencia</th>
                                                <th>Coordinador</th>
                                                <th>Tipo</th>
                                                <th>BaseT</th>
                                                <th>BaseAjustada</th>
                                                <th>Nivel</th>
                                                <th>Incremetal</th>
                                                <th>Meta Incremental</th>
                                                <th>{{ $etiquetaMesPosterior }}</th>
                                                <th>Cumplimiento Meta</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($reporte as $row)
                                                @php
                                                    $metaIncremental = (float) ($row->meta_incremental ?? 0);
                                                    $ventaPosterior = (float) ($row->total_venta_mes_posterior ?? 0);

                                                    if ($metaIncremental <= 0) {
                                                        $cumpleMeta = true;
                                                        $porcentajeCumplido = 100.0;
                                                        $porcentajeFaltante = 0.0;
                                                    } else {
                                                        $porcentajeCumplido = min(100, ($ventaPosterior / $metaIncremental) * 100);
                                                        $porcentajeFaltante = max(0, 100 - $porcentajeCumplido);
                                                        $cumpleMeta = $ventaPosterior >= $metaIncremental;
                                                    }
                                                @endphp
                                                <tr data-cumplimiento="{{ $cumpleMeta ? 'cumple' : 'no-cumple' }}">
                                                    <td>
                                                        <div class="fw-medium">{{ $row->nombre_agencia }}</div>
                                                        <small class="text-muted">Código: {{ $row->agencia_id }}</small>
                                                    </td>
                                                    <td>{{ $row->coordinador }}</td>
                                                    <td class="text-capitalize">{{ $row->tipo ?: '-' }}</td>
                                                    <td>RD$ {{ number_format((float) $row->ventas_3_meses, 2) }}</td>
                                                    <td>RD$ {{ number_format((float) $row->promedio_3_meses, 2) }}</td>
                                                    <td>{{ $row->nivel ?: '-' }}</td>
                                                    <td>RD$ {{ number_format((float) $row->incremetal, 2) }}</td>
                                                    <td>RD$ {{ number_format((float) $row->meta_incremental, 2) }}</td>
                                                    <td>RD$ {{ number_format((float) $row->total_venta_mes_posterior, 2) }}</td>
                                                    <td>
                                                        @if($cumpleMeta)
                                                            <span class="badge bg-success">Cumple 100%</span>
                                                        @else
                                                            <span class="badge bg-danger">Falta {{ number_format($porcentajeFaltante, 2) }}% para alcanzar el 100%</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="10" class="text-center text-muted">
                                                        {{ ($filtrosAplicados ?? false) ? 'No hay datos para los filtros seleccionados.' : 'Aplique los filtros y presione Filtrar para cargar la información.' }}
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
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
    document.addEventListener('DOMContentLoaded', function () {
        const formFiltro = document.getElementById('form-filtro-meta-incentivo');
        const btnFiltrar = document.getElementById('btn-filtrar-meta-incentivo');
        const inputBuscarAgencia = document.getElementById('buscar-agencia-meta-incentivo');
        const selectCumplimiento = document.getElementById('filtro-cumplimiento-meta-incentivo');

        if (!formFiltro || !btnFiltrar) return;

        formFiltro.addEventListener('submit', function () {
            btnFiltrar.disabled = true;
            Swal.fire({
                title: 'Cargando...',
                text: 'Aplicando filtros, por favor espera.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => Swal.showLoading(),
            });
        });

        if (window.$ && $.fn.DataTable && $('#table-meta-incentivo').length) {
            const dt = $('#table-meta-incentivo').DataTable({
                destroy: true,
                responsive: true,
                language: {
                    url: '/json/es-DO.json',
                    search: 'Buscar:',
                    lengthMenu: 'Mostrar _MENU_ registros',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                    paginate: { first: 'Primera', last: 'Última', next: 'Siguiente', previous: 'Anterior' }
                },
                dom: 'lrtip',
                order: [[3, 'desc']],
            });

            const filtroCumplimientoDt = function (settings, data, dataIndex) {
                if (!settings || !settings.nTable || settings.nTable.id !== 'table-meta-incentivo') {
                    return true;
                }

                const valorFiltro = (selectCumplimiento && selectCumplimiento.value) ? selectCumplimiento.value : '';
                if (!valorFiltro) {
                    return true;
                }

                const fila = dt.row(dataIndex).node();
                if (!fila) {
                    return true;
                }

                const estadoCumplimiento = fila.getAttribute('data-cumplimiento') || '';
                return estadoCumplimiento === valorFiltro;
            };

            $.fn.dataTable.ext.search.push(filtroCumplimientoDt);

            if (inputBuscarAgencia) {
                inputBuscarAgencia.addEventListener('input', function () {
                    dt.search(this.value || '').draw();
                });
            }

            if (selectCumplimiento) {
                selectCumplimiento.addEventListener('change', function () {
                    dt.draw();
                });
            }
        }
    });
</script>
@endsection
