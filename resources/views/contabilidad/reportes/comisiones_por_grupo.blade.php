@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Comisiones por Grupo</h4>
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('contabilidad.index') }}">Contabilidad</a></li>
                                <li class="breadcrumb-item active">Comisiones por grupo</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Subgrupos</h5>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="{{ route('contabilidad.reportes.comisiones-por-grupo', ['fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin]) }}" class="btn btn-primary btn-sm">
                                        <i class="ri-refresh-line align-bottom me-1"></i>Actualizar
                                    </a>
                                    <a href="{{ route('contabilidad.reportes.comisiones') }}" class="btn btn-outline-secondary btn-sm">
                                        <i class="ri-arrow-left-line align-bottom me-1"></i>Comisiones por acuerdo
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                @if(session('success'))
                                    <div class="alert alert-success">{{ session('success') }}</div>
                                @endif

                                @if(session('error'))
                                    <div class="alert alert-danger">{{ session('error') }}</div>
                                @endif

                                <form action="{{ route('contabilidad.reportes.comisiones-por-grupo') }}" method="GET" class="row g-3 align-items-end mb-3">
                                    <div class="col-12 col-md-3 col-lg-2">
                                        <label for="fechaInicioGrupo" class="form-label">Fecha inicio</label>
                                        <input type="date" id="fechaInicioGrupo" name="fecha_inicio" class="form-control" value="{{ $fechaInicio }}">
                                    </div>
                                    <div class="col-12 col-md-3 col-lg-2">
                                        <label for="fechaFinGrupo" class="form-label">Fecha fin</label>
                                        <input type="date" id="fechaFinGrupo" name="fecha_fin" class="form-control" value="{{ $fechaFin }}">
                                    </div>
                                    <div class="col-12 col-md-3 col-lg-2">
                                        <button type="submit" class="btn btn-secondary w-100">
                                            <i class="ri-filter-3-line align-bottom me-1"></i>Aplicar
                                        </button>
                                    </div>
                                </form>

                                <div class="row mb-3">
                                    <div class="col-12 col-md-5 col-lg-4">
                                        <label for="buscarGrupo" class="form-label">Buscar subgrupo</label>
                                        <input type="text" id="buscarGrupo" class="form-control" placeholder="Escribe id, nombre o agencia...">
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle mb-0" id="tablaComisionesGrupo">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center" style="width:120px;">Id SubGrupo</th>
                                                <th>SubGrupo</th>
                                                <th>Nombre SubGrupo</th>
                                                <th class="text-center" style="width:120px;">Agencias</th>
                                                <th class="text-end" style="width:150px;">Porcentaje</th>
                                                <th class="text-end">Venta base</th>
                                                <th class="text-end">Comision</th>
                                                <th class="text-center" style="width:90px;">Accion</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($grupos as $grupo)
                                                @php
                                                    $calculoGrupo = $calculos[$grupo['subgrupo']] ?? null;
                                                    $porcentajeGrupo = $calculoGrupo['porcentaje'] ?? 0;
                                                @endphp
                                                <tr>
                                                    <td class="text-center fw-semibold">{{ $grupo['subgrupo'] }}</td>
                                                    <td>{{ $grupo['subgrupo_original'] ?? ($grupo['subgrupo'] . '-' . $grupo['nombre_subgrupo']) }}</td>
                                                    <td>{{ $grupo['nombre_subgrupo'] ?: '-' }}</td>
                                                    <td class="text-center">
                                                        <button
                                                            type="button"
                                                            class="btn btn-info btn-sm btn-ver-agencias-grupo"
                                                            title="Ver agencias del subgrupo"
                                                            data-grupo="{{ $grupo['subgrupo_original'] ?? ($grupo['subgrupo'] . '-' . $grupo['nombre_subgrupo']) }}"
                                                            data-agencias='@json($grupo['agencias']->values())'>
                                                            {{ $grupo['agencias']->count() }}
                                                        </button>
                                                    </td>
                                                    <td class="text-end">
                                                        <div class="input-group input-group-sm">
                                                            <input
                                                                type="number"
                                                                name="porcentaje"
                                                                form="form-calcular-grupo-{{ $grupo['subgrupo'] }}"
                                                                class="form-control text-end"
                                                                min="0"
                                                                max="100"
                                                                step="0.01"
                                                                value="{{ number_format((float) $porcentajeGrupo, 2, '.', '') }}">
                                                            <span class="input-group-text">%</span>
                                                        </div>
                                                    </td>
                                                    <td class="text-end">
                                                        @if($calculoGrupo)
                                                            ${{ number_format((float) $calculoGrupo['venta_base'], 2) }}
                                                        @else
                                                            <span class="text-muted">Sin calcular</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-end">
                                                        @if($calculoGrupo)
                                                            ${{ number_format((float) $calculoGrupo['monto_comision'], 2) }}
                                                        @else
                                                            <span class="text-muted">Sin calcular</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-center">
                                                        <form id="form-calcular-grupo-{{ $grupo['subgrupo'] }}" action="{{ route('contabilidad.reportes.comisiones-por-grupo.calcular', $grupo['subgrupo']) }}" method="POST" class="form-calcular-grupo" data-grupo="{{ $grupo['subgrupo_original'] ?? ($grupo['subgrupo'] . '-' . $grupo['nombre_subgrupo']) }}">
                                                            @csrf
                                                            <input type="hidden" name="fecha_inicio" value="{{ $fechaInicio }}">
                                                            <input type="hidden" name="fecha_fin" value="{{ $fechaFin }}">
                                                            <button type="submit" class="btn btn-sm text-white" style="background-color:#0f766e;border-color:#0f766e;" title="Calcular subgrupo">
                                                                <i class="ri-calculator-line"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="8" class="text-center text-muted">No hay subgrupos disponibles.</td>
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

    <div class="modal fade" id="verAgenciasGrupoModal" tabindex="-1" aria-labelledby="verAgenciasGrupoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="verAgenciasGrupoModalLabel">Agencias del subgrupo (<span id="contadorAgenciasGrupo">0</span>)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Subgrupo: <strong id="nombreGrupoAgencias">-</strong></p>
                    <div id="contenidoAgenciasGrupo" class="border rounded p-2" style="max-height: 320px; overflow-y: auto;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const buscarGrupo = document.getElementById('buscarGrupo');
        const filas = document.querySelectorAll('#tablaComisionesGrupo tbody tr');
        const modalAgenciasElement = document.getElementById('verAgenciasGrupoModal');
        const modalAgencias = new bootstrap.Modal(modalAgenciasElement);
        const nombreGrupoAgencias = document.getElementById('nombreGrupoAgencias');
        const contadorAgenciasGrupo = document.getElementById('contadorAgenciasGrupo');
        const contenidoAgenciasGrupo = document.getElementById('contenidoAgenciasGrupo');

        function filtrarTabla() {
            const termino = (buscarGrupo?.value || '').toLowerCase().trim();

            filas.forEach(function (fila) {
                const texto = (fila.textContent || '').toLowerCase();
                fila.style.display = !termino || texto.includes(termino) ? '' : 'none';
            });
        }

        if (buscarGrupo) {
            buscarGrupo.addEventListener('input', filtrarTabla);
        }

        function escaparHtml(texto) {
            return String(texto || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        document.querySelectorAll('.btn-ver-agencias-grupo').forEach(function (button) {
            button.addEventListener('click', function () {
                const grupo = this.dataset.grupo || '-';
                const agencias = JSON.parse(this.dataset.agencias || '[]');

                nombreGrupoAgencias.textContent = grupo;
                contadorAgenciasGrupo.textContent = String(agencias.length || 0);

                if (!agencias.length) {
                    contenidoAgenciasGrupo.innerHTML = '<p class="text-muted mb-0">No hay agencias registradas para este subgrupo.</p>';
                } else {
                    contenidoAgenciasGrupo.innerHTML = agencias
                        .map(function (agencia) {
                            const terminal = agencia.terminal || '-';
                            const nombreAgencia = agencia.nombre_agencia || agencia.agencia || 'Sin nombre';
                            return `<div class="py-1 border-bottom">${escaparHtml(terminal)} - ${escaparHtml(nombreAgencia)}</div>`;
                        })
                        .join('');
                }

                modalAgencias.show();
            });
        });

        document.querySelectorAll('.form-calcular-grupo').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (form.dataset.enviando === '1') {
                    return;
                }

                const grupo = form.dataset.grupo || 'este subgrupo';
                event.preventDefault();

                if (window.Swal && typeof window.Swal.fire === 'function') {
                    window.Swal.fire({
                        icon: 'question',
                        title: 'Calcular comision por grupo',
                        text: `Se consultaran las ventas de las agencias del subgrupo ${grupo} para el periodo seleccionado.`,
                        showCancelButton: true,
                        confirmButtonText: 'Si, calcular',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#0f766e',
                        cancelButtonColor: '#f06548',
                        reverseButtons: true,
                    }).then(function (resultado) {
                        if (!resultado.isConfirmed) {
                            return;
                        }

                        window.Swal.fire({
                            title: 'Consultando ventas',
                            text: 'Calculando ventas por agencias del subgrupo...',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            didOpen: function () {
                                window.Swal.showLoading();
                            },
                        });

                        form.dataset.enviando = '1';
                        form.submit();
                    });
                    return;
                }

                if (confirm('Calcular ventas para ' + grupo + '?')) {
                    form.dataset.enviando = '1';
                    form.submit();
                }
            });
        });
    });
</script>
@endsection
