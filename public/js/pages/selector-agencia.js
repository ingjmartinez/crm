window.iniciarSelectorAgencia = function (configuracion) {
    if (!configuracion) return;
    const moneda = valor => valor == null ? '—' : new Intl.NumberFormat('es-DO', {style:'currency', currency:'DOP', minimumFractionDigits:0, maximumFractionDigits:0}).format(Number(valor));
    const campos = ['ventas_tradicionales', 'premios_tradicionales'];
    const encabezadosImportes = configuracion.meses.flatMap(mes => ['Ventas tradicionales', 'Premios tradicionales'].map(etiqueta => `${etiqueta} · ${mes.codigo}`));
    const columnasImportes = configuracion.meses.flatMap((mes, indice) => campos.map(campo => ({
        data:`periodos.${indice}.${campo}`, defaultContent:'', className:'text-end',
        render:(valor, tipo) => {
            if (tipo !== 'display') return valor == null ? '' : Number(valor);
            const clase = campo === 'ventas_tradicionales' ? (valor > 1000000 ? 'venta-millon' : valor >= 100000 ? 'venta-alta' : 'venta-baja') : '';
            return `<strong class="${clase}">${moneda(valor)}</strong>`;
        },
    })));
    const seccion = document.getElementById('supervisionSelectorAgencia');
    if (!seccion) return;
    const buscar = document.getElementById('buscarSupervisionSelectorAgencia');
    const tipoBusqueda = document.getElementById('tipoBusquedaSupervisionSelectorAgencia');
    const cantidad = document.getElementById('cantidadSupervisionSelectorAgencia');
    const resultados = document.getElementById('resultadosSupervisionSelectorAgencia');
    const coincidencias = document.getElementById('coincidenciasSupervisionSelectorAgencia');
    const estado = document.getElementById('estadoSupervisionSelectorAgencia');
    const ausentes = document.getElementById('ausentesSupervisionSelectorAgencia');
    const vaciar = document.getElementById('vaciarSupervisionSelectorAgencia');
    const agregar = document.getElementById('agregarSupervisionSelectorAgencia');
    const reintentar = document.getElementById('reintentarGuardadoSelectorAgencia');
    const escapar = valor => String(valor ?? '').replace(/[&<>"']/g, caracter => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'})[caracter]);
    const normalizar = valor => String(valor ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();
    const claveFila = fila => encodeURIComponent(JSON.stringify([String(fila.empresa ?? ''), String(fila.terminal)]));
    const disponibles = configuracion.datos
        .filter(fila => String(fila.terminal ?? '') !== '')
        .slice().sort((a, b) => String(a.nombre).localeCompare(String(b.nombre), 'es', {numeric:true}) || String(a.terminal).localeCompare(String(b.terminal)));
    const catalogo = new Map(disponibles.map(fila => [claveFila(fila), fila]));
    const seleccionadas = new Set();
    let importarSeleccionAnterior = false;
    if (Array.isArray(configuracion.seleccionGuardada)) {
        configuracion.seleccionGuardada.forEach(fila => seleccionadas.add(claveFila(fila)));
    } else {
        try {
            const guardadas = window.localStorage.getItem(configuracion.claveSupervision);
            if (guardadas) {
                const claves = JSON.parse(guardadas);
                if (Array.isArray(claves)) claves.filter(clave => typeof clave === 'string').forEach(clave => seleccionadas.add(clave));
                importarSeleccionAnterior = seleccionadas.size > 0;
            }
        } catch (_) {
            seleccionadas.clear();
        }
    }
    const coincidenciasActuales = () => {
        const consulta = normalizar(buscar.value);
        return disponibles.filter(fila => {
            const texto = tipoBusqueda.value === 'nombre' ? fila.nombre : tipoBusqueda.value === 'terminal' ? fila.terminal : `${fila.nombre} ${fila.terminal}`;
            return normalizar(texto).includes(consulta);
        });
    };
    const etiquetas = ['Terminal', 'Agencia', 'Empresa', 'Ruta'];
    const encabezados = [...etiquetas, ...encabezadosImportes, 'Acciones'];
    const tablaElemento = document.getElementById('tablaSupervisionSelectorAgencia');
    tablaElemento.innerHTML = '<thead><tr>' + etiquetas.map(etiqueta => `<th rowspan="2">${etiqueta}</th>`).join('')
        + configuracion.meses.map(mes => `<th colspan="2" class="text-center grupo-mes">${escapar(mes.nombre ?? mes.codigo)}</th>`).join('')
        + '<th rowspan="2">Acciones</th></tr><tr>' + encabezadosImportes.map(etiqueta => `<th>${escapar(etiqueta)}</th>`).join('') + '</tr></thead><tbody></tbody>';
    const columnas = [
        ...['terminal', 'nombre', 'empresa', 'ruta'].map(campo => ({data:campo, defaultContent:'', render:(valor, tipo) => tipo === 'display' ? escapar(valor) : String(valor ?? '')})),
        ...columnasImportes,
        {data:null, orderable:false, searchable:false, render:(_, tipo, fila) => tipo === 'display' ? `<button type="button" class="btn btn-sm btn-outline-danger" data-quitar-supervision="${escapar(claveFila(fila))}" aria-label="Quitar ${escapar(fila.nombre)} de supervisión">Quitar</button>` : ''},
    ];
    const tabla = window.jQuery('#tablaSupervisionSelectorAgencia').DataTable({
        data:[], columns:columnas, deferRender:true, pageLength:10, autoWidth:false,
        order:[[1, 'asc']], dom:'Bfrtip',
        buttons:[{
            extend:'excel', text:'Exportar lista a Excel', title:'Selector de agencia - Tradicional', filename:'selector_agencias_tradicional',
            exportOptions:{columns:indice => indice < columnas.length - 1, orthogonal:'export', modifier:{search:'applied', page:'all'}, format:{header:(_, indice) => encabezados[indice]}},
        }],
        language:{search:'Buscar en supervisión:', info:'Mostrando _START_ a _END_ de _TOTAL_ terminales', infoEmpty:'Sin terminales seleccionadas', emptyTable:'Selecciona terminales para preparar tu lista de supervisión.', zeroRecords:'Sin coincidencias', paginate:{next:'Siguiente', previous:'Anterior'}},
    });
    let paquetePendiente = null;
    let guardando = false;
    let errorGuardado = '';
    let guardadoPendiente = Promise.resolve(true);
    const guardarCache = () => {
        try {
            window.localStorage.setItem(configuracion.claveSupervision, JSON.stringify([...seleccionadas]));
        } catch (_) {}
    };
    const procesarGuardado = async () => {
        guardando = true;
        errorGuardado = '';
        actualizar();
        while (paquetePendiente !== null) {
            const paquete = paquetePendiente;
            paquetePendiente = null;
            const controlador = new AbortController();
            const tiempo = setTimeout(() => controlador.abort(), 20000);
            try {
                const respuesta = await fetch(configuracion.urlGuardar, {
                    method:'POST', credentials:'same-origin', signal:controlador.signal,
                    headers:{Accept:'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN':configuracion.csrfToken},
                    body:JSON.stringify({terminales:paquete}),
                });
                const datos = await respuesta.json();
                if (!respuesta.ok || respuesta.redirected || datos.guardada !== true) {
                    throw new Error(Object.values(datos.errors ?? {}).flat()[0] || datos.message || 'No se pudo guardar la selección.');
                }
            } catch (error) {
                paquetePendiente ??= paquete;
                errorGuardado = error.name === 'AbortError' ? 'El guardado tardó demasiado. Intenta nuevamente.' : error.message;
                guardando = false;
                actualizar();
                return false;
            } finally {
                clearTimeout(tiempo);
            }
        }
        guardando = false;
        actualizar();
        return true;
    };
    const guardar = () => {
        guardarCache();
        paquetePendiente = [...seleccionadas].flatMap(clave => {
            try {
                const fila = JSON.parse(decodeURIComponent(clave));
                return Array.isArray(fila) && fila.length === 2 ? [{empresa:String(fila[0]), terminal:String(fila[1])}] : [];
            } catch (_) {
                return [];
            }
        });
        if (!guardando) guardadoPendiente = procesarGuardado();
    };
    const mostrarResultados = () => {
        const filas = coincidenciasActuales();
        const desplazamiento = resultados.scrollTop;
        resultados.innerHTML = filas.slice(0, 100).map(fila => `<label class="d-flex align-items-center gap-2 p-2 border-bottom"><input type="checkbox" class="form-check-input m-0" data-supervision-clave="${escapar(claveFila(fila))}" ${seleccionadas.has(claveFila(fila)) ? 'checked' : ''}><span><strong>${escapar(fila.nombre)}</strong><small class="d-block text-muted">Terminal: ${escapar(fila.terminal)} · ${escapar(fila.empresa)} · ${escapar(fila.ruta)}</small></span></label>`).join('') || '<p class="text-muted mb-0 p-2">Sin coincidencias en esta consulta.</p>';
        resultados.scrollTop = desplazamiento;
        coincidencias.textContent = `${filas.length} coincidencias. ${filas.length > 100 ? 'Se muestran las primeras 100; escribe para precisar la búsqueda.' : 'Marca las terminales que quieres supervisar.'}`;
        agregar.disabled = !filas.some(fila => !seleccionadas.has(claveFila(fila)));
    };
    const actualizar = (persistir = false) => {
        if (persistir) guardar();
        const filas = [...seleccionadas].filter(clave => catalogo.has(clave)).map(clave => catalogo.get(clave));
        const fuera = [...seleccionadas].filter(clave => !catalogo.has(clave));
        tabla.clear();
        tabla.rows.add(filas);
        tabla.draw();
        tabla.button(0).enable(filas.length > 0);
        vaciar.disabled = seleccionadas.size === 0;
        const mensajeGuardado = errorGuardado ? `No se guardó la última selección: ${errorGuardado}` : guardando ? 'Guardando selección en el sistema…' : 'Selección guardada en el sistema.';
        estado.textContent = `${filas.length} terminales en supervisión para esta consulta.${fuera.length ? ` ${fuera.length} guardadas fuera de esta consulta; no se incluyen en el Excel.` : ''} ${mensajeGuardado}`;
        estado.className = `small mb-2${errorGuardado ? ' text-danger' : ''}`;
        reintentar.hidden = !errorGuardado;
        ausentes.innerHTML = fuera.length ? '<p class="text-muted mb-1">Selecciones fuera de esta consulta:</p>' + fuera.map(clave => {
            let etiqueta = clave;
            try {
                const datos = JSON.parse(decodeURIComponent(clave));
                if (Array.isArray(datos) && datos.length === 2) etiqueta = `${datos[1]} · ${datos[0]}`;
            } catch (_) {}
            return `<button type="button" class="btn btn-sm btn-outline-secondary me-1 mb-1" data-quitar-supervision="${escapar(clave)}">Quitar ${escapar(etiqueta)}</button>`;
        }).join('') : '';
        mostrarResultados();
    };
    buscar.addEventListener('input', mostrarResultados);
    tipoBusqueda.addEventListener('change', mostrarResultados);
    resultados.addEventListener('change', evento => {
        const casilla = evento.target;
        const clave = casilla.dataset?.supervisionClave;
        if (!catalogo.has(clave)) return;
        if (casilla.checked) seleccionadas.add(clave);
        else seleccionadas.delete(clave);
        actualizar(true);
    });
    agregar.addEventListener('click', () => {
        const limite = Number(cantidad.value);
        if (!Number.isInteger(limite) || limite < 1) {
            estado.textContent = 'Introduce una cantidad entera mayor que cero.';
            return;
        }
        coincidenciasActuales().filter(fila => !seleccionadas.has(claveFila(fila))).slice(0, limite).forEach(fila => seleccionadas.add(claveFila(fila)));
        actualizar(true);
    });
    const quitar = evento => {
        const boton = evento.target.closest('button[data-quitar-supervision]');
        if (!boton) return;
        seleccionadas.delete(boton.dataset.quitarSupervision);
        actualizar(true);
    };
    tablaElemento.addEventListener('click', quitar);
    ausentes.addEventListener('click', quitar);
    vaciar.addEventListener('click', () => {
        seleccionadas.clear();
        actualizar(true);
    });
    window.guardarSeleccionSelectorAgencia = async () => {
        if (guardando) await guardadoPendiente;
        if (paquetePendiente !== null && !guardando) guardadoPendiente = procesarGuardado();
        return guardadoPendiente;
    };
    reintentar.addEventListener('click', () => {
        window.guardarSeleccionSelectorAgencia();
    });
    actualizar();
    if (importarSeleccionAnterior) guardar();
    else guardarCache();
};

document.addEventListener('DOMContentLoaded', function () {
    const configuracion = window.selectorAgencia;
    if (!configuracion) return;
    const formulario = document.getElementById('formSelectorAgencia');
    const botonConsultar = formulario.querySelector('button[type="submit"]');
    let consultando = false;
    formulario.addEventListener('submit', async evento => {
        evento.preventDefault();
        if (consultando) return;
        consultando = true;
        botonConsultar.disabled = true;
        botonConsultar.textContent = 'Consultando…';
        window.Swal?.fire({title:'Consultando datos…', text:'Preparando las ventas y premios tradicionales.', allowOutsideClick:false, allowEscapeKey:false, showConfirmButton:false, didOpen:() => window.Swal.showLoading()});
        const guardada = await (window.guardarSeleccionSelectorAgencia?.() ?? true);
        if (!guardada) {
            consultando = false;
            botonConsultar.disabled = false;
            botonConsultar.textContent = 'Consultar';
            window.Swal?.fire({icon:'error', title:'No se pudo guardar la selección', text:'Reintenta el guardado antes de consultar para conservar tus cambios.'});
            return;
        }
        setTimeout(() => HTMLFormElement.prototype.submit.call(formulario), 50);
    });
    window.addEventListener('pageshow', () => {
        if (consultando) window.Swal?.close();
        consultando = false;
        botonConsultar.disabled = false;
        botonConsultar.textContent = 'Consultar';
    });
    const empresa = document.getElementById('empresaSelectorAgencia');
    const ruta = document.getElementById('rutaSelectorAgencia');
    empresa.addEventListener('change', () => {
        const seleccionada = ruta.value;
        const rutas = [...new Set(configuracion.rutasDisponibles.filter(fila => !empresa.value || fila.empresa === empresa.value).map(fila => fila.ruta))];
        ruta.replaceChildren(new Option('Todas las rutas', ''), ...rutas.map(nombre => new Option(nombre, nombre)));
        ruta.value = rutas.includes(seleccionada) ? seleccionada : '';
    });
    try {
        window.iniciarSelectorAgencia(configuracion);
    } catch (error) {
        const estado = document.getElementById('estadoSupervisionSelectorAgencia');
        if (estado) estado.textContent = 'No se pudo preparar la lista. Recarga la página para intentar de nuevo.';
        console.error('Error al preparar el selector de agencia:', error);
    }
});
