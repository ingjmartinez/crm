@extends('app')

@section('content')
    <link href="{{ asset('libs/choices.js/public/assets/styles/choices.min.css') }}" rel="stylesheet">
    <style>
        .filtro-ruta-centro-costo .choices {
            margin-bottom: 0;
        }

        .filtro-ruta-centro-costo .choices__inner {
            background-color: var(--vz-input-bg, #fff);
            border-color: var(--vz-input-border, #ced4da);
            border-radius: .2rem;
            font-size: .7109375rem;
            min-height: 31px;
        }

        .filtro-ruta-centro-costo .choices[data-type*='select-one'] .choices__inner {
            padding: .25rem 2rem .25rem .5rem;
        }

        .filtro-ruta-centro-costo .choices__list--single {
            align-items: center;
            display: flex;
            line-height: 1.5;
            min-height: 21px;
            padding: 0;
        }

        .filtro-ruta-centro-costo .choices__list--dropdown {
            z-index: 1055;
        }

        .filtro-ruta-centro-costo .choices__list--dropdown .choices__list {
            margin-left: 0 !important;
            overflow-x: hidden;
            padding-left: 0 !important;
        }

        .filtro-ruta-centro-costo .choices__list--dropdown .choices__item--selectable {
            box-sizing: border-box;
            left: 0;
            margin-left: 0 !important;
            padding: 9px 16px 9px 24px !important;
            text-indent: 0 !important;
            transform: none;
            width: 100%;
            white-space: normal;
            word-break: normal;
        }
    </style>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">

                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Centro de Costo</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('contabilidad.index') }}">Contabilidad</a></li>
                                    <li class="breadcrumb-item active">Centro de Costo</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Centros de Costo</h5>
                                    <div class="d-flex gap-2">
                                        <a href="{{ route('contabilidad.validador-agencia') }}" class="btn btn-success">
                                            <i class="ri-file-search-line align-bottom me-1"></i>
                                            Validador de Agencia
                                        </a>
                                        <button type="button" class="btn btn-primary" id="btnConsultarCentros">
                                            Consultar data (168)
                                        </button>
                                        <button type="button" class="btn btn-soft-secondary" id="btnConfigurarCentros">
                                            Configurar
                                        </button>
                                        <button type="button" class="btn btn-info" id="btnSincronizarCentros">
                                            Sincronizar
                                        </button>
                                        <button type="button" class="btn btn-danger" id="btnEliminarCentrosEmpresa">
                                            Eliminar data empresa
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row g-2 align-items-end mb-3">
                                    <div class="col-12 col-md-4 col-lg-3">
                                        <label class="form-label">Empresa</label>
                                        <select id="filtroEmpresaCentroCosto" class="form-select form-select-sm">
                                            <option value="168" selected>168 - Grupo Joselito</option>
                                            <option value="169">169 - Negosur</option>
                                            <option value="todas">Todas</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-4 col-lg-3">
                                        <label class="form-label">Estado</label>
                                        <select id="filtroEstadoCentroCosto" class="form-select form-select-sm">
                                            <option value="">Todos</option>
                                            <option value="activo">Activo</option>
                                            <option value="inactivo">Inactivo</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-4 col-lg-3 filtro-ruta-centro-costo">
                                        <label class="form-label">Ruta</label>
                                        <select id="filtroRutaCentroCosto" class="form-select form-select-sm">
                                            <option value="">Todas</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <table id="tableCentrosCosto"
                                        class="table table-bordered nowrap table-striped align-middle w-100"
                                        style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>CompanyID</th>
                                                <th>Id Centro Costo</th>
                                                <th>Cuenta contable de la ruta</th>
                                                <th>Terminal</th>
                                                <th>Nombre Agencia</th>
                                                <th>Estado</th>
                                                <th>Ruta Empresa</th>
                                                <th>Cuenta Ruta</th>
                                                <th>Centro de costo ruta</th>
                                                <th>Id SubGrupo</th>
                                                <th>Ciudad</th>
                                                <th>Empresa</th>
                                                <th>Atrib 75</th>
                                                <th>Atrib 103</th>
                                                <th>Fecha Grabado</th>
                                                <th>Modificado por</th>
                                                <th>Fecha Modificado</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <footer class="footer">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <script>document.write(new Date().getFullYear())</script> Velzon.
                    </div>
                    <div class="col-sm-6">
                        <div class="text-sm-end d-none d-sm-block">
                            Design & Develop by Themesbrand
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <div class="modal fade" id="modalConfigurarCentros" tabindex="-1" aria-labelledby="modalConfigurarCentrosLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalConfigurarCentrosLabel">Configurar centros de costo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-12 col-md-5">
                            <label class="form-label">Buscar ruta o terminal</label>
                            <input type="text" class="form-control form-control-sm" id="buscarConfigIdViejo" placeholder="Terminal, centro, agencia o ruta">
                        </div>
                        <div class="col-12 col-md-auto">
                            <button type="button" class="btn btn-soft-warning btn-sm" id="btnVerOcultosCentros">
                                Ver ocultos
                            </button>
                        </div>
                    </div>

                    <div id="panelCuentaCentro" class="border rounded p-3 mb-3 d-none">
                        <h6 id="tituloCuentaCentro"></h6>
                        <p id="cuentaActualCentro" class="text-muted mb-2"></p>
                        <label for="buscarCuentaCentro" class="form-label">Buscar cuenta por codigo o descripcion</label>
                        <input id="buscarCuentaCentro" type="search" class="form-control mb-2" placeholder="Ej.: San Cristobal o codigo contable" maxlength="150">
                        <label for="seleccionarCuentaCentro" class="form-label">Cuentas de la empresa de la ruta</label>
                        <select id="seleccionarCuentaCentro" class="form-select mb-2" size="5"></select>
                        <p id="estadoBusquedaCuentaCentro" class="text-muted small" role="status" aria-live="polite"></p>
                        <div class="d-flex gap-2">
                            <button id="guardarCuentaCentro" type="button" class="btn btn-primary btn-sm" disabled>Guardar asociacion</button>
                            <button id="quitarCuentaCentro" type="button" class="btn btn-outline-danger btn-sm">Quitar asociacion</button>
                            <button id="cerrarCuentaCentro" type="button" class="btn btn-light btn-sm">Cerrar</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Empresa</th>
                                    <th>Ruta Empresa</th>
                                    <th>Centro de costo ruta</th>
                                    <th>Centros de costo y terminales</th>
                                    <th>Cuenta contable de la ruta</th>
                                    <th class="text-center">Ocultar todos</th>
                                </tr>
                            </thead>
                            <tbody id="tablaConfigCentrosCosto"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarConfigCentros">Guardar cambios</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="{{ asset('libs/choices.js/public/assets/scripts/choices.min.js') }}"></script>
    <script>
        const buscarCuentasCentroUrl = @json(route('contabilidad.centros-costo.cuentas', ['centro' => '__CENTRO__']));
        const guardarCuentaCentroUrl = @json(route('contabilidad.centros-costo.cuenta', ['centro' => '__CENTRO__']));
        let centroCuentaSeleccionado = null;
        let busquedaCuentaVersion = 0;
        let busquedaCuentaTimer = null;
        let guardandoCuentaCentro = false;
        const csrfToken = '{{ csrf_token() }}';
        let centrosCostoTable = null;
        let centrosCostoData = [];
        let mostrarSoloOcultosConfig = false;
        let progresoSincronizacionTimer = null;
        let resizeCentrosCostoTimer = null;
        let resizeCentrosCostoBound = false;
        let filtroRutaCentroCostoChoices = null;
        const filtroEmpresaCentroCostoEl = document.getElementById('filtroEmpresaCentroCosto');
        const filtroRutaCentroCostoEl = document.getElementById('filtroRutaCentroCosto');

        if (filtroRutaCentroCostoEl && typeof Choices !== 'undefined') {
            filtroRutaCentroCostoChoices = new Choices(filtroRutaCentroCostoEl, {
                allowHTML: false,
                itemSelectText: '',
                noChoicesText: 'No hay rutas disponibles',
                noResultsText: 'No se encontraron rutas',
                searchEnabled: true,
                searchFloor: 1,
                searchPlaceholderValue: 'Buscar ruta por nombre...',
                shouldSort: false,
            });
        }

        async function parsearRespuestaJson(response, contextoError) {
            const contentType = (response.headers.get('content-type') || '').toLowerCase();
            const cuerpo = await response.text();
            let payload = null;

            if (cuerpo) {
                try {
                    payload = JSON.parse(cuerpo);
                } catch (_errorParse) {
                    payload = null;
                }
            }

            if (!response.ok) {
                const mensajeServidor = payload?.message || payload?.error || '';
                const mensajeNoJson = !contentType.includes('application/json')
                    ? 'El servidor devolvio HTML/no JSON. Revisa storage/logs/laravel.log.'
                    : '';
                const detalle = [mensajeServidor, mensajeNoJson].filter(Boolean).join(' | ');
                throw new Error(contextoError + ' (HTTP ' + response.status + ')' + (detalle ? ': ' + detalle : ''));
            }

            if (!payload) {
                throw new Error(contextoError + ': Respuesta no valida en formato JSON.');
            }

            return payload;
        }

        document.getElementById('btnConsultarCentros').addEventListener('click', () => cargarCentrosCosto(true));
        document.getElementById('btnConfigurarCentros').addEventListener('click', abrirConfiguracionCentros);
        document.getElementById('btnGuardarConfigCentros').addEventListener('click', guardarConfiguracionCentros);
        document.getElementById('buscarConfigIdViejo').addEventListener('input', renderConfigCentros);
        document.getElementById('btnVerOcultosCentros').addEventListener('click', alternarOcultosConfig);
        document.getElementById('btnSincronizarCentros').addEventListener('click', sincronizarCentros);
        document.getElementById('btnEliminarCentrosEmpresa').addEventListener('click', eliminarDataCentrosPorEmpresa);
        document.getElementById('filtroEstadoCentroCosto').addEventListener('change', aplicarFiltroEstadoCentroCosto);
        filtroRutaCentroCostoEl.addEventListener('change', aplicarFiltroEstadoCentroCosto);
        document.getElementById('filtroEmpresaCentroCosto').addEventListener('change', manejarCambioEmpresaCentroCosto);
        document.getElementById('tablaConfigCentrosCosto').addEventListener('change', manejarCambioOcultarCentro);
        document.getElementById('tablaConfigCentrosCosto').addEventListener('click', event => {
            const boton = event.target.closest('.asociar-cuenta-centro');
            if (boton && !guardandoCuentaCentro) abrirCuentaCentro(Number(boton.dataset.id));
        });
        document.getElementById('buscarCuentaCentro').addEventListener('input', () => {
            clearTimeout(busquedaCuentaTimer);
            busquedaCuentaVersion++;
            document.getElementById('seleccionarCuentaCentro').replaceChildren();
            document.getElementById('guardarCuentaCentro').disabled = true;
            busquedaCuentaTimer = setTimeout(buscarCuentasCentro, 300);
        });
        document.getElementById('seleccionarCuentaCentro').addEventListener('change', event => {
            document.getElementById('guardarCuentaCentro').disabled = !event.target.value;
        });
        document.getElementById('guardarCuentaCentro').addEventListener('click', () => guardarCuentaCentro(false));
        document.getElementById('quitarCuentaCentro').addEventListener('click', () => guardarCuentaCentro(true));
        document.getElementById('cerrarCuentaCentro').addEventListener('click', cerrarCuentaCentro);
        document.getElementById('modalConfigurarCentros').addEventListener('hidden.bs.modal', cerrarCuentaCentro);
        actualizarEtiquetaConsultar();

        function esOculto(valor) {
            if (valor === true || valor === 1) return true;
            if (typeof valor === 'string') {
                const normalizado = valor.trim().toLowerCase();
                return normalizado === '1' || normalizado === 'true' || normalizado === 'si' || normalizado === 'yes' || normalizado === 'on';
            }
            return false;
        }

        function cargarCentrosCosto(mostrarAlerta = true) {
            cerrarCuentaCentro();
            const boton = document.getElementById('btnConsultarCentros');
            const textoOriginal = boton.innerText;
            const empresa = getEmpresaCentroCostoSeleccionada();
            boton.disabled = true;
            boton.innerText = 'Consultando...';
            destruirTablaCentrosCosto();
            centrosCostoData = [];

            if (mostrarAlerta) {
                mostrarAlertaCarga('Consultando data', 'Leyendo centros de costo desde la base de datos local...');
            }

            return fetch('/api-centros-costo?empresa=' + encodeURIComponent(empresa), {
                headers: {
                    'Accept': 'application/json',
                },
            })
                .then(response => parsearRespuestaJson(response, 'Error al consultar centros de costo'))
                .then(data => {
                    const tableBody = document.querySelector('#tableCentrosCosto tbody');
                    tableBody.innerHTML = '';

                    centrosCostoData = Array.isArray(data) ? data : [];
                    const dataFiltrada = centrosCostoData.filter(item => {
                        return String(item.IdViejo ?? '').trim() !== '' && !esOculto(item.Ocultar);
                    });
                    cargarOpcionesRutaCentroCosto(dataFiltrada);

                    dataFiltrada.forEach(item => {
                        const inactivo = Boolean(item.Inactivo);
                        const estadoTexto = inactivo ? 'Inactivo' : 'Activo';
                        const estadoBadge = inactivo
                            ? '<span class="badge bg-danger">Inactivo</span>'
                            : '<span class="badge bg-success">Activo</span>';

                        const row = document.createElement('tr');
                        row.dataset.centroId = item.id;
                        row.innerHTML = `
                            <td>${item.CompanyID ?? ''}</td>
                            <td>${item.IdCentroCosto ?? ''}</td>
                            <td>${celdaCuentaCentro(item)}</td>
                            <td>${item.IdViejo ?? ''}</td>
                            <td>${item.Descripcion ?? ''}</td>
                            <td data-search="${estadoTexto}">${estadoBadge}</td>
                            <td>${item.IdGrupo ?? ''}</td>
                            <td>${escaparHtmlCentro(item.Atrib8 ?? '')}</td>
                            <td>${(item.CentrosCostoRuta ?? []).join(', ') || '—'}</td>
                            <td>${item.IdSubGrupo ?? ''}</td>
                            <td>${item.IdDivision ?? ''}</td>
                            <td>${item.IdSociedad ?? ''}</td>
                            <td>${item.Atrib75 ?? ''}</td>
                            <td>${item.Atrib103 ?? ''}</td>
                            <td>${item.FechaGrabado ?? ''}</td>
                            <td>${item.ModificadoPor ?? ''}</td>
                            <td>${item.FechaModificado ?? ''}</td>
                        `;
                        tableBody.appendChild(row);
                    });

                    if ($.fn.DataTable.isDataTable('#tableCentrosCosto')) {
                        $('#tableCentrosCosto').DataTable().destroy();
                    }

                    centrosCostoTable = $('#tableCentrosCosto').DataTable({
                        responsive: false,
                        scrollX: true,
                        scrollCollapse: true,
                        autoWidth: false,
                        deferRender: true,
                        pageLength: 10,
                        dom: 'Bfrtip',
                        buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
                        columnDefs: [
                            { targets: '_all', className: 'text-nowrap align-middle' }
                        ]
                    });

                    enlazarEventosTablaCentrosCosto();
                    ajustarLayoutTablaCentrosCosto();
                    aplicarFiltroEstadoCentroCosto();

                    if (mostrarAlerta) {
                        mostrarAlertaExito('Consulta completada', 'Registros cargados: ' + dataFiltrada.length.toLocaleString('es-DO'));
                    }
                })
                .catch(error => {
                    console.error('Error cargando centros de costo:', error);
                    mostrarAlertaError('No se pudieron cargar los centros de costo.');
                })
                .finally(() => {
                    boton.disabled = false;
                    boton.innerText = textoOriginal;
                });
        }

        function aplicarFiltroEstadoCentroCosto() {
            if (!centrosCostoTable) return;

            const estado = document.getElementById('filtroEstadoCentroCosto').value;
            const ruta = String(filtroRutaCentroCostoEl.value || '').trim();
            const busquedaEstado = estado === 'activo' ? '^Activo$' : (estado === 'inactivo' ? '^Inactivo$' : '');
            const busquedaRuta = ruta === '' ? '' : '^' + $.fn.dataTable.util.escapeRegex(ruta) + '$';
            centrosCostoTable.column(5).search(busquedaEstado, true, false);
            centrosCostoTable.column(6).search(busquedaRuta, true, false);
            centrosCostoTable.draw();
            ajustarLayoutTablaCentrosCosto();
        }

        function cargarOpcionesRutaCentroCosto(registros) {
            const rutaSeleccionada = String(filtroRutaCentroCostoEl.value || '').trim();
            const rutas = [...new Set(registros
                .map(item => String(item.IdGrupo ?? '').trim())
                .filter(ruta => ruta !== ''))]
                .sort((a, b) => a.localeCompare(b, 'es', { numeric: true, sensitivity: 'base' }));
            actualizarOpcionesRutaCentroCosto(rutas, rutas.includes(rutaSeleccionada) ? rutaSeleccionada : '');
        }

        function manejarCambioEmpresaCentroCosto() {
            centrosCostoData = [];
            destruirTablaCentrosCosto();
            cerrarCuentaCentro();
            actualizarOpcionesRutaCentroCosto([], '');
            actualizarEtiquetaConsultar();
            aplicarFiltroEstadoCentroCosto();
        }

        function actualizarOpcionesRutaCentroCosto(rutas, rutaSeleccionada) {
            const opciones = [
                { value: '', label: 'Todas', selected: rutaSeleccionada === '' },
                ...rutas.map(ruta => ({ value: ruta, label: ruta, selected: ruta === rutaSeleccionada })),
            ];

            if (filtroRutaCentroCostoChoices) {
                filtroRutaCentroCostoChoices.setChoices(opciones, 'value', 'label', true);
                return;
            }

            filtroRutaCentroCostoEl.replaceChildren(...opciones.map(opcion => new Option(
                opcion.label,
                opcion.value,
                opcion.selected,
                opcion.selected,
            )));
        }

        function destruirTablaCentrosCosto() {
            if ($.fn.DataTable.isDataTable('#tableCentrosCosto')) {
                $('#tableCentrosCosto').DataTable().clear().destroy();
            }

            centrosCostoTable = null;
            const tableBody = document.querySelector('#tableCentrosCosto tbody');
            if (tableBody) {
                tableBody.innerHTML = '';
            }
        }

        function ajustarLayoutTablaCentrosCosto() {
            if (!centrosCostoTable) return;

            centrosCostoTable.columns.adjust();
        }

        function enlazarEventosTablaCentrosCosto() {
            const $tabla = $('#tableCentrosCosto');

            $tabla.off('draw.dt.centroCosto').on('draw.dt.centroCosto', function () {
                ajustarLayoutTablaCentrosCosto();
            });

            if (!resizeCentrosCostoBound) {
                window.addEventListener('resize', function () {
                    if (resizeCentrosCostoTimer) {
                        clearTimeout(resizeCentrosCostoTimer);
                    }

                    resizeCentrosCostoTimer = setTimeout(() => {
                        ajustarLayoutTablaCentrosCosto();
                    }, 120);
                });
                resizeCentrosCostoBound = true;
            }
        }

        async function abrirConfiguracionCentros() {
            if (!centrosCostoData.length) {
                await cargarCentrosCosto(false);
            }

            cerrarCuentaCentro();
            renderConfigCentros();

            const modalElement = document.getElementById('modalConfigurarCentros');
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            modal.show();
        }

        function mismaRutaCentro(primero, segundo) {
            return primero.EmpresaCodigo === segundo.EmpresaCodigo && primero.RutaClave === segundo.RutaClave;
        }

        function agruparCentrosPorRuta() {
            const grupos = new Map();
            centrosCostoData.forEach(item => {
                const clave = JSON.stringify([item.EmpresaCodigo, item.RutaClave]);
                if (!grupos.has(clave)) grupos.set(clave, []);
                grupos.get(clave).push(item);
            });
            return [...grupos.values()];
        }

        function renderConfigCentros() {
            const tbody = document.getElementById('tablaConfigCentrosCosto');
            const normalizar = valor => String(valor ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
            const busqueda = normalizar(document.getElementById('buscarConfigIdViejo').value.trim());
            const grupos = agruparCentrosPorRuta().filter(grupo => {
                if (mostrarSoloOcultosConfig && !grupo.some(item => esOculto(item.Ocultar))) return false;
                return busqueda === '' || grupo.some(item => [item.IdViejo, item.IdCentroCosto, item.Descripcion, item.IdGrupo, item.CuentaRuta, item.CuentaRutaDescripcion].some(valor => normalizar(valor).includes(busqueda)));
            }).sort((a, b) => String(a[0].EmpresaCodigo).localeCompare(String(b[0].EmpresaCodigo)) || String(a[0].IdGrupo).localeCompare(String(b[0].IdGrupo), 'es', { numeric: true }));

            tbody.innerHTML = grupos.map(grupo => {
                const item = grupo[0];
                const ocultos = grupo.filter(centro => esOculto(centro.Ocultar)).length;
                const detalle = grupo.slice().sort((a, b) => compararIdViejo(a.IdViejo, b.IdViejo)).map(centro => `
                    <tr>
                        <td>${escaparHtmlCentro(centro.IdCentroCosto)}</td>
                        <td>${escaparHtmlCentro(centro.IdViejo)}</td>
                        <td>${escaparHtmlCentro(centro.Descripcion)}</td>
                        <td>${centro.Inactivo ? 'Inactivo' : 'Activo'}</td>
                        <td><input type="checkbox" class="form-check-input centro-ocultar-check" aria-label="Ocultar centro ${Number(centro.IdCentroCosto)}" data-id="${centro.id}" ${esOculto(centro.Ocultar) ? 'checked' : ''}></td>
                    </tr>
                `).join('');
                const terminales = grupo.filter(centro => String(centro.IdViejo ?? '').trim() !== '').length;
                return `
                    <tr>
                        <td>${escaparHtmlCentro(item.EmpresaCodigo)}</td>
                        <td>${escaparHtmlCentro(item.IdGrupo || 'Sin ruta')}</td>
                        <td>${escaparHtmlCentro((item.CentrosCostoRuta ?? []).join(', ') || '—')}</td>
                        <td><details>
                            <summary>${grupo.length} centros / ${terminales} terminales</summary>
                            <table class="table table-sm mt-2 mb-0">
                                <thead><tr><th>Centro</th><th>Terminal</th><th>Agencia</th><th>Estado</th><th>Ocultar</th></tr></thead>
                                <tbody>${detalle}</tbody>
                            </table>
                        </details></td>
                        <td>
                            ${celdaCuentaCentro(item)}
                            ${item.RutaClave ? `<button type="button" class="btn btn-outline-primary btn-sm mt-1 asociar-cuenta-centro" data-id="${item.id}">${item.CuentaRutaId ? 'Cambiar' : 'Asociar'}</button>` : ''}
                        </td>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input ruta-ocultar-check" aria-label="Ocultar todos los centros de la ruta" data-id="${item.id}" ${ocultos === grupo.length ? 'checked' : ''}>
                            <div class="small text-muted">${ocultos} / ${grupo.length}</div>
                        </td>
                    </tr>
                `;
            }).join('');
            tbody.querySelectorAll('.ruta-ocultar-check').forEach(check => {
                const centro = centrosCostoData.find(item => Number(item.id) === Number(check.dataset.id));
                const grupo = centrosCostoData.filter(item => mismaRutaCentro(item, centro));
                check.indeterminate = grupo.some(item => esOculto(item.Ocultar)) && !grupo.every(item => esOculto(item.Ocultar));
            });
            if (!grupos.length) tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No hay rutas para configurar.</td></tr>';
        }

        function escaparHtmlCentro(valor) {
            const elemento = document.createElement('span');
            elemento.textContent = String(valor ?? '');
            return elemento.innerHTML;
        }

        function celdaCuentaCentro(item) {
            if (!item.CuentaRuta) return '<span class="text-muted">Sin asociar</span>';
            return escaparHtmlCentro(item.CuentaRuta) + '<div class="small text-muted">' + escaparHtmlCentro(item.CuentaRutaDescripcion) + '</div>';
        }

        function cerrarCuentaCentro() {
            if (guardandoCuentaCentro) return;
            centroCuentaSeleccionado = null;
            busquedaCuentaVersion++;
            clearTimeout(busquedaCuentaTimer);
            document.getElementById('panelCuentaCentro').classList.add('d-none');
        }

        function abrirCuentaCentro(id) {
            centroCuentaSeleccionado = centrosCostoData.find(item => Number(item.id) === id);
            if (!centroCuentaSeleccionado) return;
            document.getElementById('panelCuentaCentro').classList.remove('d-none');
            document.getElementById('tituloCuentaCentro').textContent = 'Ruta ' + centroCuentaSeleccionado.IdGrupo + ' | Empresa ' + centroCuentaSeleccionado.EmpresaCodigo + '. La cuenta se aplicara a todos sus centros y terminales.';
            document.getElementById('cuentaActualCentro').textContent = centroCuentaSeleccionado.CuentaRuta
                ? 'Cuenta actual: ' + centroCuentaSeleccionado.CuentaRuta + ' - ' + centroCuentaSeleccionado.CuentaRutaDescripcion
                : 'Esta ruta no tiene una cuenta asociada.';
            document.getElementById('buscarCuentaCentro').value = '';
            document.getElementById('quitarCuentaCentro').disabled = !centroCuentaSeleccionado.CuentaRutaId;
            buscarCuentasCentro();
            document.getElementById('buscarCuentaCentro').focus();
            document.getElementById('panelCuentaCentro').scrollIntoView({ block: 'nearest' });
        }

        async function buscarCuentasCentro() {
            if (!centroCuentaSeleccionado) return;
            const version = ++busquedaCuentaVersion;
            const select = document.getElementById('seleccionarCuentaCentro');
            const estado = document.getElementById('estadoBusquedaCuentaCentro');
            select.replaceChildren();
            document.getElementById('guardarCuentaCentro').disabled = true;
            estado.textContent = 'Buscando cuentas...';
            const url = buscarCuentasCentroUrl.replace('__CENTRO__', centroCuentaSeleccionado.id) + '?buscar=' + encodeURIComponent(document.getElementById('buscarCuentaCentro').value.trim());
            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                const cuentas = await parsearRespuestaJson(response, 'No se pudieron buscar las cuentas');
                if (version !== busquedaCuentaVersion) return;
                select.replaceChildren(new Option('Selecciona una cuenta', ''), ...cuentas.map(cuenta => new Option(cuenta.cuenta + ' - ' + cuenta.descripcion, cuenta.id)));
                select.value = '';
                estado.textContent = cuentas.length ? 'Selecciona la cuenta y guarda la asociacion. Se muestran hasta 50 resultados; escribe para buscar.' : 'No hay coincidencias. Revisa o sincroniza el catalogo de esta empresa en Inicio Contable.';
            } catch (error) {
                if (version === busquedaCuentaVersion) estado.textContent = error.message;
            }
        }

        async function guardarCuentaCentro(quitar) {
            if (!centroCuentaSeleccionado || guardandoCuentaCentro) return;
            const cuentaId = document.getElementById('seleccionarCuentaCentro').value;
            if (!quitar && !cuentaId) return;
            const centro = centroCuentaSeleccionado;
            guardandoCuentaCentro = true;
            const controles = ['guardarCuentaCentro', 'quitarCuentaCentro', 'cerrarCuentaCentro', 'buscarCuentaCentro', 'seleccionarCuentaCentro', 'btnGuardarConfigCentros'];
            controles.forEach(id => document.getElementById(id).disabled = true);
            const estado = document.getElementById('estadoBusquedaCuentaCentro');
            estado.textContent = 'Guardando asociacion...';
            try {
                const response = await fetch(guardarCuentaCentroUrl.replace('__CENTRO__', centro.id), {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ cuenta_ruta_id: quitar ? null : Number(cuentaId) }),
                });
                const data = await parsearRespuestaJson(response, 'No se pudo guardar la asociacion');
                const miembros = centrosCostoData.filter(item => mismaRutaCentro(item, data));
                miembros.forEach(item => Object.assign(item, { CuentaRutaId: data.CuentaRutaId, CuentaRuta: data.CuentaRuta, CuentaRutaDescripcion: data.CuentaRutaDescripcion }));
                const idsActualizados = new Set(miembros.map(item => Number(item.id)));
                renderConfigCentros();
                if (centrosCostoTable) {
                    centrosCostoTable.rows().every(function () {
                        const fila = this.node();
                        if (idsActualizados.has(Number(fila.dataset.centroId))) {
                            centrosCostoTable.cell(this.index(), 2).data(celdaCuentaCentro(centro));
                        }
                    });
                    centrosCostoTable.draw(false);
                }
                document.getElementById('cuentaActualCentro').textContent = centro.CuentaRuta ? 'Cuenta actual: ' + centro.CuentaRuta + ' - ' + centro.CuentaRutaDescripcion : 'Esta ruta no tiene una cuenta asociada.';
                estado.textContent = quitar ? 'Asociacion eliminada.' : 'Asociacion guardada para toda la ruta, incluidos sus centros y terminales.';
            } catch (error) {
                estado.textContent = error.message;
            } finally {
                guardandoCuentaCentro = false;
                controles.forEach(id => document.getElementById(id).disabled = false);
                document.getElementById('guardarCuentaCentro').disabled = !document.getElementById('seleccionarCuentaCentro').value;
                document.getElementById('quitarCuentaCentro').disabled = !centro.CuentaRutaId;
            }
        }

        function alternarOcultosConfig() {
            mostrarSoloOcultosConfig = !mostrarSoloOcultosConfig;

            const boton = document.getElementById('btnVerOcultosCentros');
            boton.classList.toggle('btn-warning', mostrarSoloOcultosConfig);
            boton.classList.toggle('btn-soft-warning', !mostrarSoloOcultosConfig);
            boton.textContent = mostrarSoloOcultosConfig ? 'Ver todos' : 'Ver ocultos';

            renderConfigCentros();
        }

        function compararIdViejo(a, b) {
            const valorA = String(a ?? '').trim();
            const valorB = String(b ?? '').trim();
            const numeroA = Number(valorA);
            const numeroB = Number(valorB);

            if (Number.isFinite(numeroA) && Number.isFinite(numeroB)) {
                return numeroA - numeroB;
            }

            return valorA.localeCompare(valorB, 'es', { numeric: true, sensitivity: 'base' });
        }

        function manejarCambioOcultarCentro(event) {
            const checkRuta = event.target.closest('.ruta-ocultar-check');
            if (checkRuta) {
                const centro = centrosCostoData.find(item => Number(item.id) === Number(checkRuta.dataset.id));
                centrosCostoData.filter(item => mismaRutaCentro(item, centro)).forEach(item => item.Ocultar = checkRuta.checked ? 1 : 0);
                renderConfigCentros();
                return;
            }
            const check = event.target.closest('.centro-ocultar-check');
            if (!check) return;

            const id = Number(check.dataset.id || 0);
            if (id <= 0) return;

            const centro = centrosCostoData.find(item => Number(item.id || 0) === id);
            if (!centro) return;

            centro.Ocultar = check.checked ? 1 : 0;
            const grupo = centrosCostoData.filter(item => mismaRutaCentro(item, centro));
            const cabecera = document.querySelector('.ruta-ocultar-check[data-id="' + grupo[0].id + '"]');
            if (cabecera) {
                cabecera.checked = grupo.every(item => esOculto(item.Ocultar));
                cabecera.indeterminate = grupo.some(item => esOculto(item.Ocultar)) && !cabecera.checked;
                cabecera.nextElementSibling.textContent = grupo.filter(item => esOculto(item.Ocultar)).length + ' / ' + grupo.length;
            }
        }

        function guardarConfiguracionCentros() {
            const items = centrosCostoData
                .map(item => ({
                    id: Number(item.id || 0),
                    ocultar: esOculto(item.Ocultar)
                }))
                .filter(item => item.id > 0);

            mostrarAlertaCarga('Guardando configuracion', 'Actualizando visibilidad de centros de costo...');

            fetch('/api-centros-costo/visibilidad', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ items })
            })
                .then(response => parsearRespuestaJson(response, 'Error al guardar configuracion de centros de costo'))
                .then(data => {
                    mostrarAlertaExito('Configuracion guardada', 'Registros actualizados: ' + (data.actualizados ?? 0));
                    return cargarCentrosCosto(false);
                })
                .catch(error => {
                    mostrarAlertaError(error.message || 'Error guardando configuracion.');
                });
        }

        async function sincronizarCentros() {
            const empresa = getEmpresaCentroCostoSeleccionada();
            if (empresa === 'todas') {
                mostrarAlertaError('Para sincronizar debes elegir una empresa especifica (168 o 169).');
                return;
            }

            const confirmado = await confirmarSincronizacion();
            if (!confirmado) return;

            const boton = document.getElementById('btnSincronizarCentros');
            const textoOriginal = boton.innerText;
            boton.disabled = true;
            boton.innerText = 'Sincronizando...';
            iniciarProgresoSincronizacion();

            fetch('/api-centros-costo/sync', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ empresa }),
            })
                .then(response => parsearRespuestaJson(response, 'Error durante la sincronizacion de centros de costo'))
                .then(data => {
                    finalizarProgresoSincronizacion();
                    mostrarAlertaExito(
                        'Sincronizacion completada',
                        `Recibidos: ${data.total_recibidos ?? 0}<br>` +
                        `Creados: ${data.creados ?? 0}<br>` +
                        `Actualizados: ${data.actualizados ?? 0}<br>` +
                        `Omitidos: ${data.omitidos ?? 0}`
                    );
                    cargarCentrosCosto(false);
                })
                .catch(error => {
                    detenerProgresoSincronizacion();
                    mostrarAlertaError(error.message || 'Error sincronizando centros de costo.');
                })
                .finally(() => {
                    boton.disabled = false;
                    boton.innerText = textoOriginal;
                });
        }

        async function eliminarDataCentrosPorEmpresa() {
            const empresa = getEmpresaCentroCostoSeleccionada();
            if (empresa === 'todas') {
                mostrarAlertaError('Para eliminar data debes elegir una empresa especifica (168 o 169).');
                return;
            }

            const confirmado = await confirmarEliminacionEmpresa(empresa);
            if (!confirmado) return;

            const boton = document.getElementById('btnEliminarCentrosEmpresa');
            const textoOriginal = boton.innerText;
            boton.disabled = true;
            boton.innerText = 'Eliminando...';
            mostrarAlertaCarga('Eliminando data', `Eliminando centros de costo de la empresa ${empresa}...`);

            fetch('/api-centros-costo/empresa', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ empresa }),
            })
                .then(response => parsearRespuestaJson(response, 'Error eliminando data de centros de costo'))
                .then(async data => {
                    mostrarAlertaExito(
                        'Eliminacion completada',
                        `Empresa: ${data.empresa ?? empresa}<br>Eliminados: ${(data.eliminados ?? 0).toLocaleString('es-DO')}`
                    );

                    await cargarCentrosCosto(false);
                })
                .catch(error => {
                    mostrarAlertaError(error.message || 'No se pudo eliminar la data de centros de costo.');
                })
                .finally(() => {
                    boton.disabled = false;
                    boton.innerText = textoOriginal;
                });
        }

        function mostrarAlertaCarga(titulo, texto) {
            if (typeof Swal === 'undefined') return;

            Swal.fire({
                title: titulo,
                text: texto,
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => Swal.showLoading()
            });
        }

        function getEmpresaCentroCostoSeleccionada() {
            const valor = String(filtroEmpresaCentroCostoEl?.value || '168').trim();
            return valor === '' ? '168' : valor;
        }

        function actualizarEtiquetaConsultar() {
            const boton = document.getElementById('btnConsultarCentros');
            const empresa = getEmpresaCentroCostoSeleccionada();
            const etiqueta = empresa === 'todas' ? 'Todas' : empresa;
            boton.innerText = `Consultar data (${etiqueta})`;
        }

        function mostrarAlertaExito(titulo, html) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: titulo,
                    html,
                    confirmButtonText: 'Cerrar'
                });
                return;
            }

            alert(String(html).replace(/<br>/g, '\n'));
        }

        function mostrarAlertaError(mensaje) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: mensaje,
                    confirmButtonText: 'Cerrar'
                });
                return;
            }

            alert(mensaje);
        }

        async function confirmarSincronizacion() {
            if (typeof Swal === 'undefined') {
                return confirm('Sincronizar centros de costo desde el API externo?');
            }

            const result = await Swal.fire({
                icon: 'question',
                title: 'Sincronizar centros de costo',
                text: 'Esto consultara el API externo y guardara datos nuevos o actualizados.',
                showCancelButton: true,
                confirmButtonText: 'Sincronizar',
                cancelButtonText: 'Cancelar'
            });

            return result.isConfirmed;
        }

        async function confirmarEliminacionEmpresa(empresa) {
            if (typeof Swal === 'undefined') {
                return confirm(`Esta accion eliminara toda la data de la empresa ${empresa}. Deseas continuar?`);
            }

            const result = await Swal.fire({
                icon: 'warning',
                title: 'Eliminar data por empresa',
                html: `Se eliminara toda la data de centros de costo de la empresa <strong>${empresa}</strong>.`,
                text: 'Esta accion no se puede deshacer.',
                showCancelButton: true,
                confirmButtonText: 'Si, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
            });

            return result.isConfirmed;
        }

        function iniciarProgresoSincronizacion() {
            let progreso = 0;
            mostrarProgresoSincronizacion(progreso);

            progresoSincronizacionTimer = setInterval(() => {
                progreso = Math.min(progreso + 5, 90);
                actualizarProgresoSincronizacion(progreso);
            }, 500);
        }

        function finalizarProgresoSincronizacion() {
            detenerProgresoSincronizacion();
            actualizarProgresoSincronizacion(100);
        }

        function detenerProgresoSincronizacion() {
            if (progresoSincronizacionTimer) {
                clearInterval(progresoSincronizacionTimer);
                progresoSincronizacionTimer = null;
            }
        }

        function mostrarProgresoSincronizacion(progreso) {
            if (typeof Swal === 'undefined') return;

            Swal.fire({
                title: 'Sincronizando centros de costo',
                html: `
                    <div class="progress" style="height: 20px;">
                        <div id="syncCentrosProgressBar" class="progress-bar progress-bar-striped progress-bar-animated"
                            role="progressbar" style="width: ${progreso}%">
                            ${progreso}%
                        </div>
                    </div>
                    <div class="text-muted mt-2">Guardando datos nuevos y actualizando existentes...</div>
                `,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false
            });
        }

        function actualizarProgresoSincronizacion(progreso) {
            const progressBar = document.getElementById('syncCentrosProgressBar');
            if (!progressBar) return;

            progressBar.style.width = progreso + '%';
            progressBar.textContent = progreso + '%';
        }
    </script>
@endsection
