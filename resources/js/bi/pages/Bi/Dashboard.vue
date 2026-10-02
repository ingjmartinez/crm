<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { currencyInputCaret, formatCurrencyInput, normalizeCurrencyInput } from '../../currency-input.js';

const props = defineProps({
    modulo: { type: String, required: true },
    menuUrl: { type: String, required: true },
    biUrl: { type: String, required: true },
    recalcularUrl: { type: String, required: true },
    mensajeRecalculo: { type: String, default: null },
    terminales: { type: Object, required: true },
    busquedaTerminal: { type: String, default: '' },
    catalogoActualizado: { type: String, default: null },
    periodoVentas: { type: Object, required: true },
    ventasPorCategoria: { type: Object, required: true },
    ventasPremios: { type: Object, required: true },
    indicadores: { type: Object, required: true },
    fechaLecturas: { type: String, required: true },
    lecturasPorHora: { type: Array, required: true },
    rutasConMasVenta: { type: Array, required: true },
    promediosHistoricos: { type: Object, required: true },
    quinielaLotekaHoy: { type: Number, default: null },
    quinielaLotekaSemanaAnterior: { type: Number, default: null },
    quinielaLotekaRecordAnterior: { type: Number, default: null },
    megaChanceHoy: { type: Number, default: null },
    megaChanceSemanaAnterior: { type: Number, default: null },
    megaChanceRecordAnterior: { type: Number, default: null },
    limitesProductosUrl: { type: String, required: true },
    mensajeLimite: { type: String, default: null },
    productosDisponibles: { type: Array, default: () => [] },
    gruposDisponibles: { type: Array, default: () => [] },
    alertasProductos: { type: Array, default: () => [] },
    terminalesLimites: { type: Array, default: () => [] },
});

const tabs = [
    { key: 'dashboard', name: 'Dashboard', color: 'blue' },
    { key: 'productos', name: 'Record de Productos', color: 'blue' },
    { key: 'entrada', name: 'Control de Entrada', color: 'gold' },
    { key: 'limites', name: 'Límite de Ventas', color: 'red' },
    { key: 'metricas', name: 'Métricas', color: 'violet' },
];
const periods = ['5 días', '15 días', 'Último mes', '30 días', '60 días', '90 días', 'Todo'];
const activeTab = ref('dashboard');
const activePeriod = ref('5 días');
const activeView = ref('Monto');
const activeSalesView = ref('Monto');
const activeEntry = ref('Detalle diario');
const limiteForm = useForm({ producto_id: '', alcance: 'global', terminal: '', monto: '', activo: true });
const filtroAlcance = ref('todos');
const nombresTerminales = computed(() => Object.fromEntries(props.terminalesLimites.map((row) => [row.terminal, row.nombre])));
const soloAlertas = ref(false);
const limiteGuardado = ref(false);
const cantidadAlertas = computed(() => props.alertasProductos.filter((row) => row.alerta).length);
const productosVigilados = computed(() => props.alertasProductos.filter((row) => row.activo).length);
const filteredLimits = computed(() => props.alertasProductos.filter((row) =>
    `${row.producto_id} ${row.nombre} ${row.terminal} ${nombresTerminales.value[row.terminal] || ''}`.toLowerCase().includes(search.value.toLowerCase())
    && (!soloAlertas.value || row.alerta) && (filtroAlcance.value === 'todos' || row.alcance === filtroAlcance.value)));

function editarLimite(row) {
    limiteForm.clearErrors();
    limiteGuardado.value = false;
    limiteForm.producto_id = row.producto_id;
    limiteForm.alcance = row.alcance;
    limiteForm.terminal = row.terminal;
    limiteForm.monto = row.monto;
    limiteForm.activo = row.activo;
    document.getElementById('limite-monto')?.focus();
}

function seleccionarProductoLimite() {
    const terminal = limiteForm.alcance === 'global' ? '' : limiteForm.terminal;
    const row = props.alertasProductos.find((item) => item.producto_id === limiteForm.producto_id && item.terminal === terminal);
    limiteForm.clearErrors();
    limiteGuardado.value = false;
    limiteForm.monto = row?.monto ?? '';
    limiteForm.activo = row?.activo ?? true;
}

function cambiarAlcanceLimite() {
    limiteForm.terminal = '';
    seleccionarProductoLimite();
}

async function actualizarMontoLimite(event) {
    const input = event.target;
    const caret = currencyInputCaret(input.value, input.selectionStart ?? input.value.length);
    limiteForm.monto = normalizeCurrencyInput(input.value);
    input.value = formatCurrencyInput(limiteForm.monto);
    await nextTick();
    input.setSelectionRange(caret, caret);
}

function completarMontoLimite(event) {
    const formatted = formatCurrencyInput(limiteForm.monto, true);
    limiteForm.monto = normalizeCurrencyInput(formatted);
    event.target.value = formatted;
}

function borrarSeparadorMonto(event) {
    const input = event.target;
    const caret = input.selectionStart;
    if (caret !== input.selectionEnd) return;
    if (event.key === 'Backspace' && input.value[caret - 1] === ',') {
        event.preventDefault();
        input.value = input.value.slice(0, caret - 2) + input.value.slice(caret);
        input.setSelectionRange(caret - 2, caret - 2);
        actualizarMontoLimite(event);
    } else if (event.key === 'Delete' && input.value[caret] === ',') {
        event.preventDefault();
        input.value = input.value.slice(0, caret) + input.value.slice(caret + 2);
        input.setSelectionRange(caret, caret);
        actualizarMontoLimite(event);
    }
}

function guardarLimite() {
    limiteGuardado.value = false;
    limiteForm.post(props.limitesProductosUrl, {
        preserveScroll: true,
        onSuccess: () => { limiteForm.reset(); limiteGuardado.value = true; },
    });
}
const zone = ref('');
const search = ref('');
const catalogSearch = ref(props.busquedaTerminal);
const toDate = (date) => [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
const referenceDate = () => new Date(`${props.periodoVentas.ultimaFecha || props.periodoVentas.hasta}T12:00:00`);
const from = ref(props.periodoVentas.desde);
const until = ref(props.periodoVentas.hasta);
const periodError = ref('');
const recalculando = ref(false);
const recalculoError = ref('');
const comparativoAbierto = ref(false);
const abrirComparativoButton = ref(null);
const cerrarComparativoButton = ref(null);
let hourlyRefreshTimer;

async function abrirComparativo() {
    comparativoAbierto.value = true;
    await nextTick();
    cerrarComparativoButton.value?.focus();
}

function cerrarComparativo() {
    comparativoAbierto.value = false;
    abrirComparativoButton.value?.focus();
}

function handleModalKeydown(event) {
    if (event.key === 'Escape') {
        cerrarComparativo();
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleModalKeydown);
    hourlyRefreshTimer = window.setInterval(() => {
        if (!document.hidden) {
            router.reload({ only: ['lecturasPorHora', 'rutasConMasVenta', 'indicadores', 'quinielaLotekaHoy', 'megaChanceHoy', 'alertasProductos'], preserveScroll: true });
        }
    }, 120_000);
});

