@extends('app')

@section('content')
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <div>
                            <h4 class="mb-sm-0">Seguimiento de Agencia</h4>
                            @if ($debeConsultar)
                                <small class="text-muted">Información histórica cerrada hasta {{ $fechaFin->locale('es')->translatedFormat('l d \d\e F \d\e Y') }}</small>
                            @else
                                <small class="text-muted">Selecciona el mes que deseas analizar</small>
                            @endif
                        </div>
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('gerencia.index') }}">Gerencia</a></li>
                            <li class="breadcrumb-item active">Seguimiento de Agencia</li>
                        </ol>
                    </div>
                </div>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('gerencia.seguimiento-agencia') }}" id="form-seguimiento" class="row g-2 align-items-end">
                        <input type="hidden" name="consultar" value="1">
                        <input type="hidden" name="meta_tradicional" id="meta-tradicional" value="{{ $metas['tradicional'] }}">
                        <input type="hidden" name="meta_no_tradicional" id="meta-no-tradicional" value="{{ $metas['no_tradicional'] }}">
                        <input type="hidden" name="meta_recargas" id="meta-recargas" value="{{ $metas['recargas'] }}">
                        <div class="col-md-2">
                            <label class="form-label">Mes a consultar</label>
                            <input type="month" class="form-control" name="mes" value="{{ $mesSeleccionado }}" max="{{ now()->format('Y-m') }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sistema</label>
                            <select class="form-select" name="sistema">
                                <option value="lotobet" @selected($filtros['sistema'] === 'lotobet')>Lotobet</option>
                                <option value="lotonet" @selected($filtros['sistema'] === 'lotonet')>Lotonet</option>
                                <option value="todos" @selected($filtros['sistema'] === 'todos')>Todos (puede tardar más)</option>
                            </select>
                        </div>
                        @foreach ([
                            'empresa' => ['Empresa', $opciones['empresas']],
                            'ciudad' => ['Ciudad', $opciones['ciudades']],
                            'coordinador' => ['Coordinador', $opciones['coordinadores']],
                            'ruta' => ['Ruta', $opciones['rutas']],
                            'agencia' => ['Agencia', $opciones['agencias']],
                        ] as $campo => [$etiqueta, $valores])
                            <div class="col-md-2">
                                <label class="form-label">{{ $etiqueta }}</label>
                                <select class="form-select" name="{{ $campo }}">
                                    <option value="">Todas</option>
                                    @foreach ($valores as $valor)
                                        <option value="{{ $valor }}" @selected(($filtros[$campo] ?? '') === $valor)>{{ $valor }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                        <div class="col-md-4">
                            <label class="form-label" for="buscar-agencia-terminal">Nombre de agencia o terminal</label>
                            <input type="search" class="form-control" id="buscar-agencia-terminal" name="buscar" value="{{ $filtros['buscar'] ?? '' }}" placeholder="Ej.: Agencia Centro o 00123">
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2 mt-3">
                            <button class="btn btn-primary" type="submit" id="btn-generar-seguimiento"><i class="ri-bar-chart-box-line me-1"></i>Generar informe</button>
                            <button class="btn btn-soft-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalMetasSeguimiento"><i class="ri-settings-3-line me-1"></i>Configurar metas</button>
                            <a class="btn btn-light" href="{{ route('gerencia.seguimiento-agencia') }}">Limpiar</a>
                            @if ($debeConsultar)
                                <a class="btn btn-success ms-sm-auto" href="{{ route('gerencia.seguimiento-agencia.export.excel', request()->query()) }}"><i class="ri-file-excel-2-line me-1"></i>Excel</a>
                                <a class="btn btn-danger" href="{{ route('gerencia.seguimiento-agencia.export.pdf', request()->query()) }}"><i class="ri-file-pdf-2-line me-1"></i>PDF</a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            @if (! $debeConsultar)
                <div class="card">
                    <div class="card-body text-center py-5">
                        <div class="avatar-lg mx-auto mb-3"><span class="avatar-title rounded-circle bg-primary-subtle text-primary fs-2"><i class="ri-calendar-2-line"></i></span></div>
                        <h5>El informe está listo para consultar</h5>
                        <p class="text-muted mb-0">Elige un mes y presiona <strong>Generar informe</strong>. La información de ventas no se cargará automáticamente.</p>
                    </div>
                </div>
            @else
            <div class="alert alert-info d-flex align-items-center gap-2">
                <i class="ri-information-line fs-5"></i>
                <span>El período incluye {{ $dias_analizados }} días. {{ $mesSeleccionado === now()->format('Y-m') ? 'No se incluye el día de hoy porque sus ventas todavía están abiertas.' : 'Se está mostrando el mes completo seleccionado.' }}</span>
            </div>

            <div class="row g-4 mb-5">
                @foreach ([
                    ['Meta acumulada', $resumen['meta_acumulada'], 'ri-flag-line', 'primary', true],
                    ['Venta acumulada', $resumen['venta'], 'ri-money-dollar-circle-line', 'success', true],
                    ['Cumplimiento', $resumen['cumplimiento'], 'ri-percent-line', $resumen['cumplimiento'] >= 100 ? 'success' : ($resumen['cumplimiento'] >= 85 ? 'warning' : 'danger'), false],
                    ['Brecha', $resumen['brecha'], 'ri-scales-3-line', $resumen['brecha'] >= 0 ? 'success' : 'danger', true],
                    ['Promedio diario', $resumen['promedio_diario'], 'ri-calendar-check-line', 'info', true],
                    ['Proyección mensual', $resumen['proyeccion'], 'ri-line-chart-line', 'secondary', true],
                ] as [$titulo, $valor, $icono, $color, $moneda])
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <div class="card border-{{ $color }} border-opacity-25 h-100 mb-0">
                            <div class="card-body">
                                <div class="d-flex justify-content-between"><small class="text-muted">{{ $titulo }}</small><i class="{{ $icono }} text-{{ $color }}"></i></div>
                                <h5 class="mt-2 mb-0 text-{{ $color }}">{{ $moneda ? 'RD$ '.number_format($valor, 2) : number_format($valor, 1).'%' }}</h5>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row g-4 mb-4">
                <div class="col-xl-5">
                    <div class="card h-100 mb-0">
                        <div class="card-header"><h5 class="card-title mb-0">Resumen por producto</h5></div>
                        <div class="card-body table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Producto</th><th>Meta</th><th>Venta</th><th>Cumplimiento</th><th>Brecha</th></tr></thead>
                                <tbody>
                                @foreach ($por_producto as $producto)
                                    <tr>
                                        <td class="fw-medium">{{ $producto['nombre'] }}</td>
                                        <td>RD$ {{ number_format($producto['meta'], 2) }}</td>
                                        <td>RD$ {{ number_format($producto['venta'], 2) }}</td>
                                        <td><span class="badge bg-{{ $producto['cumplimiento'] >= 100 ? 'success' : ($producto['cumplimiento'] >= 85 ? 'warning' : 'danger') }}-subtle text-{{ $producto['cumplimiento'] >= 100 ? 'success' : ($producto['cumplimiento'] >= 85 ? 'warning' : 'danger') }}">{{ number_format($producto['cumplimiento'], 1) }}%</span></td>
                                        <td class="{{ $producto['brecha'] >= 0 ? 'text-success' : 'text-danger' }}">RD$ {{ number_format($producto['brecha'], 2) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-xl-7">
                    <div class="card h-100 mb-0">
                        <div class="card-header"><h5 class="card-title mb-0">Semáforo de seguimiento</h5></div>
                        <div class="card-body">
                            <div class="row text-center g-3">
                                <div class="col-4"><div class="p-3 bg-success-subtle rounded"><h3 class="text-success">{{ $resumen['en_meta'] }}</h3><span>Cumple ≥ 100%</span></div></div>
                                <div class="col-4"><div class="p-3 bg-warning-subtle rounded"><h3 class="text-warning">{{ $resumen['en_seguimiento'] }}</h3><span>Seguimiento 85–99%</span></div></div>
                                <div class="col-4"><div class="p-3 bg-danger-subtle rounded"><h3 class="text-danger">{{ $resumen['criticos'] }}</h3><span>Crítica &lt; 85%</span></div></div>
                            </div>
                            <p class="text-muted mt-3 mb-0">El semáforo se calcula por agencia y producto. Así se identifica exactamente cuál línea necesita intervención.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="card-title mb-0">Detalle Empresa → Ciudad → Coordinador → Ruta → Agencia → Producto</h5>
                        <small class="text-muted">{{ $resumen['agencias'] }} agencias activas · mostrando {{ $filasVisibles->count() }} de {{ $totalFilasDetalle }} líneas, priorizadas por menor cumplimiento</small>
                    </div>
                    <input type="search" class="form-control form-control-sm" id="buscar-seguimiento" placeholder="Buscar en el detalle" style="max-width: 280px">
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-striped align-middle w-100" id="tabla-seguimiento">
                        <thead><tr><th>Empresa</th><th>Ciudad</th><th>Coordinador</th><th>Ruta</th><th>Agencia</th><th>Producto</th><th>Meta acum.</th><th>Venta</th><th>%</th><th>Brecha</th><th>Promedio</th><th>Proyección</th><th>Días ✓</th><th>Días ✕</th><th>Estado</th><th>Ver</th></tr></thead>
                        <tbody>
                        @foreach ($filasVisibles as $fila)
                            @php $color = $fila['estado'] === 'Cumple' ? 'success' : ($fila['estado'] === 'En seguimiento' ? 'warning' : 'danger'); @endphp
                            <tr>
                                <td>{{ $fila['empresa'] }}</td><td>{{ $fila['ciudad'] }}</td><td>{{ $fila['coordinador'] }}</td><td>{{ $fila['ruta'] }}</td>
                                <td><span class="fw-medium">{{ $fila['agencia'] }}</span><small class="d-block text-muted">{{ $fila['terminal'] }} · {{ $fila['sistema'] }}</small></td>
                                <td>{{ $fila['producto'] }}</td><td>RD$ {{ number_format($fila['meta_acumulada'], 2) }}</td><td>RD$ {{ number_format($fila['venta'], 2) }}</td>
                                <td>{{ number_format($fila['cumplimiento'], 1) }}%</td><td class="{{ $fila['brecha'] >= 0 ? 'text-success' : 'text-danger' }}">RD$ {{ number_format($fila['brecha'], 2) }}</td>
                                <td>RD$ {{ number_format($fila['promedio_diario'], 2) }}</td><td>RD$ {{ number_format($fila['proyeccion'], 2) }}</td><td>{{ $fila['dias_cumplidos'] }}</td><td>{{ $fila['dias_no_cumplidos'] }}</td>
                                <td><span class="badge bg-{{ $color }}-subtle text-{{ $color }}">{{ $fila['estado'] }}</span></td>
                                <td><button type="button" class="btn btn-sm btn-soft-primary btn-ver-detalle" data-terminal="{{ $fila['terminal'] }}" data-sistema="{{ strtolower($fila['sistema']) }}" data-agencia="{{ $fila['agencia'] }}"><i class="ri-eye-line me-1"></i>Ver</button></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<div class="modal fade" id="modalMetasSeguimiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="ri-settings-3-line me-2"></i>Configurar Meta Diaria por Producto</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                @foreach (['tradicional' => 'Tradicional', 'no_tradicional' => 'No Tradicional', 'recargas' => 'Recargas'] as $key => $label)
                    <div class="mb-3"><label class="form-label">{{ $label }} (RD$)</label><input type="number" min="0" step="0.01" class="form-control" id="input-meta-{{ str_replace('_', '-', $key) }}" value="{{ $metas[$key] }}"></div>
                @endforeach
            </div>
            <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="button" id="guardar-metas"><i class="ri-save-line me-1"></i>Guardar configuración</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetalleSeguimiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div><h5 class="modal-title mb-0">Detalle diario de ventas</h5><small class="text-muted" id="detalle-agencia-titulo"></small></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="detalle-seguimiento-cargando" class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="text-muted mt-3 mb-0">Consultando ventas de la agencia...</p></div>
                <div id="detalle-seguimiento-contenido" class="d-none">
                    <div class="d-flex flex-wrap gap-3 mb-3"><span><i class="ri-checkbox-blank-circle-fill text-success me-1"></i>Día cumplido</span><span><i class="ri-checkbox-blank-circle-fill text-danger me-1"></i>Día no cumplido o sin venta</span></div>
                    <div id="grafico-ventas-producto" style="min-height: 360px;"></div>
                    <div class="row g-3 mt-1" id="resumen-productos-detalle"></div>
                    <div class="mt-4" id="dias-productos-detalle"></div>
                </div>
                <div id="detalle-seguimiento-error" class="alert alert-danger d-none mb-0"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="{{ asset('libs/apexcharts/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const storageKey = 'comercial_kpi_meta_diaria_v1';
    ['modalMetasSeguimiento', 'modalDetalleSeguimiento'].forEach(function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal && modal.parentElement !== document.body) document.body.appendChild(modal);
    });

    const query = new URLSearchParams(window.location.search);
    const saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
    if (saved && query.has('consultar') && !query.has('meta_tradicional') && !query.has('meta_no_tradicional') && !query.has('meta_recargas')) {
        query.set('meta_tradicional', Number(saved.tradicional || 0));
        query.set('meta_no_tradicional', Number(saved.no_tradicional || 0));
        query.set('meta_recargas', Number(saved.recargas || 0));
        window.location.replace(window.location.pathname + '?' + query.toString());
        return;
    }

    document.getElementById('guardar-metas')?.addEventListener('click', function () {
        const metas = {
            tradicional: Number(document.getElementById('input-meta-tradicional').value || 0),
            no_tradicional: Number(document.getElementById('input-meta-no-tradicional').value || 0),
            recargas: Number(document.getElementById('input-meta-recargas').value || 0),
        };
        localStorage.setItem(storageKey, JSON.stringify(metas));
        document.getElementById('meta-tradicional').value = metas.tradicional;
        document.getElementById('meta-no-tradicional').value = metas.no_tradicional;
        document.getElementById('meta-recargas').value = metas.recargas;
        document.getElementById('form-seguimiento').submit();
    });

    document.getElementById('form-seguimiento')?.addEventListener('submit', function () {
        const button = document.getElementById('btn-generar-seguimiento');
        if (button) button.disabled = true;
        if (window.Swal) {
            Swal.fire({title: 'Generando informe', text: 'Consultando las ventas del mes seleccionado...', allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading()});
        }
    });

    let graficoDetalle = null;
    const modalDetalleElement = document.getElementById('modalDetalleSeguimiento');
    const modalDetalle = modalDetalleElement && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalDetalleElement) : null;

    function escapeHtml(value) {
        return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function formatCurrency(value) {
        return 'RD$ ' + Number(value || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    async function mostrarDetalle(button) {
        const loading = document.getElementById('detalle-seguimiento-cargando');
        const content = document.getElementById('detalle-seguimiento-contenido');
        const error = document.getElementById('detalle-seguimiento-error');
        document.getElementById('detalle-agencia-titulo').textContent = button.dataset.agencia + ' · Terminal ' + button.dataset.terminal;
        loading.classList.remove('d-none');
        content.classList.add('d-none');
        error.classList.add('d-none');
        modalDetalle?.show();

        const params = new URLSearchParams({
            mes: @json($mesSeleccionado),
            terminal: button.dataset.terminal,
            sistema: button.dataset.sistema,
            meta_tradicional: document.getElementById('meta-tradicional').value,
            meta_no_tradicional: document.getElementById('meta-no-tradicional').value,
            meta_recargas: document.getElementById('meta-recargas').value,
        });

        try {
            const response = await fetch(@json(route('gerencia.seguimiento-agencia.detalle')) + '?' + params.toString(), {headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error('No se pudo consultar el detalle diario.');
            const data = await response.json();

            if (graficoDetalle) graficoDetalle.destroy();
            graficoDetalle = new ApexCharts(document.querySelector('#grafico-ventas-producto'), {
                chart: {type: 'bar', height: 360, toolbar: {show: true}},
                series: data.productos.map(producto => ({name: producto.nombre, data: producto.ventas})),
                colors: ['#405189', '#299cdb', '#0ab39c'],
                plotOptions: {bar: {columnWidth: '70%', borderRadius: 2}},
                dataLabels: {enabled: false},
                xaxis: {categories: data.labels, title: {text: 'Día del mes'}},
                yaxis: {labels: {formatter: value => 'RD$ ' + Number(value).toLocaleString('en-US', {maximumFractionDigits: 0})}},
                tooltip: {y: {formatter: value => formatCurrency(value)}},
                legend: {position: 'top'},
                noData: {text: 'No hay ventas en el período'},
            });
            await graficoDetalle.render();

            document.getElementById('resumen-productos-detalle').innerHTML = data.productos.map(producto => `
                <div class="col-md-4"><div class="border rounded p-3 h-100">
                    <div class="fw-semibold">${escapeHtml(producto.nombre)}</div><small class="text-muted">Meta diaria: ${formatCurrency(producto.meta_diaria)}</small>
                    <div class="d-flex gap-3 mt-2"><span class="text-success fw-medium">${producto.dias_cumplidos} cumplidos</span><span class="text-danger fw-medium">${producto.dias_no_cumplidos} no cumplidos</span></div>
                </div></div>`).join('');

            document.getElementById('dias-productos-detalle').innerHTML = data.productos.map(producto => `
                <div class="mb-4"><h6>${escapeHtml(producto.nombre)}</h6><div class="d-flex flex-wrap gap-2">
                    ${producto.dias.map(dia => `<span class="badge border ${dia.cumple ? 'bg-success-subtle text-success border-success-subtle' : 'bg-danger-subtle text-danger border-danger-subtle'}" title="${formatCurrency(dia.venta)}">${escapeHtml(dia.etiqueta)} · ${formatCurrency(dia.venta)}</span>`).join('')}
                </div></div>`).join('');

            loading.classList.add('d-none');
            content.classList.remove('d-none');
        } catch (exception) {
            loading.classList.add('d-none');
            error.textContent = exception.message;
            error.classList.remove('d-none');
        }
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('.btn-ver-detalle');
        if (button) mostrarDetalle(button);
    });

    if (window.$ && $.fn.DataTable && document.getElementById('tabla-seguimiento')) {
        const table = $('#tabla-seguimiento').DataTable({pageLength: 25, order: [[8, 'asc']], dom: 'lrtip', language: {emptyTable: 'No hay agencias para los filtros seleccionados', info: 'Mostrando _START_ a _END_ de _TOTAL_', lengthMenu: 'Mostrar _MENU_', paginate: {next: 'Siguiente', previous: 'Anterior'}}});
        document.getElementById('buscar-seguimiento')?.addEventListener('input', function () { table.search(this.value).draw(); });
    }
});
</script>
@endsection
