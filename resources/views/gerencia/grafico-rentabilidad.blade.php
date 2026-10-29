@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">Gráfico de rentabilidad</h4>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <p class="text-muted">Ingreso real = ventas registradas − premios propios pagados. Los premios propios pagados incluyen los de esta terminal pagados por ella misma, por otras terminales y por otras empresas, según la fecha de pago. Se excluyen los premios ajenos pagados por esta terminal. Premios sacados se muestra por separado y no se resta otra vez. Los costos y gastos provienen de las cuentas activas de Clasificación de cuentas.</p>
                        <form method="GET" action="{{ route('gerencia.grafico-rentabilidad') }}" class="row g-3 align-items-end" id="formRentabilidadAgenciaGrafico">
                            <div class="col-md-2">
                                <label for="periodoRentabilidadGrafico" class="form-label">Período</label>
                                <select id="periodoRentabilidadGrafico" name="periodo" class="form-select">
                                    <option value="mes" @selected($periodo === 'mes')>Mes</option>
                                    <option value="rango" @selected($periodo === 'rango')>Rango de meses</option>
                                    <option value="anio" @selected($periodo === 'anio')>Año completo</option>
                                </select>
                            </div>
                            <div @class(['col-md-2', 'd-none' => $periodo !== 'mes']) data-periodo-grafico="mes">
                                <label for="mesRentabilidadGrafico" class="form-label">Mes</label>
                                <input id="mesRentabilidadGrafico" type="month" name="mes" class="form-control" value="{{ $mes }}" @disabled($periodo !== 'mes') @required($periodo === 'mes')>
                            </div>
                            <div @class(['col-md-2', 'd-none' => $periodo !== 'rango']) data-periodo-grafico="rango">
                                <label for="mesDesdeRentabilidadGrafico" class="form-label">Desde</label>
                                <input id="mesDesdeRentabilidadGrafico" type="month" name="mes_desde" class="form-control" value="{{ $mesDesde }}" @disabled($periodo !== 'rango') @required($periodo === 'rango')>
                            </div>
                            <div @class(['col-md-2', 'd-none' => $periodo !== 'rango']) data-periodo-grafico="rango">
                                <label for="mesHastaRentabilidadGrafico" class="form-label">Hasta</label>
                                <input id="mesHastaRentabilidadGrafico" type="month" name="mes_hasta" class="form-control" value="{{ $mesHasta }}" @disabled($periodo !== 'rango') @required($periodo === 'rango')>
                            </div>
                            <div @class(['col-md-2', 'd-none' => $periodo !== 'anio']) data-periodo-grafico="anio">
                                <label for="anioRentabilidadGrafico" class="form-label">Año</label>
                                <input id="anioRentabilidadGrafico" type="number" name="anio" class="form-control" min="1900" max="9999" value="{{ $anio }}" @disabled($periodo !== 'anio') @required($periodo === 'anio')>
                            </div>
                            <div class="col-md-2">
                                <label for="empresaRentabilidadGrafico" class="form-label">Empresa</label>
                                <select id="empresaRentabilidadGrafico" name="empresa" class="form-select">
                                    <option value="">Todas las empresas</option>
                                    @foreach ($empresas as $nombreEmpresa)
                                        <option value="{{ $nombreEmpresa }}" @selected($empresa === $nombreEmpresa)>{{ $nombreEmpresa }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="rutaRentabilidadGrafico" class="form-label">Ruta</label>
                                <select id="rutaRentabilidadGrafico" name="ruta" class="form-select">
                                    <option value="">Todas las rutas</option>
                                    @foreach ($rutas as $nombreRuta)
                                        <option value="{{ $nombreRuta }}" @selected($ruta === $nombreRuta)>{{ $nombreRuta }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="alcanceRentabilidadGrafico" class="form-label">Consultar</label>
                                <select id="alcanceRentabilidadGrafico" name="alcance" class="form-select">
                                    <option value="todas" @selected($alcance === 'todas')>Todas las terminales</option>
                                    <option value="terminal" @selected($alcance === 'terminal')>Una terminal</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="terminalRentabilidadGrafico" class="form-label">Número de terminal</label>
                                <input id="terminalRentabilidadGrafico" type="text" name="terminal" class="form-control" value="{{ $terminal }}" placeholder="Ej.: 052232">
                            </div>
                            <div class="col-md-auto d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Cargar datos</button>
                                <button type="button" id="filtrarRentabilidadGrafico" class="btn btn-success" @disabled(! $consultar)>Filtrar</button>
                            </div>
                            <div class="col-md-auto">
                                <div class="form-check mb-2">
                                    <input id="mostrarResumenRentabilidad" class="form-check-input" type="checkbox" name="mostrar_resumen" value="1" @checked($mostrarResumen) aria-controls="tarjetaResumenRentabilidad">
                                    <label class="form-check-label" for="mostrarResumenRentabilidad">Mostrar resumen</label>
                                </div>
                            </div>
                            <div class="col-12"><small id="estadoDatosRentabilidadGrafico" class="text-muted">Cargar datos obtiene el período completo. Filtrar reutiliza los datos cargados para empresa, ruta, terminal y productos.</small></div>
                            <div class="col-12">
                                <input type="hidden" name="productos_filtrados" value="1">
                                <span class="form-label d-block mb-2">Productos incluidos</span>
                                <div class="d-flex flex-wrap gap-3">
                                    @foreach (['Tradicional', 'No Tradicional', 'Recarga'] as $producto)
                                        <label class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" name="productos[]" value="{{ $producto }}" @checked($productosSeleccionados->contains($producto))>
                                            <span class="form-check-label">{{ $producto }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <small class="text-muted">Los premios de Tradicional y No Tradicional siguen la selección. Recarga no tiene premios asociados.</small>
                            </div>
                        </form>
                        <div class="d-flex flex-wrap align-items-center gap-3 mt-3" id="pagosRentabilidadGrafico">
                            @foreach ([60, 70, 80] as $pago)
                                <label class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" value="{{ $pago }}" @checked($pago === 60)>
                                    <span class="form-check-label">Pago {{ $pago }} %</span>
                                </label>
                            @endforeach
                            <small class="text-muted">Meta = gastos ÷ (1 − pago). Ventas por día = meta ÷ días de cada mes.</small>
                        </div>
                        @if ($errors->any())
                            <div class="alert alert-danger mt-3 mb-0">{{ $errors->first() }}</div>
                        @endif
                    </div>
                </div>

                @if ($consultar && $resumen !== null)
                    <div id="estadoCargaMesesRentabilidad" class="alert alert-info d-none" role="status" aria-live="polite">Cargando los datos del período por mes…</div>
                    <div id="sinTerminalesRentabilidad" @class(['alert alert-info', 'd-none' => $agenciasResumen->isNotEmpty() || $mesesPorCargar !== []])>No se encontraron terminales activas con los filtros seleccionados.</div>
                    <div id="tarjetaVentasRentabilidad" @class(['card mb-4', 'd-none' => $agenciasResumen->isEmpty()])>
                        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <h5 class="card-title mb-0">Ventas mensuales por producto</h5>
                            <span class="badge bg-primary-subtle text-primary">{{ $etiquetaPeriodo }}</span>
                        </div>
                        <div class="card-body border-bottom border-bottom-dashed bg-light-subtle">
                            <div class="row g-3 text-center">
                                <div class="col-6 col-md-3">
                                    <h5 class="mb-1" id="totalVentasProductosRentabilidad">RD$ {{ number_format(array_sum($ventasPorProducto), 2) }}</h5>
                                    <p class="text-muted mb-0">Total de ventas</p>
                                </div>
                                @foreach ($ventasPorProducto as $producto => $ventaProducto)
                                    <div class="col-6 col-md-3">
                                        <h5 class="mb-1" data-total-producto="{{ $producto }}">RD$ {{ number_format($ventaProducto, 2) }}</h5>
                                        <p class="text-muted mb-0">{{ $producto }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="card-body">
                            <p id="sinVentasRentabilidad" @class(['text-muted text-center', 'd-none' => array_sum($ventasPorProducto) !== 0.0])>No hay ventas para los productos y filtros seleccionados.</p>
                            <div id="graficoVentasPorProducto" aria-label="Gráfico de ventas por mes con barras de Tradicional, No Tradicional y Recarga" style="min-height: 374px"></div>
                        </div>
                    </div>
                    @foreach (['ventas_por_dia' => 'Ventas por día', 'meta_venta' => 'Meta de venta', 'premios_sacados' => 'Premios sacados', 'premios' => 'Premios propios pagados', 'ingreso_real' => 'Ingreso real', 'gastos' => 'Gastos', 'balance' => 'Balance'] as $campo => $titulo)
                        <div id="tarjetaGrafico-{{ $campo }}" data-tarjeta-metrica="{{ $campo }}" @class(['card mb-4', 'd-none' => $agenciasResumen->isEmpty()])>
                            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <h5 class="card-title mb-0">{{ $titulo }}</h5>
                                <span class="badge bg-primary-subtle text-primary">{{ $etiquetaPeriodo }}</span>
                            </div>
                            <div class="card-body border-bottom border-bottom-dashed bg-light-subtle">
                                <div id="totalesGrafico-{{ $campo }}" class="d-flex flex-wrap justify-content-center gap-4 text-center"></div>
                            </div>
                            <div class="card-body">
                                <p id="sinDatosGrafico-{{ $campo }}" class="text-muted text-center d-none">No hay datos para los filtros seleccionados.</p>
                                <div id="graficoMetrica-{{ $campo }}" data-grafico-metrica="{{ $campo }}" data-titulo="{{ $titulo }}" aria-label="{{ $titulo }} por mes" style="min-height: 374px"></div>
                            </div>
                        </div>
                    @endforeach
                    <div id="tarjetaResumenRentabilidad" @class(['card mb-4', 'd-none' => ! $mostrarResumen || $agenciasResumen->isEmpty()])>
                        <div class="card-header"><h5 class="mb-0" id="tituloResumenRentabilidad">Resumen de {{ $resumen['terminales'] }} terminales · {{ $empresa ?: 'Todas las empresas' }} · {{ $ruta ?: 'Todas las rutas' }} · {{ $etiquetaPeriodo }}</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                @foreach (['Ventas' => $resumen['venta'], 'Premios sacados' => $resumen['premios_sacados'], 'Premios propios pagados' => $resumen['premios'], 'Ingreso real' => $resumen['ingreso_real'], 'Gastos' => $resumen['gastos'], 'Balance' => $resumen['balance']] as $etiqueta => $importe)
                                    <div class="col-md-4 col-xl-2"><div class="border rounded p-3 h-100"><div class="text-muted small">{{ $etiqueta }}</div><div data-resumen-rentabilidad="{{ $etiqueta }}" class="fw-semibold {{ $importe < 0 ? 'text-danger' : '' }}">RD$ {{ number_format($importe, 2) }}</div></div></div>
                                @endforeach
                                <div class="col-md-4 col-xl-2"><div class="border rounded p-3 h-100"><div class="text-muted small">Terminales con balance positivo</div><div id="terminalesPositivasRentabilidad" class="fw-semibold text-success">{{ number_format($resumen['terminales_balance_positivo']) }}</div></div></div>
                                <div class="col-md-4 col-xl-2"><div class="border rounded p-3 h-100"><div class="text-muted small">Terminales con balance negativo</div><div id="terminalesNegativasRentabilidad" class="fw-semibold text-danger">{{ number_format($resumen['terminales_balance_negativo']) }}</div></div></div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="{{ asset('js/pages/grafico-rentabilidad-filtros.js') }}"></script>
    @if ($consultar)
        <script src="{{ asset('libs/apexcharts/apexcharts.min.js') }}"></script>
    @endif
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let datosCargados = {{ Illuminate\Support\Js::from($datosCargados) }};
            const mesesPorCargar = {{ Illuminate\Support\Js::from($mesesPorCargar) }};
            let cargandoMeses = mesesPorCargar.length > 0;
            const periodo = document.getElementById('periodoRentabilidadGrafico');
            const actualizarPeriodo = () => {
                document.querySelectorAll('[data-periodo-grafico]').forEach((grupo) => {
                    const activo = grupo.dataset.periodoGrafico === periodo.value;
                    grupo.classList.toggle('d-none', !activo);
                    grupo.querySelectorAll('input').forEach((campo) => {
                        campo.disabled = !activo;
                        campo.required = activo;
                    });
                });
            };
            periodo.addEventListener('change', actualizarPeriodo);
            actualizarPeriodo();
            const empresa = document.getElementById('empresaRentabilidadGrafico');
            const ruta = document.getElementById('rutaRentabilidadGrafico');
            const alcance = document.getElementById('alcanceRentabilidadGrafico');
            const terminal = document.getElementById('terminalRentabilidadGrafico');
            const rutasDisponibles = {{ Illuminate\Support\Js::from($rutasDisponibles->toArray()) }};
            const actualizarTerminal = () => {
                terminal.disabled = alcance.value === 'todas';
                terminal.required = alcance.value === 'terminal';
            };

            alcance.addEventListener('change', actualizarTerminal);
            actualizarTerminal();

            const mostrarResumen = document.getElementById('mostrarResumenRentabilidad');
            const tarjetaResumen = document.getElementById('tarjetaResumenRentabilidad');
            let hayTerminalesResumen = {{ Illuminate\Support\Js::from($agenciasResumen->isNotEmpty()) }};
            const actualizarVisibilidadResumen = () => {
                if (tarjetaResumen) {
                    tarjetaResumen.classList.toggle('d-none', !mostrarResumen.checked || !hayTerminalesResumen);
                }
            };
            mostrarResumen.addEventListener('change', actualizarVisibilidadResumen);
            actualizarVisibilidadResumen();

            const contenedorVentas = document.getElementById('graficoVentasPorProducto');
            const moneda = (importe) => 'RD$ ' + Number(importe).toLocaleString('es-DO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const crearGrafico = (contenedor, ventasMensuales, titulo) => {
                const estilos = getComputedStyle(document.documentElement);
                const colores = ['--vz-primary', '--vz-success', '--vz-warning'].map((nombre, indice) => estilos.getPropertyValue(nombre).trim() || ['#405189', '#0ab39c', '#ffbe0b'][indice]);
                const rangosColor = contenedor.dataset.graficoMetrica === 'balance'
                    ? [{ from: -Number.MAX_VALUE, to: -Number.MIN_VALUE, color: estilos.getPropertyValue('--vz-danger').trim() || '#f06548' }]
                    : [];

                const grafico = new ApexCharts(contenedor, {
                    chart: { height: 374, type: 'bar', toolbar: { show: false } },
                    series: ventasMensuales.series,
                    colors: colores,
                    plotOptions: { bar: { horizontal: false, distributed: false, columnWidth: '45%', borderRadius: 3, colors: { ranges: rangosColor } } },
                    dataLabels: { enabled: false },
                    stroke: { width: 0 },
                    fill: { opacity: 1 },
                    xaxis: { categories: ventasMensuales.etiquetas, axisTicks: { show: false }, axisBorder: { show: false } },
                    yaxis: { labels: { formatter: moneda }, title: { text: titulo + ' (RD$)' } },
                    grid: { show: true, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } }, padding: { top: 0, right: 10, bottom: 15, left: 10 } },
                    legend: { show: true, position: 'bottom', horizontalAlign: 'center', markers: { radius: 6 } },
                    tooltip: { shared: true, intersect: false, y: { formatter: moneda } },
                    responsive: [{ breakpoint: 576, options: { plotOptions: { bar: { columnWidth: '65%' } }, yaxis: { labels: { formatter: (importe) => 'RD$ ' + Number(importe).toLocaleString('es-DO', { notation: 'compact' }) } } } }],
                });
                grafico.render();
                return grafico;
            };
            let graficoVentas = null;
            if (contenedorVentas && typeof ApexCharts !== 'undefined') {
                graficoVentas = crearGrafico(contenedorVentas, {{ Illuminate\Support\Js::from($ventasMensuales) }}, 'Ventas');
            }

            const graficosMetricas = new Map();
            const obtenerFiltros = () => ({
                empresa: empresa.value, ruta: ruta.value, alcance: alcance.value, terminal: terminal.value,
                productos: [...document.querySelectorAll('#formRentabilidadAgenciaGrafico input[name="productos[]"]:checked')].map((campo) => campo.value),
                pagos: [...document.querySelectorAll('#pagosRentabilidadGrafico input:checked')].map((campo) => Number(campo.value)),
            });
            let datosGraficos = datosCargados ? FiltrosGraficoRentabilidad.filtrar(datosCargados, obtenerFiltros()) : null;
            const actualizarGraficosMetricas = () => {
                if (!datosGraficos) return;
                document.querySelectorAll('[data-grafico-metrica]').forEach((contenedor) => {
                    const campo = contenedor.dataset.graficoMetrica;
                    const series = datosGraficos.graficos[campo];
                    const sinTerminales = datosGraficos.resumen.terminales === 0;
                    document.getElementById('tarjetaGrafico-' + campo).classList.toggle('d-none', sinTerminales);
                    const sinDatos = !series.some((serie) => serie.data.some((importe) => importe !== 0));
                    const aviso = document.getElementById('sinDatosGrafico-' + campo);
                    aviso.classList.toggle('d-none', !sinDatos);
                    aviso.textContent = series.length === 0 ? 'Selecciona al menos un porcentaje de pago.' : 'No hay datos para los filtros seleccionados.';
                    const totales = document.getElementById('totalesGrafico-' + campo);
                    totales.replaceChildren();
                    series.forEach((serie) => {
                        let total = serie.data.reduce((suma, importe) => suma + importe, 0);
                        if (campo === 'ventas_por_dia') {
                            const dias = datosCargados.meses.map((mes) => {
                                const [anio, numeroMes] = mes.split('-').map(Number);
                                return new Date(Date.UTC(anio, numeroMes, 0)).getUTCDate();
                            });
                            total = serie.data.reduce((suma, importe, indice) => suma + importe * dias[indice], 0) / dias.reduce((suma, cantidad) => suma + cantidad, 0);
                        }
                        const bloque = document.createElement('div');
                        const importe = document.createElement('h5');
                        importe.className = 'mb-1' + (campo === 'balance' && total < 0 ? ' text-danger' : '');
                        importe.textContent = moneda(total);
                        const etiqueta = document.createElement('p');
                        etiqueta.className = 'text-muted mb-0';
                        etiqueta.textContent = campo === 'ventas_por_dia' ? serie.name + ' · Promedio diario del período' : serie.name + ' · Total del período';
                        bloque.append(importe, etiqueta);
                        totales.append(bloque);
                    });
                    if (graficosMetricas.has(campo)) {
                        graficosMetricas.get(campo).updateOptions({ series, xaxis: { categories: datosCargados.etiquetas } });
                    } else if (typeof ApexCharts !== 'undefined') {
                        graficosMetricas.set(campo, crearGrafico(contenedor, { series, etiquetas: datosCargados.etiquetas }, contenedor.dataset.titulo));
                    }
                });
            };
            actualizarGraficosMetricas();
            document.querySelectorAll('#pagosRentabilidadGrafico input').forEach((campo) => {
                campo.addEventListener('change', () => {
                    if (datosGraficos) {
                        datosGraficos = FiltrosGraficoRentabilidad.filtrar(datosCargados, { ...filtrosAplicados, pagos: obtenerFiltros().pagos });
                        actualizarGraficosMetricas();
                    }
                });
            });
            let filtrosAplicados = obtenerFiltros();

            empresa.addEventListener('change', function () {
                const seleccionada = ruta.value;
                const nombres = [...new Set(rutasDisponibles
                    .filter((fila) => empresa.value === '' || fila.empresa === empresa.value)
                    .map((fila) => fila.ruta))];

                ruta.replaceChildren(new Option('Todas las rutas', ''));
                nombres.forEach((nombre) => ruta.add(new Option(nombre, nombre)));
                ruta.value = nombres.includes(seleccionada) ? seleccionada : '';
            });

            const botonFiltrar = document.getElementById('filtrarRentabilidadGrafico');
            const estadoDatos = document.getElementById('estadoDatosRentabilidadGrafico');
            const clavePeriodo = () => {
                if (periodo.value === 'anio') return 'anio:' + document.getElementById('anioRentabilidadGrafico').value;
                if (periodo.value === 'rango') return 'rango:' + document.getElementById('mesDesdeRentabilidadGrafico').value + ':' + document.getElementById('mesHastaRentabilidadGrafico').value;
                return 'mes:' + document.getElementById('mesRentabilidadGrafico').value;
            };
            const periodoCargado = clavePeriodo();
            const etiquetaPeriodoCargado = {{ Illuminate\Support\Js::from($etiquetaPeriodo) }};
            const actualizarEstadoDatos = () => {
                const cambioPeriodo = clavePeriodo() !== periodoCargado;
                botonFiltrar.disabled = cargandoMeses || !datosCargados || cambioPeriodo;
                estadoDatos.textContent = cargandoMeses
                    ? 'Cargando los datos del período. Los filtros estarán disponibles al terminar.'
                    : !datosCargados
                    ? 'Pulsa Cargar datos para obtener el período. Después puedes usar Filtrar sin volver a consultar.'
                    : cambioPeriodo
                        ? 'El período cambió. Pulsa Cargar datos para obtener las nuevas fechas.'
                        : 'Datos cargados: ' + etiquetaPeriodoCargado + '. Usa Filtrar para cambiar empresa, ruta, terminal o productos sin volver a cargar.';
            };
            ['periodoRentabilidadGrafico', 'mesRentabilidadGrafico', 'mesDesdeRentabilidadGrafico', 'mesHastaRentabilidadGrafico', 'anioRentabilidadGrafico'].forEach((id) => {
                document.getElementById(id).addEventListener('change', actualizarEstadoDatos);
                document.getElementById(id).addEventListener('input', actualizarEstadoDatos);
            });
            actualizarEstadoDatos();

            const aplicarFiltros = () => {
                if (!datosCargados || clavePeriodo() !== periodoCargado || !document.getElementById('formRentabilidadAgenciaGrafico').reportValidity()) return;
                const filtros = obtenerFiltros();
                const datos = FiltrosGraficoRentabilidad.filtrar(datosCargados, filtros);
                filtrosAplicados = filtros;
                datosGraficos = datos;
                actualizarGraficosMetricas();
                const sinTerminales = datos.resumen.terminales === 0;
                document.getElementById('sinTerminalesRentabilidad').classList.toggle('d-none', !sinTerminales);
                document.getElementById('tarjetaVentasRentabilidad').classList.toggle('d-none', sinTerminales);
                hayTerminalesResumen = !sinTerminales;
                actualizarVisibilidadResumen();
                const totalVentas = Object.values(datos.ventasPorProducto).reduce((suma, venta) => suma + venta, 0);
                document.getElementById('totalVentasProductosRentabilidad').textContent = moneda(totalVentas);
                document.getElementById('sinVentasRentabilidad').classList.toggle('d-none', totalVentas !== 0);
                document.querySelectorAll('[data-total-producto]').forEach((elemento) => {
                    elemento.textContent = moneda(datos.ventasPorProducto[elemento.dataset.totalProducto]);
                });
                const camposResumen = {
                    'Ventas': 'venta', 'Premios sacados': 'premios_sacados', 'Premios propios pagados': 'premios',
                    'Ingreso real': 'ingreso_real', 'Gastos': 'gastos', 'Balance': 'balance',
                };
                document.querySelectorAll('[data-resumen-rentabilidad]').forEach((elemento) => {
                    const importe = datos.resumen[camposResumen[elemento.dataset.resumenRentabilidad]];
                    elemento.textContent = moneda(importe);
                    elemento.classList.toggle('text-danger', importe < 0);
                });
                document.getElementById('terminalesPositivasRentabilidad').textContent = datos.resumen.terminales_balance_positivo.toLocaleString('es-DO');
                document.getElementById('terminalesNegativasRentabilidad').textContent = datos.resumen.terminales_balance_negativo.toLocaleString('es-DO');
                document.getElementById('tituloResumenRentabilidad').textContent = `Resumen de ${datos.resumen.terminales} terminales · ${empresa.value || 'Todas las empresas'} · ${ruta.value || 'Todas las rutas'} · ${etiquetaPeriodoCargado}`;
                if (graficoVentas) {
                    graficoVentas.updateOptions({ series: datos.ventasMensuales.series, xaxis: { categories: datos.ventasMensuales.etiquetas } });
                }
            };
            botonFiltrar.addEventListener('click', aplicarFiltros);

            if (cargandoMeses) {
                const estadoCarga = document.getElementById('estadoCargaMesesRentabilidad');
                if (window.Swal) {
                    Swal.fire({
                        title: 'Consultando rentabilidad',
                        text: 'Cargando los datos del período por mes…',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        showCloseButton: false,
                        didOpen: () => Swal.showLoading(),
                    });
                } else {
                    estadoCarga.classList.toggle('d-none', false);
                }
                FiltrosGraficoRentabilidad.cargarMeses(mesesPorCargar, async (mes) => {
                    const url = new URL({{ Illuminate\Support\Js::from(route('gerencia.grafico-rentabilidad.datos')) }});
                    url.searchParams.set('periodo', 'mes');
                    url.searchParams.set('mes', mes);
                    const respuesta = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    if (!respuesta.ok) {
                        throw new Error('No se pudieron cargar los datos de ' + mes + '. Pulsa Cargar datos para reintentar.');
                    }
                    return respuesta.json();
                }, (completados, total, mes) => {
                    estadoCarga.textContent = 'Cargando ' + mes + ' · ' + completados + ' de ' + total + ' meses completados.';
                    if (window.Swal) {
                        Swal.update({ text: estadoCarga.textContent });
                    }
                }).then((datos) => {
                    datosCargados = datos;
                    cargandoMeses = false;
                    estadoCarga.classList.toggle('d-none', true);
                    actualizarEstadoDatos();
                    if (clavePeriodo() === periodoCargado) {
                        aplicarFiltros();
                    }
                    if (window.Swal) {
                        Swal.close();
                    }
                }).catch((error) => {
                    cargandoMeses = false;
                    const mensaje = error.message || 'No se pudo completar la carga. Pulsa Cargar datos para reintentar.';
                    if (window.Swal) {
                        Swal.fire({ title: 'No se pudo cargar el reporte', text: mensaje, icon: 'error', confirmButtonText: 'Entendido' });
                    } else {
                        estadoCarga.classList.toggle('d-none', false);
                        estadoCarga.classList.toggle('alert-info', false);
                        estadoCarga.classList.toggle('alert-danger', true);
                        estadoCarga.textContent = mensaje;
                    }
                    actualizarEstadoDatos();
                });
            }

            document.getElementById('formRentabilidadAgenciaGrafico').addEventListener('submit', function () {
                if (window.Swal) {
                    Swal.fire({
                        title: 'Consultando rentabilidad',
                        text: 'Estamos agrupando los datos con los filtros seleccionados.',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => Swal.showLoading(),
                    });
                }
            });
        });
    </script>
@endsection