onUnmounted(() => {
    window.clearInterval(hourlyRefreshTimer);
    window.removeEventListener('keydown', handleModalKeydown);
});
const formatDop = (amount) => `DOP ${new Intl.NumberFormat('es-DO', { maximumFractionDigits: 0 }).format(amount)}`;
const formatLimiteDop = (amount) => `DOP ${new Intl.NumberFormat('es-DO', { maximumFractionDigits: 2 }).format(amount)}`;
const formatNumber = (amount) => new Intl.NumberFormat('es-DO', { maximumFractionDigits: 0 }).format(amount);
const formatCompactDop = (amount) => amount === null ? '—' : Math.abs(amount) >= 1_000_000 ? `DOP ${(amount / 1_000_000).toFixed(2)}M` : formatDop(amount);
const shortDate = (date) => date ? date.slice(5) : '—';
const percentChange = (current, previous) => current === null || previous === null || previous === 0 ? null : ((current / previous) - 1) * 100;
const salesComparisons = computed(() => {
    const data = props.indicadores.comparativoDia;
    return [
        { label: `VS ${shortDate(data.ayer.fecha)}`, amount: data.ayer.ventas, change: percentChange(data.hoy.ventas, data.ayer.ventas) },
        { label: `MES ANT ${shortDate(data.mesAnterior.fecha)}`, amount: data.mesAnterior.ventas, change: percentChange(data.hoy.ventas, data.mesAnterior.ventas) },
    ];
});
const comparisonRows = [
    { label: 'Ventas', key: 'ventas', color: 'blue', format: formatCompactDop },
    { label: 'Premios', key: 'premios', color: 'red', format: formatCompactDop },
    { label: 'Resultado', key: 'resultado', color: 'green', format: formatCompactDop },
    { label: 'Tasa de premiación', key: 'tasa', color: '', format: (value) => value === null ? '—' : `${value.toFixed(1)}%` },
    { label: 'Agencias activas', key: 'agenciasActivas', color: '', format: (value) => value === null ? '—' : formatNumber(value) },
];
const includesToday = computed(() => props.periodoVentas.desde <= props.fechaLecturas && props.periodoVentas.hasta >= props.fechaLecturas);
const hourlyLatest = computed(() => props.lecturasPorHora.at(-1) || null);
const recordsProductos = computed(() => [
    { name: 'Quiniela Loteka', hoy: props.quinielaLotekaHoy, semanaAnterior: props.quinielaLotekaSemanaAnterior, recordAnterior: props.quinielaLotekaRecordAnterior },
    { name: 'Mega Chance', hoy: props.megaChanceHoy, semanaAnterior: props.megaChanceSemanaAnterior, recordAnterior: props.megaChanceRecordAnterior },
].map(({ name, hoy, semanaAnterior, recordAnterior }) => ({
        name,
        hoy,
        semanaAnterior,
        record: recordAnterior === null ? hoy : hoy === null ? recordAnterior : Math.max(hoy, recordAnterior),
        diferencia: hoy !== null && semanaAnterior > 0 ? ((hoy / semanaAnterior) - 1) * 100 : null,
        alcanzado: hoy !== null && recordAnterior !== null && recordAnterior > 0 ? (hoy / recordAnterior) * 100 : null,
        nuevoRecord: hoy !== null && recordAnterior !== null && recordAnterior > 0 && hoy >= recordAnterior,
    })));
