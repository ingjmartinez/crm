document.addEventListener('DOMContentLoaded', function () {
    const configuracion = window.rentabilidadV8;
    const moneda = valor => new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(Number(valor || 0));
    const empresa = document.getElementById('empresaV8');
    const ruta = document.getElementById('rutaV8');
    const actualizarRutas = () => {
        const seleccionada = ruta.value;
        const rutas = [...new Set(configuracion.rutasDisponibles.filter(fila => !empresa.value || fila.empresa === empresa.value).map(fila => fila.ruta))];
        ruta.replaceChildren(new Option('Todas las rutas', ''), ...rutas.map(nombre => new Option(nombre, nombre)));
        ruta.value = rutas.includes(seleccionada) ? seleccionada : '';
    };
    empresa.addEventListener('change', actualizarRutas);
    actualizarRutas();
    const completo = document.getElementById('anioCompletoV8');
    const inicio = document.getElementById('mes_inicioV8');
    const fin = document.getElementById('mes_finV8');
    const actualizarAnio = () => {
        if (completo.checked) { inicio.value = '1'; fin.value = '12'; }
    };
    completo.addEventListener('change', actualizarAnio);
    [inicio, fin].forEach(select => select.addEventListener('change', () => { completo.checked = false; }));
    actualizarAnio();
    const alcance = document.getElementById('alcanceV8');
    const terminal = document.getElementById('terminalV8');
    const agrupacion = document.getElementById('agrupacionV8');
    const actualizarTerminal = () => {
        const porRuta = agrupacion.value === 'ruta';
        alcance.disabled = porRuta;
        terminal.disabled = porRuta || alcance.value !== 'terminal';
        terminal.required = !porRuta && alcance.value === 'terminal';
    };
    alcance.addEventListener('change', actualizarTerminal);
    agrupacion.addEventListener('change', actualizarTerminal);
    actualizarTerminal();
    let consultando = false;
    document.getElementById('filtrosRentabilidadV8').addEventListener('submit', function (event) {
        if (consultando) {
            event.preventDefault();
            return;
        }
        consultando = true;
        const boton = this.querySelector('button[type="submit"]');
        boton.disabled = true;
        boton.textContent = 'Consultando meses...';
        if (window.Swal) {
            event.preventDefault();
            const formulario = this;
            window.Swal.fire({
                title: 'Consultando datos...',
                text: 'Estamos preparando la comparación de los meses seleccionados.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    window.Swal.showLoading();
                    setTimeout(() => HTMLFormElement.prototype.submit.call(formulario), 50);
                },
            });
        }
    });
    window.addEventListener('pageshow', function () {
        if (consultando) window.Swal?.close();
        consultando = false;
        const boton = document.querySelector('#filtrosRentabilidadV8 button[type="submit"]');
        boton.disabled = false;
        boton.textContent = 'Consultar';
    });
    if (!configuracion.datos.length || !document.getElementById('tablaRentabilidadV8')) return;

    const medidas = ['ventas', 'premios_sacados', 'utilidad', 'gastos', 'balance_general'];
    const etiquetas = ['Ventas', 'Premios sacados', 'Utilidad', 'Gastos', 'Balance general'];
    const porRuta = configuracion.agrupacion === 'ruta';
    const unidad = porRuta ? 'rutas' : 'terminales';
    const escapar = valor => String(valor ?? '').replace(/[&<>"']/g, caracter => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[caracter]);
    const conPorcentaje = (campo, importe, ventas) => {
        const porcentaje = Number(ventas) === 0 ? '—' : `${(Number(importe) / Number(ventas) * 100).toFixed(2)}%`;
        const clase = campo === 'premios_sacados' ? 'porcentaje-premios' : 'porcentaje-balance';
        return `<div>${moneda(importe)}</div><small class="porcentaje ${clase}">${porcentaje}</small>`;
    };
    const encabezados = [porRuta ? 'Ruta / Empresa' : 'Terminal / Agencia', ...configuracion.meses.flatMap(mes => etiquetas.map(etiqueta => `${etiqueta} · ${mes.codigo}`))];
    const columnas = [
        { data: 'terminal', render: (valor, tipo, fila) => {
            if (porRuta) {
                if (tipo === 'display') return `<div class="fw-semibold">${escapar(fila.nombre)}</div><small class="text-muted">${escapar(fila.empresa)} · ${Number(fila.cantidad_terminales)} terminales</small>`;
                return `${fila.nombre} - ${fila.empresa}`;
            }
            if (tipo === 'display') return `<div class="fw-semibold">${escapar(fila.nombre)}</div><small class="text-muted">Terminal: ${escapar(valor)}</small>`;
            if (tipo === 'sort' || tipo === 'type') return valor;
            return `${valor} - ${fila.nombre}`;
        } },
        ...configuracion.meses.flatMap((mes, indiceMes) => medidas.map((campo, indiceCampo) => ({
            data: `periodos.${indiceMes}.${campo}`,
            defaultContent: 0,
            className: `text-end fw-bold${indiceCampo === 0 ? ' inicio-mes' : ''}`,
            render: (valor, tipo, fila) => {
                if (tipo !== 'display') return Number(valor || 0);
                return ['premios_sacados', 'balance_general'].includes(campo)
                    ? conPorcentaje(campo, valor, campo === 'premios_sacados' ? (fila.periodos[indiceMes].ventas_loterias || 0) : fila.periodos[indiceMes].ventas)
                    : moneda(valor);
            },
            createdCell: (celda, valor) => {
                if (campo === 'balance_general' && Number(valor) !== 0) celda.classList.add(Number(valor) > 0 ? 'text-success' : 'text-danger');
            },
        }))),
    ];
    $('#tablaRentabilidadV8').DataTable({
        data: configuracion.datos,
        columns: columnas,
        deferRender: true,
        pageLength: 25,
        scrollX: true,
        autoWidth: false,
        order: [[0, 'asc']],
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'print'].map(extend => ({
            extend,
            title: 'Rentabilidad mensual V8',
            exportOptions: {
                orthogonal: 'export',
                modifier: { search: 'applied' },
                format: { header: (contenido, indice) => encabezados[indice] },
            },
        })),
        initComplete: function () {
            document.getElementById('cargandoV8').classList.add('d-none');
            document.getElementById('contenedorTablaV8').classList.remove('d-none');
            this.api().columns.adjust();
        },
        footerCallback: function () {
            const api = this.api();
            const filas = api.rows({ search: 'applied' }).data().toArray();
            api.column(0).footer().textContent = `Totales filtrados · ${filas.length} ${unidad}`;
            configuracion.meses.forEach((mes, indiceMes) => medidas.forEach((campo, indiceCampo) => {
                const total = Math.round(filas.reduce((suma, fila) => suma + Number(fila.periodos[indiceMes]?.[campo] || 0), 0) * 100) / 100;
                const celda = api.column(1 + indiceMes * medidas.length + indiceCampo).footer();
                celda.textContent = moneda(total);
                if (['premios_sacados', 'balance_general'].includes(campo)) {
                    const baseVentas = campo === 'premios_sacados' ? 'ventas_loterias' : 'ventas';
                    const ventas = filas.reduce((suma, fila) => suma + Number(fila.periodos[indiceMes]?.[baseVentas] || 0), 0);
                    celda.innerHTML = conPorcentaje(campo, total, ventas);
                }
                celda.classList.remove('text-success', 'text-danger');
                if (campo === 'balance_general' && total !== 0) celda.classList.add(total > 0 ? 'text-success' : 'text-danger');
            }));
        },
        language: { search: porRuta ? 'Buscar ruta o empresa:' : 'Buscar agencia o terminal:', info: `Mostrando _START_ a _END_ de _TOTAL_ ${unidad}`, infoEmpty: `Sin ${unidad}`, zeroRecords: 'Sin coincidencias', emptyTable: 'Sin datos', paginate: { next: 'Siguiente', previous: 'Anterior' } },
    });
});
