@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Carga de ponche por cédula</h4>
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('mantenimiento.index') }}">Mantenimiento</a></li>
                        <li class="breadcrumb-item active">Carga de ponche por cédula</li>
                    </ol>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Cargar ventas del día</h5></div>
                    <div class="card-body">
                        <form id="formCargarPoncheVentas" method="POST" action="{{ route('mantenimiento.ponche-por-cedula-ventas.store') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="row g-3 align-items-end">
                                <div class="col-md-2"><label for="fecha_carga" class="form-label">Fecha</label><input type="date" id="fecha_carga" name="fecha" class="form-control" value="{{ old('fecha', today()->toDateString()) }}" required></div>
                                <div class="col-md-2"><label for="tradicional" class="form-label">Tradicional</label><input type="file" id="tradicional" name="tradicional" class="form-control" accept=".xlsx,.csv,.txt" required></div>
                                <div class="col-md-2"><label for="no_tradicional" class="form-label">No Tradicional</label><input type="file" id="no_tradicional" name="no_tradicional" class="form-control" accept=".xlsx,.csv,.txt" required></div>
                                <div class="col-md-2"><label for="recargas" class="form-label">Recargas</label><input type="file" id="recargas" name="recargas" class="form-control" accept=".xlsx" required></div>
                                <div class="col-md-2"><button type="submit" class="btn btn-success w-100">Cargar archivos</button></div>
                            </div>
                        </form>
                        <small class="text-muted">La nueva carga reemplaza las ventas de esta fecha solo en Ponche por Cédula.</small>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Carga por lote</h5></div>
                    <div class="card-body">
                        <p class="text-muted mb-2">Nombra cada archivo con su fecha y categoría. Luego selecciona todos los documentos juntos.</p>
                        <div class="small mb-3">
                            <div><code>2026-10-12_tradicional.csv</code></div>
                            <div><code>2026-10-12_no_tradicional.csv</code></div>
                            <div><code>2026-10-12_recargas.xlsx</code></div>
                        </div>
                        <form id="formCargarPonchePool" method="POST" action="{{ route('mantenimiento.ponche-por-cedula-ventas.pool') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="row g-3 align-items-end">
                                <div class="col-md-8">
                                    <label for="archivosPonchePool" class="form-label">Documentos del lote</label>
                                    <input type="file" id="archivosPonchePool" name="archivos[]" class="form-control" accept=".xlsx,.csv,.txt" multiple required>
                                </div>
                                <div class="col-md-4"><button type="submit" id="cargarPonchePool" class="btn btn-success w-100" disabled>Cargar lote</button></div>
                            </div>
                        </form>
                        <div id="resumenPonchePool" class="mt-3"></div>
                        <small class="text-muted d-block mt-2">Puedes seleccionar hasta 31 fechas. Se envían solo los tres documentos de un día; al terminar de procesarlo, comienza el siguiente. Si uno falla, los días anteriores quedan cargados.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        const mensajeCargaExitosa = @json(session('success'));
        if (mensajeCargaExitosa) {
            Swal.fire({
                icon: 'success',
                title: 'Archivos cargados',
                text: mensajeCargaExitosa,
                confirmButtonText: 'Aceptar'
            });
        }

        document.getElementById('formCargarPoncheVentas').addEventListener('submit', function() {
            this.querySelector('button[type="submit"]').disabled = true;
            Swal.fire({
                title: 'Cargando datos...',
                text: 'Espere mientras se insertan las ventas.',
                width: '20rem',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });
        });

        const archivosPonchePool = document.getElementById('archivosPonchePool');
        const resumenPonchePool = document.getElementById('resumenPonchePool');
        const cargarPonchePool = document.getElementById('cargarPonchePool');
        const categoriasPonchePool = ['tradicional', 'no_tradicional', 'recargas'];

        archivosPonchePool.addEventListener('change', function() {
            resumenPonchePool.replaceChildren();
            cargarPonchePool.disabled = true;
            const archivos = [...this.files];
            if (!archivos.length) return;

            const grupos = new Map();
            const errores = [];
            if (archivos.length > 93) errores.push('Selecciona hasta 93 documentos (31 fechas).');
            archivos.forEach(archivo => {
                if (archivo.size === 0 || archivo.size > 50 * 1024 * 1024) {
                    errores.push(`${archivo.name} debe medir entre 1 byte y 50 MB.`);
                }
                const partes = archivo.name.match(/^(\d{4}-\d{2}-\d{2})_(tradicional|no_tradicional|recargas)\.(csv|txt|xlsx)$/i);
                if (!partes) {
                    errores.push(`Nombre inválido: ${archivo.name}`);
                    return;
                }
                const [, fecha, categoriaOriginal, extensionOriginal] = partes;
                const categoria = categoriaOriginal.toLowerCase();
                const [anio, mes, dia] = fecha.split('-').map(Number);
                if (new Date(Date.UTC(anio, mes - 1, dia)).toISOString().slice(0, 10) !== fecha) {
                    errores.push(`Fecha inválida: ${archivo.name}`);
                }
                if (categoria === 'recargas' && extensionOriginal.toLowerCase() !== 'xlsx') {
                    errores.push(`Recargas debe ser .xlsx: ${archivo.name}`);
                }
                if (!grupos.has(fecha)) grupos.set(fecha, new Map());
                if (grupos.get(fecha).has(categoria)) errores.push(`Archivo duplicado para ${fecha} / ${categoria}.`);
                grupos.get(fecha).set(categoria, archivo.name);
            });
            if (grupos.size > 31) errores.push('Selecciona hasta 31 fechas.');

            const tabla = document.createElement('table');
            tabla.className = 'table table-bordered table-sm align-middle';
            const cabecera = tabla.createTHead().insertRow();
            ['Fecha', 'Tradicional', 'No Tradicional', 'Recargas'].forEach(texto => {
                const celda = document.createElement('th');
                celda.textContent = texto;
                cabecera.appendChild(celda);
            });
            const cuerpo = tabla.createTBody();
            [...grupos.keys()].sort().forEach(fecha => {
                const fila = cuerpo.insertRow();
                fila.insertCell().textContent = fecha;
                categoriasPonchePool.forEach(categoria => {
                    const archivo = grupos.get(fecha).get(categoria);
                    fila.insertCell().textContent = archivo || 'Falta archivo';
                    if (!archivo) errores.push(`A ${fecha} le falta ${categoria}.`);
                });
            });
            const contenedor = document.createElement('div');
            contenedor.className = 'table-responsive';
            contenedor.style.maxHeight = '16rem';
            contenedor.style.overflowY = 'auto';
            contenedor.appendChild(tabla);
            resumenPonchePool.appendChild(contenedor);
            errores.forEach(error => {
                const aviso = document.createElement('div');
                aviso.className = 'text-danger small';
                aviso.textContent = error;
                resumenPonchePool.appendChild(aviso);
            });
            cargarPonchePool.disabled = errores.length > 0;
        });

        document.getElementById('formCargarPonchePool').addEventListener('submit', async function(event) {
            event.preventDefault();
            if (cargarPonchePool.disabled) return;
            const filas = [...resumenPonchePool.querySelectorAll('tbody tr')];
            const totalFechas = filas.length;
            const fechas = filas.map(fila => fila.cells[0].textContent);
            const resumenConfirmacion = document.createElement('div');
            resumenConfirmacion.className = 'text-start';
            const cantidad = document.createElement('p');
            cantidad.className = 'mb-1 fw-semibold';
            cantidad.textContent = `${totalFechas} ${totalFechas === 1 ? 'día' : 'días'} · ${archivosPonchePool.files.length} documentos`;
            resumenConfirmacion.appendChild(cantidad);
            const rango = document.createElement('p');
            rango.className = 'text-muted small mb-2';
            rango.textContent = totalFechas === 1 ? fechas[0] : `Desde ${fechas[0]} hasta ${fechas[totalFechas - 1]}`;
            resumenConfirmacion.appendChild(rango);
            const detalle = document.createElement('details');
            detalle.className = 'border rounded p-2 small';
            const tituloDetalle = document.createElement('summary');
            tituloDetalle.className = 'fw-semibold';
            tituloDetalle.textContent = 'Ver archivos por día';
            detalle.appendChild(tituloDetalle);
            const listaDias = document.createElement('div');
            listaDias.className = 'mt-2';
            listaDias.style.maxHeight = '14rem';
            listaDias.style.overflowY = 'auto';
            filas.forEach(fila => {
                const dia = document.createElement('div');
                dia.className = 'border-top py-2';
                const fecha = document.createElement('strong');
                fecha.textContent = fila.cells[0].textContent;
                dia.appendChild(fecha);
                ['Tradicional', 'No Tradicional', 'Recargas'].forEach((categoria, indice) => {
                    const archivo = document.createElement('div');
                    archivo.className = 'text-break';
                    archivo.textContent = `${categoria}: ${fila.cells[indice + 1].textContent}`;
                    dia.appendChild(archivo);
                });
                listaDias.appendChild(dia);
            });
            detalle.appendChild(listaDias);
            resumenConfirmacion.appendChild(detalle);
            const confirmacion = await Swal.fire({
                title: 'Confirmar carga',
                html: resumenConfirmacion,
                width: '32rem',
                showCancelButton: true,
                confirmButtonText: 'Cargar lote',
                cancelButtonText: 'Revisar'
            });
            if (!confirmacion.isConfirmed) return;

            cargarPonchePool.disabled = true;
            const archivos = [...archivosPonchePool.files];
            const totalBytes = archivos.reduce((total, archivo) => total + archivo.size, 0);
            const tamanoFragmento = 1024 * 1024;
            const archivosPorFecha = fechas.map(fecha => categoriasPonchePool.map(categoria =>
                archivos.find(archivo => archivo.name.toLowerCase().startsWith(`${fecha}_${categoria}.`))
            ));
            const csrfToken = this.querySelector('[name="_token"]').value;
            const fragmentoUrl = @json(route('mantenimiento.ponche-por-cedula-ventas.fragmento', [], false));
            const finalizarUrl = @json(route('mantenimiento.ponche-por-cedula-ventas.finalizar', [], false));
            const progresoBaseUrl = @json(route('mantenimiento.ponche-por-cedula-ventas.progreso', ['token' => '__TOKEN__'], false));
            let token;
            let indiceDia = 0;
            let consultaProgreso;
            let procesandoDia = false;
            Swal.fire({
                title: 'Cargando lote...',
                html: `<div class="text-start small fw-semibold mb-1">1. Subida de documentos</div>
                    <div id="estadoSubidaPonche" class="small text-muted mb-2">Preparando archivos...</div>
                    <div class="progress" role="progressbar" aria-label="Subida de documentos" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                        <div id="barraSubidaPonche" class="progress-bar" style="width: 0%">0%</div>
                    </div>
                    <div class="text-start small fw-semibold mt-3 mb-1">2. Procesamiento por día</div>
                    <div id="estadoProcesamientoPonche" class="small text-muted mb-2">Esperando los archivos...</div>
                    <div class="progress" role="progressbar" aria-label="Procesamiento de ventas" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                        <div id="barraProcesamientoPonche" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%">0%</div>
                    </div>
                    <div id="conteoCargaPonche" class="small text-muted mt-2">0 de ${totalFechas} días completados</div>`,
                width: '30rem',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    consultaProgreso = setInterval(async () => {
                        if (!procesandoDia) return;
                        try {
                            const respuesta = await fetch(progresoBaseUrl.replace('__TOKEN__', token), {credentials: 'same-origin', cache: 'no-store'});
                            if (!respuesta.ok) return;
                            const progreso = await respuesta.json();
                            const barra = document.getElementById('barraProcesamientoPonche');
                            if (!barra) return;
                            const porcentajeReal = Math.round((indiceDia * 3 + progreso.pasos_completados) / (totalFechas * 3) * 100);
                            const porcentaje = progreso.estado === 'completo' ? Math.round((indiceDia + 1) / totalFechas * 100) : Math.min(porcentajeReal, 99);
                            barra.style.width = `${porcentaje}%`;
                            barra.textContent = `${porcentaje}%`;
                            barra.parentElement.setAttribute('aria-valuenow', porcentaje);
                            document.getElementById('conteoCargaPonche').textContent = `${indiceDia} de ${totalFechas} días completados`;
                            const categoria = {
                                tradicional: 'Tradicional',
                                no_tradicional: 'No Tradicional',
                                recargas: 'Recargas'
                            }[progreso.categoria];
                            document.getElementById('estadoProcesamientoPonche').textContent = progreso.estado === 'completo'
                                ? `Día ${indiceDia + 1} de ${totalFechas} · ${fechas[indiceDia]} completado.`
                                : progreso.estado === 'error'
                                    ? 'No se pudo completar el lote.'
                                    : progreso.fecha ? `Día ${indiceDia + 1} de ${totalFechas} · ${progreso.fecha} · ${categoria}` : 'Confirmando el día...';
                        } catch (error) {
                            return;
                        }
                    }, 800);
                }
            });

            function actualizarSubida(bytesEnviados) {
                const porcentaje = Math.min(100, Math.round(bytesEnviados / totalBytes * 100));
                const barra = document.getElementById('barraSubidaPonche');
                if (!barra) return;
                barra.style.width = `${porcentaje}%`;
                barra.textContent = `${porcentaje}%`;
                barra.parentElement.setAttribute('aria-valuenow', porcentaje);
                document.getElementById('estadoSubidaPonche').textContent = `Día ${indiceDia + 1} de ${totalFechas} · ${fechas[indiceDia]} · subiendo documentos: ${porcentaje}%`;
            }

            function enviarFragmento(archivo, documento, parte, bytesCompletados) {
                return new Promise((resolve, reject) => {
                    const fragmento = archivo.slice(parte * tamanoFragmento, (parte + 1) * tamanoFragmento);
                    const datos = new FormData();
                    datos.append('_token', csrfToken);
                    datos.append('token', token);
                    datos.append('indice', documento.indice);
                    datos.append('parte', parte);
                    datos.append('partes', documento.partes);
                    datos.append('nombre', documento.nombre);
                    datos.append('tamano', documento.tamano);
                    datos.append('fragmento', fragmento, `${documento.indice}-${parte}.bin`);

                    const solicitud = new XMLHttpRequest();
                    solicitud.open('POST', fragmentoUrl);
                    solicitud.timeout = 60000;
                    solicitud.setRequestHeader('Accept', 'application/json');
                    solicitud.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    solicitud.upload.addEventListener('progress', evento => {
                        if (evento.lengthComputable) actualizarSubida(bytesCompletados + Math.min(evento.loaded, fragmento.size));
                    });
                    solicitud.addEventListener('load', () => {
                        if (solicitud.status >= 200 && solicitud.status < 300) {
                            actualizarSubida(bytesCompletados + fragmento.size);
                            resolve();
                            return;
                        }
                        let respuesta = {};
                        try { respuesta = JSON.parse(solicitud.responseText || '{}'); } catch (error) { respuesta = {}; }
                        const error = new Error(Object.values(respuesta.errors || {}).flat().join('\n') || respuesta.message || 'No se pudo subir un fragmento.');
                        error.reintentable = solicitud.status >= 500;
                        reject(error);
                    });
                    solicitud.addEventListener('error', () => {
                        const error = new Error('Se interrumpió la conexión durante la subida.');
                        error.reintentable = true;
                        reject(error);
                    });
                    solicitud.addEventListener('timeout', () => {
                        const error = new Error('La subida tardó demasiado en responder.');
                        error.reintentable = true;
                        reject(error);
                    });
                    solicitud.send(datos);
                });
            }

            async function subirFragmento(archivo, documento, parte, bytesCompletados) {
                for (let intento = 1; intento <= 3; intento++) {
                    try {
                        await enviarFragmento(archivo, documento, parte, bytesCompletados);
                        return;
                    } catch (error) {
                        if (!error.reintentable || intento === 3) {
                            throw new Error(`${documento.nombre} · fragmento ${parte + 1} de ${documento.partes}: ${error.message}`);
                        }
                        document.getElementById('estadoSubidaPonche').textContent = `Reintentando ${documento.nombre} · fragmento ${parte + 1} de ${documento.partes} (${intento + 1}/3)...`;
                        await new Promise(resolve => setTimeout(resolve, intento * 1000));
                    }
                }
            }

            try {
                let bytesCompletados = 0;
                for (indiceDia = 0; indiceDia < totalFechas; indiceDia++) {
                    token = crypto.randomUUID();
                    const archivosDia = archivosPorFecha[indiceDia];
                    const documentos = archivosDia.map((archivo, indice) => ({
                        indice,
                        nombre: archivo.name,
                        partes: Math.ceil(archivo.size / tamanoFragmento),
                        tamano: archivo.size
                    }));
                    document.getElementById('estadoProcesamientoPonche').textContent = `Esperando los archivos de ${fechas[indiceDia]}...`;
                    for (const documento of documentos) {
                        const archivo = archivosDia[documento.indice];
                        for (let parte = 0; parte < documento.partes; parte++) {
                            await subirFragmento(archivo, documento, parte, bytesCompletados);
                            bytesCompletados += Math.min(tamanoFragmento, archivo.size - parte * tamanoFragmento);
                        }
                    }
                    document.getElementById('estadoSubidaPonche').textContent = `Documentos de ${fechas[indiceDia]} enviados.`;
                    document.getElementById('estadoProcesamientoPonche').textContent = `Día ${indiceDia + 1} de ${totalFechas} · ${fechas[indiceDia]} · procesando Tradicional...`;
                    procesandoDia = true;
                    const respuesta = await fetch(finalizarUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken},
                        body: JSON.stringify({token, documentos})
                    });
                    const resultado = await respuesta.json();
                    procesandoDia = false;
                    if (!respuesta.ok) {
                        throw new Error(Object.values(resultado.errors || {}).flat().join('\n') || resultado.message || 'No se pudo procesar el día.');
                    }
                    document.getElementById('conteoCargaPonche').textContent = `${indiceDia + 1} de ${totalFechas} días completados`;
                    document.getElementById('estadoProcesamientoPonche').textContent = `${fechas[indiceDia]} completado.`;
                    const porcentaje = Math.round((indiceDia + 1) / totalFechas * 100);
                    const barra = document.getElementById('barraProcesamientoPonche');
                    barra.style.width = `${porcentaje}%`;
                    barra.textContent = `${porcentaje}%`;
                    barra.parentElement.setAttribute('aria-valuenow', porcentaje);
                }

                this.reset();
                resumenPonchePool.replaceChildren();
                Swal.fire('Carga completada', `${totalFechas} fechas cargadas correctamente para Ponche por Cédula.`, 'success');
            } catch (error) {
                cargarPonchePool.disabled = false;
                const detalle = indiceDia > 0 ? `${indiceDia} ${indiceDia === 1 ? 'día quedó cargado' : 'días quedaron cargados'}. Falló ${fechas[indiceDia]}. ` : '';
                Swal.fire('No se pudo cargar el lote', detalle + (error.message || 'Revisa los archivos e inténtalo otra vez.'), 'error');
            } finally {
                clearInterval(consultaProgreso);
            }
        });
    </script>
@endsection