const hourlyCards = computed(() => [
    { name: 'VENTAS ACUMULADAS', value: hourlyLatest.value ? formatDop(hourlyLatest.value.total) : '—', detail: 'Todas las categorías disponibles' },
    { name: 'PROMEDIO POR HORA', value: hourlyLatest.value ? formatDop(hourlyLatest.value.total / (Number(hourlyLatest.value.hora) - 6 + 1)) : '—', detail: hourlyLatest.value ? `Desde las 06:00 · ${Number(hourlyLatest.value.hora) - 6 + 1} horas` : 'Esperando captura' },
]);
const promedioHistoricoVentas = computed(() => {
    const tradicional = props.promediosHistoricos.tradicional?.monto;
    const noTradicional = props.promediosHistoricos.no_tradicional?.monto;
    if (!hourlyLatest.value || tradicional == null || noTradicional == null) return null;

    const promedio = Number(tradicional) + Number(noTradicional);
    const ventaHoy = Number(hourlyLatest.value.tradicional) + Number(hourlyLatest.value.noTradicional);
    return promedio > 0 ? { promedio, porcentaje: (ventaHoy / promedio) * 100 } : null;
});
const ventasPorTipoHoy = computed(() => {
    const lectura = hourlyLatest.value;
    if (!lectura) return [];

    return [
        { key: 'tradicional', title: 'VENTAS TRADICIONALES', amount: lectura.tradicional, count: props.indicadores.coberturaCategorias.tradicional, color: 'blue' },
        { key: 'no_tradicional', title: 'VENTAS NO TRADICIONALES', amount: lectura.noTradicional, count: props.indicadores.coberturaCategorias.no_tradicional, color: 'pink' },
    ].map((tipo) => {
        const average = props.promediosHistoricos[tipo.key]?.monto ?? null;
        const progress = average > 0 ? (tipo.amount / average) * 100 : null;
        return {
            ...tipo,
            share: lectura.total > 0 ? (tipo.amount / lectura.total) * 100 : 0,
            average,
            progress,
            remaining: average === null ? null : Math.max(0, average - tipo.amount),
            perAgency: tipo.count > 0 ? tipo.amount / tipo.count : 0,
        };
    });
});
const asideCards = computed(() => {
    const data = props.indicadores;
    const trend = data.tendencia7d;

    return [
        {
            name: 'TERMINALES CON VENTA',
            value: data.terminalesConVenta === null ? '—' : `${formatNumber(data.terminalesConVenta)} / ${formatNumber(data.terminalesEvaluadas)}`,
            detail: 'Con venta de loterías / activas LotoBet',
            color: 'green',
        },
        {
            name: 'TENDENCIA 7D',
            value: trend === null ? 'Sin comparativo' : trend > 0 ? '▲ Subiendo' : trend < 0 ? '▼ Bajando' : '● Estable',
            detail: trend === null ? `${data.diasTendenciaActual}/7 y ${data.diasTendenciaAnterior}/7 días con ventas` : `${Math.abs(trend).toFixed(1)}% vs. 7 días anteriores`,
            color: trend === null ? '' : trend > 0 ? 'green' : trend < 0 ? 'red' : 'gold',
        },
        {
            name: 'PROM. VENTAS DIARIAS',
            value: data.promedioVentasDiarias === null ? '—' : formatDop(data.promedioVentasDiarias),
            detail: `${data.diasPromedioConDatos} de ${data.diasPromedioEsperados} días cerrados con ventas`,
            color: 'violet',
        },
    ];
});
const coverageCards = computed(() => [
    { name: 'Tradicional', key: 'tradicional', color: 'blue' },
    { name: 'No Tradicional', key: 'no_tradicional', color: 'pink' },
    { name: 'Externas', key: 'externas', color: 'gold' },
    { name: 'Recargas', key: 'recargas', color: 'violet' },
].map((category) => {
    const count = props.indicadores.coberturaCategorias[category.key];
    const evaluated = props.indicadores.terminalesEvaluadas;

    return {
        ...category,
        value: count === null || !evaluated ? '—' : `${((count / evaluated) * 100).toFixed(1)}%`,
        count: count === null ? 'Sin dato' : formatNumber(count),
    };
}));
const hourlyChart = computed(() => {
    const values = props.lecturasPorHora.map((reading) => ({
        hora: Number(reading.hora),
        total: Number(reading.total),
    }));
    const highest = Math.max(1, ...values.map((reading) => reading.total));
    const magnitude = 10 ** Math.floor(Math.log10(highest / 4));
    const step = [1, 2, 2.5, 5, 10].map((factor) => factor * magnitude).find((candidate) => candidate * 4 >= highest);
    const maximum = step * 4;
    const formatAxis = (amount) => amount >= 1_000_000
        ? `DOP ${(amount / 1_000_000).toFixed(1).replace('.0', '')}M`
        : amount >= 1_000 ? `DOP ${Math.round(amount / 1_000)}K` : formatDop(amount);
    const ticks = Array.from({ length: 5 }, (_, index) => ({ y: 180 - index * 40, label: formatAxis(index * step) }));
    const hours = Array.from({ length: 17 }, (_, index) => ({ hour: index + 6, x: 72 + index * 34.5 }));
    const points = values.map((reading) => ({
        ...reading,
        x: 72 + (reading.hora - 6) * 34.5,
        y: 180 - (reading.total / maximum) * 160,
    }));
    const line = points.map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`).join(' ');
    const area = points.length > 1 ? `${line} L ${points.at(-1).x} 180 L ${points[0].x} 180 Z` : '';

    return { ticks, hours, points, line, area };
});
const categories = computed(() => [
    { key: 'tradicional', name: 'Tradicional', amount: props.ventasPorCategoria.tradicional, color: 'blue' },
    { key: 'no_tradicional', name: 'No Tradicional', amount: props.ventasPorCategoria.no_tradicional, color: 'pink' },
    { key: 'externas', name: 'Externas', amount: props.ventasPorCategoria.externas, color: 'gold' },
    { key: 'recargas', name: 'Recargas', amount: props.ventasPorCategoria.recargas, color: 'violet' },
].map((category) => ({
    ...category,
    value: activeView.value === 'Participación %'
        ? `${((category.amount / (props.ventasPorCategoria.total || 1)) * 100).toFixed(1)}%`
        : formatDop(category.amount),
    detail: activeView.value === 'Participación %'
        ? formatDop(category.amount)
        : `${((category.amount / (props.ventasPorCategoria.total || 1)) * 100).toFixed(1)}% del total`,
    percentage: (category.amount / (props.ventasPorCategoria.total || 1)) * 100,
})));
const metrics = computed(() => [
    { key: 'ventas', name: 'Ventas', value: formatDop(props.ventasPremios.ventas), change: props.ventasPremios.desde ? `${props.ventasPremios.desde} — ${props.ventasPremios.hasta}` : 'Sin días cerrados', color: 'blue' },
    { key: 'premios', name: 'Premios', value: formatDop(props.ventasPremios.premios), change: 'Premios pagados en el período', color: 'red' },
    { key: 'utilidad', name: 'Utilidad', value: formatDop(props.ventasPremios.utilidad), change: 'Ventas − premios', color: 'green' },
    { key: 'tasaPremiacion', name: 'Tasa de premiación', value: `${Number(props.ventasPremios.tasaPremiacion).toFixed(1)}%`, change: 'Premios / ventas', color: 'gold' },
]);
function metricSparkline(key, serie) {
    if (!serie.length) {
        return null;
    }

    const groupSize = Math.max(1, Math.ceil(serie.length / 40));
    const values = [];
    for (let index = 0; index < serie.length; index += groupSize) {
        const group = serie.slice(index, index + groupSize);
        const sales = group.reduce((sum, day) => sum + Number(day.ventas), 0);
        const prizes = group.reduce((sum, day) => sum + Number(day.premios), 0);
        const value = key === 'tasaPremiacion'
            ? (sales > 0 ? (prizes / sales) * 100 : 0)
            : group.reduce((sum, day) => sum + Number(day[key]), 0) / group.length;
        values.push({ value, firstDate: group[0].fecha, lastDate: group.at(-1).fecha });
    }

    const minimum = Math.min(...values.map((point) => point.value));
    const maximum = Math.max(...values.map((point) => point.value));
    const spread = maximum - minimum;
    const points = values.map((point, index) => ({
        ...point,
        x: values.length === 1 ? 140 : 4 + (index / (values.length - 1)) * 272,
        y: spread === 0 ? 23 : 40 - ((point.value - minimum) / spread) * 34,
    }));
    const line = points.map((point) => `${point.x},${point.y}`).join(' ');
    const area = `4,44 ${line} 276,44`;

    return { line, area, points, last: points.at(-1) };
}
const metricSparklines = computed(() => Object.fromEntries(metrics.value.map((metric) => [metric.key, metricSparkline(metric.key, props.ventasPremios.serie)])));
const categorySparklines = computed(() => Object.fromEntries(categories.value.map((category) => [category.key, metricSparkline(category.key, props.ventasPorCategoria.serie)])));
const categoryChart = computed(() => {
    const serie = props.ventasPorCategoria.serie;
    const groupSize = Math.max(1, Math.ceil(serie.length / 30));
    const rows = [];
    for (let index = 0; index < serie.length; index += groupSize) {
        const group = serie.slice(index, index + groupSize);
        const totals = Object.fromEntries(['tradicional', 'no_tradicional', 'externas', 'recargas', 'otros'].map((key) => [
            key,
            group.reduce((sum, day) => sum + Number(day[key]), 0),
        ]));
        rows.push({
            key: group[0].fecha,
            label: group.length === 1 ? group[0].fecha.slice(5) : `${group[0].fecha.slice(5)}–${group.at(-1).fecha.slice(5)}`,
            total: Object.values(totals).reduce((sum, value) => sum + value, 0),
            ...totals,
        });
    }

    const maximum = Math.max(1, ...rows.map((row) => row.total));
    return rows.map((row) => ({
        ...row,
        height: activeView.value === 'Participación %' ? (row.total > 0 ? 100 : 0) : (row.total / maximum) * 100,
        segments: [
            { key: 'otros', name: 'Otros', color: 'other' },
            { key: 'recargas', name: 'Recargas', color: 'violet' },
            { key: 'externas', name: 'Externas', color: 'gold' },
            { key: 'no_tradicional', name: 'No Tradicional', color: 'pink' },
            { key: 'tradicional', name: 'Tradicional', color: 'blue' },
        ].filter((segment) => row[segment.key] > 0).map((segment) => ({
            ...segment,
            height: (row[segment.key] / row.total) * 100,
            title: `${segment.name}: ${activeView.value === 'Participación %' ? `${((row[segment.key] / row.total) * 100).toFixed(1)}%` : formatDop(row[segment.key])}`,
        })),
    }));
});
const salesPrizeChart = computed(() => {
    const isRate = activeSalesView.value === 'Tasa de premiación';
    const rows = props.ventasPremios.serie.map((row) => ({
        ...row,
        salesValue: isRate ? (Number(row.ventas) > 0 ? 100 : 0) : Number(row.ventas),
        prizesValue: isRate ? Number(row.tasaPremiacion) : Number(row.premios),
    }));
    const maximum = Math.max(1, ...rows.flatMap((row) => [row.salesValue, row.prizesValue]));

    return rows.map((row) => ({
        ...row,
        salesHeight: (row.salesValue / maximum) * 100,
        prizesHeight: (row.prizesValue / maximum) * 100,
        salesTitle: isRate ? 'Ventas: 100%' : `Ventas: ${formatDop(row.ventas)}`,
        prizesTitle: isRate ? `Tasa: ${Number(row.tasaPremiacion).toFixed(1)}%` : `Premios: ${formatDop(row.premios)}`,
    }));
});
const sales = [82, 60, 74, 77, 58];
const prizes = [56, 33, 47, 44, 37];
const dates = ['09-23', '09-24', '09-25', '09-26', '09-27'];
const entries = [
    { id: '5346', name: 'AGENCIA EJEMPLO 01', login: '07:45', delta: '+15 min tarde', logout: '11:57' },
    { id: '5347', name: 'AGENCIA EJEMPLO 02', login: '07:37', delta: '+7 min tarde', logout: '11:49' },
    { id: '5357', name: 'AGENCIA EJEMPLO 03', login: '06:57', delta: '33 min antes', logout: '09:38' },
];
const filteredEntries = computed(() => entries.filter((row) => `${row.id} ${row.name}`.toLowerCase().includes(search.value.toLowerCase())));

function selectPeriod(period) {
    activePeriod.value = period;
    const days = { '5 días': 5, '15 días': 15, 'Último mes': 30, '30 días': 30, '60 días': 60, '90 días': 90 }[period];
    const end = referenceDate();
    from.value = days ? toDate(new Date(end.getFullYear(), end.getMonth(), end.getDate() - days + 1)) : (props.periodoVentas.primeraFecha || toDate(end));
    until.value = toDate(end);
    applyPeriod();
}

function selectTab(tab) {
    activeTab.value = tab;
    search.value = '';
}

function resetPeriod() {
    selectPeriod('Todo');
}

function searchCatalog() {
    router.get(props.biUrl, { q: catalogSearch.value || undefined, desde: from.value, hasta: until.value }, { preserveState: true, preserveScroll: true });
}

function applyPeriod() {
    if (!from.value || !until.value || from.value > until.value) {
        periodError.value = 'Selecciona un rango de fechas válido.';
        return;
    }

    periodError.value = '';
    router.get(props.biUrl, { q: catalogSearch.value || undefined, desde: from.value, hasta: until.value }, { preserveState: true, preserveScroll: true });
}

function recalcularVentas() {
    recalculoError.value = '';
    router.post(props.recalcularUrl, { desde: from.value, hasta: until.value }, {
        preserveScroll: true,
        onStart: () => { recalculando.value = true; },
        onFinish: () => { recalculando.value = false; },
        onError: (errors) => { recalculoError.value = errors.hasta || errors.desde || 'No se pudo recalcular el período.'; },
    });
}
</script>

<template>
    <div class="bi-shell">
        <header class="bi-header">
            <div class="bi-brand"><span class="bi-logo">BI</span><strong>CONSORCIO JOSELITO</strong><small>Portal de KPIs</small></div>
            <div class="bi-header-actions"><span class="bi-updated">LotoBet: {{ periodoVentas.ultimaFecha || 'sin datos' }} · Datos desde {{ periodoVentas.primeraFecha || '—' }}<br>Catálogo actualizado: {{ catalogoActualizado || 'sin fecha' }}</span><a class="bi-back" :href="menuUrl">← Volver al menú</a></div>
        </header>
        <nav class="bi-nav" aria-label="Secciones BI">
            <button v-for="tab in tabs" :key="tab.key" type="button" class="bi-nav-item" :class="{ active: activeTab === tab.key }" @click="selectTab(tab.key)"><span :class="tab.color">■</span>{{ tab.name }}<b v-if="tab.key === 'limites' && cantidadAlertas" class="bi-nav-badge" title="Productos con alerta">{{ cantidadAlertas }}</b></button>
            <select v-model="zone" class="bi-zone" aria-label="Elegir zona"><option value="">— Elegir zona —</option><option>ZONA NORTE</option><option>ZONA SUR</option><option>ZONA ESTE</option><option>ZONA CENTRAL</option></select>
        </nav>
<div class="bi-filters"><strong>FILTRAR POR PERÍODO</strong><input v-model="from" type="date" aria-label="Fecha inicial"><input v-model="until" type="date" aria-label="Fecha final"><button class="bi-primary" type="button" @click="activePeriod = 'Personalizado'; applyPeriod()">Aplicar</button><button class="bi-clear" type="button" @click="resetPeriod">✕ Todo</button><div class="bi-periods"><button v-for="period in periods" :key="period" type="button" :class="{ active: activePeriod === period }" @click="selectPeriod(period)">{{ period }}</button></div><button class="bi-recalculate" type="button" :disabled="recalculando" title="Recalcula hasta 31 días cerrados de ventas y premios" @click="recalcularVentas">{{ recalculando ? 'Recalculando…' : '↻ Recalcular datos' }}</button></div>
        <main class="bi-main">
            <div v-if="periodError" class="bi-period-error" role="alert">{{ periodError }}</div>
            <div v-if="recalculoError" class="bi-period-error" role="alert">{{ recalculoError }}</div>
            <div v-if="mensajeRecalculo" class="bi-recalculate-success" role="status">{{ mensajeRecalculo }}</div>
            <div v-if="activeTab !== 'productos' && activeTab !== 'limites'" class="bi-notice"><b>BI EN CONSTRUCCIÓN</b> Ventas, premios y resúmenes usan datos reales. Control de Entrada y algunas métricas esperan sus fuentes.</div>
            <div v-if="activeTab !== 'productos' && activeTab !== 'limites' && ventasPorCategoria.diasSinDatos" class="bi-period-error" role="status">Faltan {{ ventasPorCategoria.diasSinDatos }} día(s) de datos históricos en este período. El total mostrado es parcial.</div>
            <div v-if="activeTab !== 'productos' && activeTab !== 'limites' && includesToday" class="bi-notice">El día en curso se muestra arriba. Los gráficos históricos usan días cerrados hasta ayer.</div>
            <template v-if="activeTab === 'dashboard'">
                <section class="bi-panel bi-today">
                    <div class="bi-title"><h2>DÍA EN CURSO <em>PARCIAL</em></h2><span>Lecturas horarias de LotoBet · {{ fechaLecturas }} · {{ lecturasPorHora.length }} lecturas hoy</span></div>
                    <div class="bi-today-body">
                        <div class="bi-today-cards">
                            <div v-for="card in hourlyCards" :key="card.name" class="bi-today-card">
                                <span>{{ card.name }}</span><strong>{{ card.value }}</strong><small>{{ card.detail }}</small>
                                <div v-if="card.name === 'PROMEDIO POR HORA' && promedioHistoricoVentas" class="bi-hour-average-comparison">
                                    <div><span>META DIARIA</span><b>{{ formatDop(promedioHistoricoVentas.promedio) }}</b></div>
                                    <div class="bi-sales-type-ring" :style="{ '--progress': Math.min(100, Math.max(0, promedioHistoricoVentas.porcentaje)) + '%', '--ring-color': '#00a8cc' }" role="img" :aria-label="`Avance de ventas de hoy frente al promedio diario histórico: ${promedioHistoricoVentas.porcentaje.toFixed(1)}%`"><b>{{ Math.min(100, Math.max(0, promedioHistoricoVentas.porcentaje)).toFixed(0) }}%</b></div>
                                </div>
                            </div>
                        </div>
                        <div class="bi-hour-chart">
                            <span>VENTAS ACUMULADAS POR HORA</span>
                            <div v-if="lecturasPorHora.length" class="bi-hour-legend"><span class="blue">● Ventas totales</span><small>Última lectura {{ String(hourlyLatest.hora).padStart(2, '0') }}:00</small></div>
                            <svg v-if="lecturasPorHora.length" viewBox="0 0 640 218" role="img" :aria-label="`Ventas acumuladas desde las 06:00 hasta las 22:00. Última lectura: ${formatDop(hourlyLatest.total)} a las ${String(hourlyLatest.hora).padStart(2, '0')}:00`">
                                <g v-for="tick in hourlyChart.ticks" :key="tick.y"><line x1="72" :y1="tick.y" x2="624" :y2="tick.y" stroke="#d9eaf0"/><text x="65" :y="tick.y + 4" text-anchor="end" fill="#789bab" font-size="10">{{ tick.label }}</text></g>
                                <path v-if="hourlyChart.area" :d="hourlyChart.area" fill="#bce9f6" fill-opacity="0.65"/>
                                <path :d="hourlyChart.line" fill="none" stroke="#0092bf" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle v-for="point in hourlyChart.points" :key="point.hora" :cx="point.x" :cy="point.y" r="3.5" fill="#0092bf" stroke="white" stroke-width="1.5"><title>{{ String(point.hora).padStart(2, '0') }}:00 · {{ formatDop(point.total) }}</title></circle>
                                <text v-for="hour in hourlyChart.hours" :key="hour.hour" :x="hour.x" y="208" text-anchor="middle" fill="#789bab" font-size="9">{{ String(hour.hour).padStart(2, '0') }}:00</text>
                            </svg>
                            <p v-else class="bi-hour-empty">Esperando la primera captura horaria.</p>
                        </div>
                        <div class="bi-group-list"><span>RUTAS CON MÁS VENTA HOY</span><div v-for="ruta in rutasConMasVenta" :key="ruta.nombre"><span>{{ ruta.nombre }}</span><b>{{ formatDop(ruta.monto) }}</b></div><p v-if="!rutasConMasVenta.length">Aún no hay ventas asociadas a rutas en la lectura de hoy.</p></div>
                    </div>
                </section>
                <section class="bi-panel bi-today bi-sales-types" aria-label="Reporte de ventas tradicionales y no tradicionales">
                    <div class="bi-title"><h2>VENTAS POR TIPO · DÍA EN CURSO <em>PARCIAL</em></h2><span>Lectura LotoBet · {{ fechaLecturas }} · {{ hourlyLatest ? `${String(hourlyLatest.hora).padStart(2, '0')}:00` : 'sin lectura' }}</span></div>
                    <div v-if="ventasPorTipoHoy.length" class="bi-sales-types-grid">
                        <article v-for="tipo in ventasPorTipoHoy" :key="tipo.key" class="bi-sales-type-card" :class="tipo.color">
                            <div class="bi-sales-type-heading"><span>{{ tipo.title }}</span><b>{{ tipo.share.toFixed(1) }}% del total</b></div>
                            <strong>{{ formatDop(tipo.amount) }}</strong>
                            <small>{{ formatNumber(tipo.count ?? 0) }} agencias · Promedio: {{ formatDop(tipo.perAgency) }}</small>
                            <div class="bi-sales-type-bottom">
                                <div>
                                    <span>PROMEDIO DE VENTA DIARIO · ÚLTIMOS 3 MESES CALCULADOS</span>
                                    <b>{{ tipo.average === null ? 'Sin calcular' : formatDop(tipo.average) }}</b>
                                    <small v-if="tipo.average !== null" :class="tipo.remaining > 0 ? 'red' : 'green'">{{ tipo.remaining > 0 ? `Faltan ${formatDop(tipo.remaining)} para llegar al 100%` : `Meta diaria superada por ${formatDop(tipo.amount - tipo.average)}` }}</small>
                                </div>
                                <div class="bi-sales-type-ring" :style="{ '--progress': Math.min(100, Math.max(0, tipo.progress ?? 0)) + '%', '--ring-color': tipo.color === 'blue' ? '#00a8cc' : '#db0c81' }" role="img" :aria-label="tipo.progress === null ? 'Promedio sin calcular' : `Cumplimiento ${tipo.progress.toFixed(1)}%`"><b>{{ tipo.progress === null ? '—' : `${Math.min(100, Math.max(0, tipo.progress)).toFixed(0)}%` }}</b></div>
                            </div>
                        </article>
                    </div>
                    <p v-else class="bi-hour-empty">Esperando la primera captura horaria.</p>
                </section>
                <div class="bi-dashboard-layout">
                    <aside class="bi-aside">
                        <button ref="abrirComparativoButton" type="button" class="bi-panel bi-stat bi-day-sales-card" aria-haspopup="dialog" @click="abrirComparativo">
                            <span>VENTAS {{ shortDate(indicadores.comparativoDia.hoy.fecha) }}</span>
                            <strong class="blue">{{ formatCompactDop(indicadores.ventasHoy) }}</strong>
                            <small v-if="indicadores.horaLectura !== null">PARCIAL {{ String(indicadores.horaLectura).padStart(2, '0') }}:00</small>
                            <small v-else>Esperando lectura horaria</small>
                            <small v-for="item in salesComparisons" :key="item.label" class="bi-day-comparison">{{ item.label }}: <b>{{ formatCompactDop(item.amount) }}</b> <b v-if="item.change !== null" :class="item.change >= 0 ? 'green' : 'red'">{{ item.change >= 0 ? '▲' : '▼' }} {{ Math.abs(item.change).toFixed(1) }}%</b></small>
                            <span class="bi-day-card-hint">Ver comparativo ›</span>
                        </button>
                        <div v-for="card in asideCards" :key="card.name" class="bi-panel bi-stat">
                            <span>{{ card.name }}</span><strong :class="card.color">{{ card.value }}</strong><small>{{ card.detail }}</small>
                        </div>
                        <div class="bi-panel bi-stat">
                            <span>VENTAS 0</span><strong class="red">{{ indicadores.terminalesSinVenta === null ? '—' : formatNumber(indicadores.terminalesSinVenta) }}</strong><small>Sin venta de loterías LotoBet · última lectura</small>
                        </div>
                        <div class="bi-panel bi-stat bi-agency-percent">
                            <span>TERMINALES %</span><small>Con venta por categoría · última lectura</small>
                            <div v-for="category in coverageCards" :key="category.name" class="bi-agency-percent-row">
                                <span><i :class="category.color"></i>{{ category.name }}</span><b :class="category.color">{{ category.value }}</b><small>{{ category.count }}</small>
                            </div>
                        </div>
                    </aside>
                    <div class="bi-content-stack">
                    <section class="bi-panel bi-chart-panel"><div class="bi-title"><h2>VENTAS · PREMIOS — CONSORCIO</h2><div class="bi-toggle"><button :class="{ active: activeSalesView === 'Monto' }" @click="activeSalesView = 'Monto'">Monto</button><button :class="{ active: activeSalesView === 'Tasa de premiación' }" @click="activeSalesView = 'Tasa de premiación'">Tasa de premiación</button></div></div><div class="bi-chips"><span class="blue">● Ventas</span><span class="red">● Premios</span></div><div class="bi-metric-grid"><div v-for="metric in metrics" :key="metric.name" class="bi-metric"><span :class="metric.color">● {{ metric.name }}</span><strong>{{ metric.value }}</strong><small :class="metric.color">{{ metric.change }}</small><svg v-if="metricSparklines[metric.key]" class="bi-metric-sparkline" :class="metric.color" viewBox="0 0 280 46" preserveAspectRatio="none" role="img" :aria-label="`Evolución diaria de ${metric.name.toLowerCase()} entre ${ventasPremios.desde} y ${ventasPremios.hasta}`"><polygon :points="metricSparklines[metric.key].area" fill="currentColor" opacity="0.1"/><polyline :points="metricSparklines[metric.key].line" fill="none" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke"/><circle :cx="metricSparklines[metric.key].last.x" :cy="metricSparklines[metric.key].last.y" r="2.5" fill="currentColor"/><title>{{ metric.name }} · {{ ventasPremios.desde }} — {{ ventasPremios.hasta }}</title></svg><div v-else class="bi-metric-sparkline-empty">Sin días cerrados</div></div></div><div v-if="salesPrizeChart.length" class="bi-bars-chart real-series" :class="{ dense: salesPrizeChart.length > 15 }"><div v-for="row in salesPrizeChart" :key="row.fecha" class="bi-bar-set"><div class="bi-bars"><div class="bi-bar sales" :style="{ height: `${row.salesHeight}%` }"><span class="bi-tooltip">{{ row.salesTitle }}</span></div><div class="bi-bar prizes" :style="{ height: `${row.prizesHeight}%` }"><span class="bi-tooltip">{{ row.prizesTitle }}</span></div></div><small>{{ row.etiqueta }}</small></div></div><div v-else class="bi-empty">No hay datos para el período seleccionado.</div></section>
<section class="bi-panel bi-chart-panel">
    <div class="bi-title"><h2>DESGLOSE POR CATEGORÍA — LOTOBET · DATOS REALES</h2><div class="bi-toggle"><button :class="{ active: activeView === 'Monto' }" @click="activeView = 'Monto'">Monto</button><button :class="{ active: activeView === 'Participación %' }" @click="activeView = 'Participación %'">Participación %</button></div></div>
    <div class="bi-category-strip"><span v-for="category in categories" :key="category.key" :class="category.color" :style="{ width: `${category.percentage}%` }"></span><span v-if="ventasPorCategoria.otros" class="other" :style="{ width: `${(ventasPorCategoria.otros / ventasPorCategoria.total) * 100}%` }"></span></div>
    <div class="bi-metric-grid"><div v-for="category in categories" :key="category.key" class="bi-metric"><span :class="category.color">● {{ category.name }}</span><strong>{{ category.value }}</strong><small>{{ category.detail }}</small><svg v-if="categorySparklines[category.key]" class="bi-metric-sparkline" :class="category.color" viewBox="0 0 280 46" preserveAspectRatio="none" role="img" :aria-label="`Evolución de ${category.name.toLowerCase()} entre ${from} y ${until}`"><polygon :points="categorySparklines[category.key].area" fill="currentColor" opacity="0.1"/><polyline :points="categorySparklines[category.key].line" fill="none" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke"/><circle :cx="categorySparklines[category.key].last.x" :cy="categorySparklines[category.key].last.y" r="2.5" fill="currentColor"/><title>{{ category.name }} · {{ from }} — {{ until }}</title></svg><div v-else class="bi-metric-sparkline-empty">Sin datos en el período</div></div></div>
    <p class="bi-caption">Total del período: <b>{{ formatDop(ventasPorCategoria.total) }}</b> · {{ ventasPorCategoria.desde && ventasPorCategoria.hasta ? `${ventasPorCategoria.desde} — ${ventasPorCategoria.hasta}` : 'Sin días cerrados' }}</p>
    <div v-if="categoryChart.length" class="bi-bars-chart real-series category-series" :class="{ dense: categoryChart.length > 15 }"><div v-for="row in categoryChart" :key="row.key" class="bi-bar-set"><div class="bi-stack" :style="{ height: `${row.height}%` }"><span v-for="segment in row.segments" :key="segment.key" :class="segment.color" :style="{ height: `${segment.height}%` }" :title="segment.title"></span></div><small>{{ row.label }}</small></div></div><div v-else class="bi-empty">No hay ventas para el período seleccionado.</div>
</section>
                </div></div>
                <section class="bi-panel bi-table-panel"><div class="bi-title"><h2>CATÁLOGO DE TERMINALES — AGENCIAS <small class="bi-real-tag">DATOS REALES · {{ terminales.total }} TERMINALES</small></h2><form class="bi-catalog-search" @submit.prevent="searchCatalog"><input v-model="catalogSearch" placeholder="Buscar terminal o agencia…" aria-label="Buscar terminal o agencia"><button type="submit">Buscar</button></form></div><div class="bi-table-scroll"><table><thead><tr><th>#</th><th>Terminal</th><th>Agencia</th><th>Código</th><th>Empresa</th><th>Ciudad</th><th>Estado</th></tr></thead><tbody><tr v-for="(row, index) in terminales.data" :key="row.id"><td>{{ terminales.from + index }}</td><td><b>{{ row.terminal }}</b></td><td>{{ row.nombre_agencia || 'Sin nombre' }}</td><td>{{ row.agencia || '—' }}</td><td>{{ row.empresa || '—' }}</td><td>{{ row.ciudad || '—' }}</td><td><span :class="row.estatus === 1 ? 'green' : 'red'">{{ row.estatus === 1 ? 'Activa' : 'Inactiva' }}</span></td></tr><tr v-if="!terminales.data.length"><td colspan="7" class="bi-empty">No se encontraron terminales</td></tr></tbody></table></div><div class="bi-pagination"><span>Mostrando {{ terminales.from || 0 }}–{{ terminales.to || 0 }} de {{ terminales.total }}</span><div><a v-if="terminales.prev_page_url" :href="terminales.prev_page_url">← Anterior</a><span>Página {{ terminales.current_page }} de {{ terminales.last_page }}</span><a v-if="terminales.next_page_url" :href="terminales.next_page_url">Siguiente →</a></div></div></section>
            </template>
            <template v-else-if="activeTab === 'productos'">
                <section class="bi-panel bi-products-empty" aria-label="Record de Productos">
                    <h1>Record de Productos</h1>
                    <article v-for="producto in recordsProductos" :key="producto.name" class="bi-product-record" :class="{ achieved: producto.nuevoRecord }">
                        <div class="bi-product-goal"><span>% Alcanzado de la Meta</span><div class="bi-product-goal-ring" :style="{ '--progress': Math.min(100, Math.max(0, producto.alcanzado ?? 0)) + '%' }" role="img" :aria-label="producto.alcanzado === null ? 'Sin récord histórico disponible' : `Meta alcanzada: ${producto.alcanzado.toFixed(2)}%`"><b>{{ producto.alcanzado === null ? '—' : `${producto.alcanzado.toFixed(2)}%` }}</b></div></div>
                        <div class="bi-product-data"><h2>{{ producto.name }}</h2><div class="bi-product-record-grid">
                            <div><span>Venta del día</span><strong>{{ producto.hoy === null ? '—' : formatNumber(producto.hoy) }}</strong></div>
                            <div><span>Venta sem ant</span><strong>{{ producto.semanaAnterior === null ? '—' : formatNumber(producto.semanaAnterior) }}</strong></div>
                            <div><span>Record</span><strong>{{ producto.record === null ? '—' : formatNumber(producto.record) }}</strong></div>
                            <div><span>Diferencia</span><strong :class="producto.diferencia === null ? '' : producto.diferencia < 0 ? 'red' : 'green'">{{ producto.diferencia === null ? '—' : `${producto.diferencia > 0 ? '+' : ''}${producto.diferencia.toFixed(2)}%` }}</strong></div>
                        </div></div>
                    </article>
                </section>
            </template>
            <template v-else-if="activeTab === 'entrada'"><div class="bi-heading"><h1>Control de Entrada — Consorcio</h1><p>Todas las zonas. Usa el filtro de fecha global.</p></div><div class="bi-subnav"><button v-for="view in ['Detalle diario', '🏆 Top Puntualidad', '⚠️ Top Impuntualidad']" :key="view" :class="{ active: activeEntry === view }" @click="activeEntry = view">{{ view }}</button></div><div class="bi-date-buttons"><span>Fecha:</span><button v-for="date in ['09/29', '09/28', '09/27', '09/26', '09/25']" :key="date">{{ date }}</button></div><div class="bi-kpi-grid three"><div v-for="card in [{ name: 'PUNTUALES ENTRADA', value: '270', detail: 'de 1271 terminales', color: 'blue' }, { name: 'TARDANZAS ENTRADA', value: '1,001', detail: 'llegaron tarde', color: 'red' }, { name: 'CIERRES ANTICIPADOS', value: '1,271', detail: 'salieron antes', color: 'gold' }]" :key="card.name" class="bi-panel bi-stat"><span>{{ card.name }}</span><strong :class="card.color">{{ card.value }}</strong><small>{{ card.detail }}</small></div></div><section class="bi-panel bi-table-panel"><div class="bi-title"><h2>CONTROL DE ENTRADA — CONSORCIO · 1,271 TERMINALES</h2><input v-model="search" placeholder="Buscar terminal…" aria-label="Buscar terminal"></div><div class="bi-table-scroll"><table><thead><tr><th>#</th><th>Terminal</th><th>Agencia</th><th>Empleado T1</th><th>Prog.</th><th>Login</th><th>Δ Entrada</th><th>Logout</th><th>Empleado T2</th><th>Cierre</th></tr></thead><tbody><tr v-for="(row, index) in filteredEntries" :key="row.id"><td>{{ index + 1 }}</td><td>{{ row.id }}</td><td>{{ row.name }}</td><td>EMPLEADO EJEMPLO</td><td>07:30–14:30</td><td>{{ row.login }}</td><td :class="row.delta.includes('tarde') ? 'red' : 'green'">{{ row.delta }}</td><td>{{ row.logout }}</td><td>—</td><td>21:30</td></tr><tr v-if="!filteredEntries.length"><td colspan="10" class="bi-empty">Sin resultados</td></tr></tbody></table></div></section></template>
<template v-else-if="activeTab === 'limites'">
                <div class="bi-heading"><h1>Límite de Ventas — Consorcio</h1><p>Alertas diarias por producto · Globales y por terminal · {{ fechaLecturas }}.</p></div>
                <div class="bi-kpi-grid three">
                    <div class="bi-panel bi-stat"><span>LÍMITES ACTIVOS</span><strong>{{ productosVigilados }}</strong><small>Globales y por terminal</small></div>
                    <div class="bi-panel bi-stat"><span>LÍMITES ALCANZADOS</span><strong class="red">{{ cantidadAlertas }}</strong><small>Alertas que requieren atención</small></div>
                    <div class="bi-panel bi-stat"><span>ÚLTIMA LECTURA</span><strong>{{ alertasProductos[0]?.capturadoEn?.slice(11, 16) || '—' }}</strong><small>Ventas acumuladas del día</small></div>
                </div>
                <section class="bi-panel bi-table-panel">
                    <div class="bi-title"><h2>CONFIGURAR ALERTA POR PRODUCTO</h2></div>
                    <p class="bi-caption">Selecciona un producto o el total de Tradicionales o No tradicionales. El límite global vigila la venta total en el consorcio; el límite por terminal vigila únicamente esa terminal. Puedes configurar ambos a la vez; son diarios y no bloquean ventas.</p>
                    <form class="bi-limit-form" @submit.prevent="guardarLimite">
                        <div><label for="limite-alcance">Aplicar límite</label><select id="limite-alcance" v-model="limiteForm.alcance" @change="cambiarAlcanceLimite"><option value="global">Global — Consorcio</option><option value="terminal">Por terminal</option></select><small v-if="limiteForm.errors.alcance" class="red" role="alert">{{ limiteForm.errors.alcance }}</small></div>
                        <div v-if="limiteForm.alcance === 'terminal'"><label for="limite-terminal">Terminal</label><input id="limite-terminal" v-model="limiteForm.terminal" list="terminales-limites" placeholder="Escribe el código de terminal" required @change="seleccionarProductoLimite"><datalist id="terminales-limites"><option v-for="terminal in terminalesLimites" :key="terminal.terminal" :value="terminal.terminal">{{ terminal.nombre }}</option></datalist><small v-if="nombresTerminales[limiteForm.terminal]">{{ nombresTerminales[limiteForm.terminal] }}</small><small v-if="limiteForm.errors.terminal" class="red" role="alert">{{ limiteForm.errors.terminal }}</small></div>
                        <div>
                            <label for="limite-producto">Producto a vigilar</label>
                            <select id="limite-producto" v-model="limiteForm.producto_id" required @change="seleccionarProductoLimite">
                                <option value="" disabled>Seleccionar producto…</option>
                                <optgroup v-if="gruposDisponibles.length" label="Totales por tipo">
                                    <option v-for="grupo in gruposDisponibles" :key="grupo.producto_id" :value="grupo.producto_id">{{ grupo.descripcion }}</option>
                                </optgroup>
                                <optgroup v-if="productosDisponibles.length" label="Productos individuales">
                                    <option v-for="producto in productosDisponibles" :key="producto.producto_id" :value="producto.producto_id">{{ producto.descripcion || 'Producto ' + producto.producto_id }} ({{ producto.producto_id }})</option>
                                </optgroup>
                            </select>
                            <small v-if="limiteForm.errors.producto_id" class="red" role="alert">{{ limiteForm.errors.producto_id }}</small>
                        </div>
                        <div><label for="limite-monto">Límite diario (DOP)</label><div class="bi-currency-input"><span aria-hidden="true">DOP</span><input id="limite-monto" :value="formatCurrencyInput(limiteForm.monto)" type="text" inputmode="decimal" placeholder="100,000.00" required @input="actualizarMontoLimite" @blur="completarMontoLimite" @keydown="borrarSeparadorMonto"></div><small v-if="limiteForm.errors.monto" class="red" role="alert">{{ limiteForm.errors.monto }}</small></div>
                        <div><label for="limite-activo">Estado</label><select id="limite-activo" v-model="limiteForm.activo"><option :value="true">Activa</option><option :value="false">Pausada</option></select><small v-if="limiteForm.errors.activo" class="red" role="alert">{{ limiteForm.errors.activo }}</small></div>
                        <button type="submit" class="bi-primary" :disabled="limiteForm.processing || (!productosDisponibles.length && !gruposDisponibles.length)">{{ limiteForm.processing ? 'Guardando…' : 'Guardar alerta' }}</button>
                    </form>
                    <p v-if="!productosDisponibles.length" class="bi-caption">No hay productos disponibles en el catálogo.</p>
                    <p v-if="limiteGuardado && mensajeLimite" class="bi-recalculate-success" role="status">{{ mensajeLimite }}</p>
                </section>
                <section class="bi-panel bi-table-panel">
                    <div class="bi-title"><h2>PRODUCTOS EN VIGILANCIA</h2><input v-model="search" placeholder="Buscar producto o terminal…" aria-label="Buscar producto o terminal"></div>
                    <div class="bi-subnav compact"><button v-for="option in [{ key: 'todos', label: 'Todos los límites' }, { key: 'global', label: 'Globales' }, { key: 'terminal', label: 'Por terminal' }]" :key="option.key" type="button" :class="{ active: filtroAlcance === option.key }" @click="filtroAlcance = option.key">{{ option.label }}</button></div>
                    <div class="bi-subnav compact"><button type="button" :class="{ active: !soloAlertas }" @click="soloAlertas = false">Todos</button><button type="button" :class="{ active: soloAlertas }" @click="soloAlertas = true">Con alerta ({{ cantidadAlertas }})</button></div>
                    <div class="bi-table-scroll"><table><thead><tr><th>Estado</th><th>Producto</th><th>Alcance / Terminal</th><th>Límite diario</th><th>Venta de hoy</th><th>Consumo</th><th>Acciones</th></tr></thead><tbody>
                        <tr v-for="row in filteredLimits" :key="row.id">
                            <td><span :class="row.alerta ? 'bi-alert' : ''">{{ row.estado }}</span></td><td><b>{{ row.nombre }}</b><template v-if="!row.esGrupo"><br><small>{{ row.producto_id }}</small></template></td>
                            <td><b>{{ row.alcance === 'global' ? 'Global — Consorcio' : 'Terminal ' + row.terminal }}</b><br><small v-if="row.terminal">{{ nombresTerminales[row.terminal] || 'Sin nombre en catálogo' }}</small></td>
                            <td>{{ formatLimiteDop(row.monto) }}</td><td>{{ row.ventas === null ? 'Sin lectura' : formatLimiteDop(row.ventas) }}</td>
                            <td><b :class="{ red: row.alerta }">{{ row.porcentaje === null ? '—' : row.porcentaje + '%' }}</b><div v-if="row.porcentaje !== null" class="bi-progress"><span :class="row.alerta ? 'red' : 'blue'" :style="{ width: Math.max(0, Math.min(row.porcentaje, 100)) + '%' }"></span></div></td>
                            <td><button type="button" @click="editarLimite(row)">Editar</button></td>
                        </tr>
                        <tr v-if="!filteredLimits.length"><td colspan="7" class="bi-empty">{{ alertasProductos.length ? 'Sin resultados para este filtro.' : 'Agrega una alerta para empezar a vigilar productos.' }}</td></tr>
                    </tbody></table></div>
                    <p class="bi-caption">La venta se actualiza con las capturas del día. Sin una lectura nueva por producto, se muestra “Sin lectura”.</p>
                </section>
            </template>
            <template v-else><div class="bi-heading"><h1>Métricas — Consorcio</h1><p>Indicadores comparativos del período seleccionado.</p></div><div class="bi-kpi-grid four"><div v-for="metric in metrics" :key="metric.name" class="bi-panel bi-stat"><span>{{ metric.name.toUpperCase() }}</span><strong :class="metric.color">{{ metric.value }}</strong><small>{{ metric.change }}</small></div></div><section class="bi-panel bi-chart-panel"><div class="bi-title"><h2>EVOLUCIÓN DE INDICADORES</h2></div><span class="bi-demo-label">Gráfico ilustrativo pendiente de datos comparativos</span><div class="bi-bars-chart"><div v-for="(height, index) in sales" :key="index" class="bi-bar-set"><div class="bi-bars"><div class="bi-bar sales" :style="{ height: `${height}%` }"></div><div class="bi-bar prizes" :style="{ height: `${prizes[index]}%` }"></div></div><small>{{ dates[index] }}</small></div></div></section></template>
        </main>
        <div v-if="comparativoAbierto" class="bi-modal-backdrop" @click.self="cerrarComparativo">
            <section class="bi-comparison-modal" role="dialog" aria-modal="true" aria-labelledby="bi-comparison-title">
                <div class="bi-comparison-header"><h2 id="bi-comparison-title">Comparativo — Consorcio · {{ indicadores.comparativoDia.hoy.fecha }}</h2><button ref="cerrarComparativoButton" type="button" aria-label="Cerrar comparativo" @click="cerrarComparativo">×</button></div>
                <p class="bi-comparison-note">Ventas de hoy: lectura parcial {{ indicadores.horaLectura === null ? 'pendiente' : `${String(indicadores.horaLectura).padStart(2, '0')}:00` }}. Los días anteriores usan resúmenes cerrados.</p>
                <div class="bi-comparison-scroll"><table><thead><tr><th>Métrica</th><th>{{ shortDate(indicadores.comparativoDia.hoy.fecha) }}</th><th>{{ shortDate(indicadores.comparativoDia.ayer.fecha) }}</th><th>{{ shortDate(indicadores.comparativoDia.mesAnterior.fecha) }} (mes ant.)</th></tr></thead><tbody><tr v-for="row in comparisonRows" :key="row.key"><td>{{ row.label }}</td><td v-for="period in ['hoy', 'ayer', 'mesAnterior']" :key="period" :class="row.color" :title="indicadores.comparativoDia[period][row.key] === null ? 'Dato no disponible' : undefined">{{ row.format(indicadores.comparativoDia[period][row.key]) }}</td></tr></tbody></table></div>
                <p class="bi-comparison-note">— indica que aún no hay datos de esa métrica. Las agencias activas de fechas anteriores no tienen histórico disponible.</p>
            </section>
        </div>
    </div>
</template>

<style scoped>
.bi-catalog-search { display: flex; gap: 8px; flex-wrap: wrap; }
.bi-catalog-search button { border: 0; border-radius: 8px; background: #05abc8; color: white; font-weight: 700; padding: 8px 14px; }
.bi-real-tag { display: inline-block; margin-left: 8px; color: #15904d; font-size: 11px; font-weight: 700; }
.bi-pagination { display: flex; justify-content: space-between; gap: 12px; align-items: center; flex-wrap: wrap; padding-top: 18px; color: #6a8c9e; font-size: 13px; }
.bi-pagination div { display: flex; align-items: center; gap: 14px; }
.bi-pagination a { border: 1px solid #cce5ef; border-radius: 8px; padding: 7px 10px; color: #008fbc; text-decoration: none; }
.bi-recalculate { margin-left: auto; border: 1px solid #00a8cc; border-radius: 8px; background: white; color: #008fac; font-weight: 700; padding: 8px 12px; white-space: nowrap; }
.bi-recalculate:disabled { opacity: .6; cursor: wait; }
.bi-recalculate-success { margin-bottom: 14px; padding: 9px 13px; border: 1px solid #a8dec2; border-radius: 9px; background: #ecfbf2; color: #167043; font-size: 13px; }
.bi-hour-chart svg { display: block; height: auto; margin-top: 8px; }
.bi-hour-legend { align-items: center; flex-wrap: wrap; }
.bi-hour-legend small { color: #789bab; }
.bi-products-empty { min-height: 380px; padding: 30px 32px; }
.bi-products-empty h1 { margin: 0; color: #1e293b; font-size: 22px; }
.bi-product-record { display: grid; grid-template-columns: 240px minmax(0, 430px); gap: 28px; max-width: 780px; margin-top: 22px; padding: 22px 26px; border: 1px solid #cdeaf1; border-radius: 24px; background: linear-gradient(135deg, #f0fbfe, #fff 70%); }
.bi-product-record.achieved { border-color: #34d399; background: linear-gradient(135deg, #dcfce7, #bbf7d0); }
.bi-product-goal { display: flex; flex-direction: column; align-items: center; gap: 15px; color: #328e9c; font-weight: 800; font-size: 17px; }
.bi-product-goal-ring { width: 145px; height: 145px; display: grid; place-items: center; border-radius: 50%; background: conic-gradient(#00a8cc var(--progress), #e2e8f0 0); position: relative; }
.bi-product-goal-ring::before { content: ''; position: absolute; inset: 13px; border-radius: 50%; background: white; }
.bi-product-record.achieved .bi-product-goal-ring { background: conic-gradient(#059669 100%, #d1fae5 0); }
.bi-product-goal-ring b { position: relative; z-index: 1; color: #64748b; font-size: 23px; }
.bi-product-data h2 { margin: 0 0 17px; color: #328e9c; font-size: 28px; text-align: center; }
.bi-product-record-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 14px; }
.bi-product-record-grid > div { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 5px; min-height: 82px; border: 1px solid #cbd5e1; border-radius: 12px; background: white; box-shadow: 0 5px 12px #0f172a12; text-align: center; }
.bi-product-record-grid span { color: #334155; font-weight: 700; }
.bi-product-record-grid strong { color: #1e293b; font-size: 24px; }
.bi-product-record-grid strong.red { color: #e11d48; }
.bi-product-record-grid strong.green { color: #059669; }
@media (max-width: 760px) { .bi-product-record { grid-template-columns: 1fr; padding: 18px; } .bi-product-data h2 { font-size: 24px; } }
.bi-today-cards { grid-template-columns: 1fr; align-content: start; gap: 10px; }
.bi-today-card { padding: 13px 16px; gap: 5px; }
.bi-today-card strong { color: #008fb8; font-size: 29px; }
.bi-today-card span { font-size: 13px; }
.bi-today-card small { font-size: 13px; }
.bi-hour-average-comparison { display: flex; flex-direction: column; align-items: stretch; gap: 8px; margin-top: 8px; padding-top: 8px; border-top: 1px solid #dbe8ed; }
.bi-hour-average-comparison > div:first-child { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.bi-hour-average-comparison span { font-size: 11px; }
.bi-hour-average-comparison > div:first-child b { color: #008fb8; font-size: 20px; font-weight: 800; }
.bi-hour-average-comparison .bi-sales-type-ring { width: 108px; height: 108px; flex-basis: 108px; align-self: center; }
.bi-hour-average-comparison .bi-sales-type-ring b { font-size: 19px; color: #1e293b; }
@media (min-width: 1251px) { .bi-today-body { grid-template-columns: minmax(250px, .75fr) minmax(0, 1.8fr) minmax(300px, .8fr); } }
.bi-sales-types { min-height: 285px; }
.bi-sales-types-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
.bi-sales-type-card { border: 1px solid #bce8f2; border-radius: 16px; padding: 21px 24px; background: #fff; min-width: 0; }
.bi-sales-type-card.pink { border-color: #f2c3df; }
.bi-sales-type-heading { display: flex; justify-content: space-between; gap: 12px; color: #64748b; font-size: 12px; font-weight: 700; }
.bi-sales-type-heading b { color: #0891b2; white-space: nowrap; }
.bi-sales-type-card.pink .bi-sales-type-heading b { color: #db0c81; }
.bi-sales-type-card > strong { display: block; margin-top: 12px; font-size: clamp(22px, 2vw, 31px); color: #008fb8; }
.bi-sales-type-card.pink > strong { color: #db0c81; }
.bi-sales-type-card > small { color: #64748b; font-weight: 700; }
.bi-sales-type-bottom { display: flex; align-items: center; justify-content: space-between; gap: 16px; border-top: 1px solid #e2e8f0; margin-top: 18px; padding-top: 16px; }
.bi-sales-type-bottom > div:first-child { display: flex; flex-direction: column; gap: 4px; }
.bi-sales-type-bottom span { font-size: 11px; color: #64748b; }
.bi-sales-type-bottom b { color: #1e293b; }
.bi-sales-type-bottom small { font-size: 12px; color: #64748b; }
.bi-sales-type-bottom small.red { color: #e11d48; font-weight: 700; }.bi-sales-type-bottom small.green { color: #059669; font-weight: 700; }
.bi-sales-type-ring { width: 76px; height: 76px; flex: 0 0 76px; border-radius: 50%; background: conic-gradient(var(--ring-color) var(--progress), #e2e8f0 0); display: grid; place-items: center; position: relative; }
.bi-sales-type-ring::before { content: ''; position: absolute; inset: 8px; background: white; border-radius: 50%; }
.bi-sales-type-ring b { position: relative; color: #1e293b; }
@media (max-width: 760px) { .bi-sales-types-grid { grid-template-columns: 1fr; } }
</style>



