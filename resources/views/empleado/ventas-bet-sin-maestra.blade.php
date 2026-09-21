@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                    <div>
                        <h4 class="mb-1">Cédulas de ventas fuera de la maestra</h4>
                        <p class="text-muted mb-0">Usuarios de la última fecha cargada en ventas BET que todavía no existen en empleados.</p>
                    </div>
                    <a href="/empleados" class="btn btn-light">
                        <i class="ri-arrow-left-line me-1"></i>Volver a empleados
                    </a>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-sm-6 col-xl-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted text-uppercase small fw-semibold">Cédulas pendientes</div>
                                <div class="display-6 fw-semibold text-warning" id="totalPendientes">—</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted text-uppercase small fw-semibold">Fecha de ventas</div>
                                <div class="fs-3 fw-semibold" id="fechaVentas">—</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <h5 class="card-title mb-0">Pendientes de consultar</h5>
                        <div class="d-flex gap-2">
                            <input type="search" id="buscarCedula" class="form-control form-control-sm" placeholder="Buscar cédula">
                            <button type="button" id="btnSeleccionarCincuenta" class="btn btn-sm btn-outline-secondary text-nowrap">
                                Seleccionar 50
                            </button>
                            <button type="button" id="btnConsultarSeleccionadas" class="btn btn-sm btn-primary text-nowrap" disabled>
                                Consultar seleccionadas (0/50)
                            </button>
                            <button type="button" id="btnActualizar" class="btn btn-sm btn-outline-primary text-nowrap">
                                <i class="ri-refresh-line me-1"></i>Actualizar
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4" style="width: 48px;"></th>
                                        <th class="ps-4">Cédula</th>
                                        <th class="text-end pe-4">Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaPendientes">
                                    <tr><td colspan="3" class="text-center text-muted py-5">Cargando...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const ventasBetSinMaestraListUrl = @json(route('empleados.ventas-bet-sin-maestra.list'));
        const sincronizarLoteUrl = @json(route('empleados.sincronizar-lote'));
        let cedulasPendientes = [];
        const cedulasSeleccionadas = new Set();

        function parsearRespuesta(response, mensaje) {
            return response.json().catch(() => ({})).then(payload => {
                if (!response.ok) {
                    throw new Error(payload.error || mensaje);
                }

                return payload;
            });
        }

        function renderPendientes() {
            const filtro = document.getElementById('buscarCedula').value.replace(/\D/g, '');
            const filas = cedulasPendientes.filter(fila => !filtro || fila.cedula.includes(filtro));
            const tbody = document.getElementById('tablaPendientes');
            tbody.innerHTML = '';

            if (filas.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-5">No hay cédulas pendientes para mostrar.</td></tr>';
                return;
            }

            filas.forEach(fila => {
                const tr = document.createElement('tr');
                const tdSeleccion = document.createElement('td');
                const tdCedula = document.createElement('td');
                const tdAccion = document.createElement('td');
                const checkbox = document.createElement('input');
                const boton = document.createElement('button');

                tdSeleccion.className = 'ps-4';
                checkbox.type = 'checkbox';
                checkbox.className = 'form-check-input';
                checkbox.checked = cedulasSeleccionadas.has(fila.cedula);
                checkbox.disabled = !checkbox.checked && cedulasSeleccionadas.size >= 50;
                checkbox.addEventListener('change', function () {
                    if (this.checked) {
                        cedulasSeleccionadas.add(fila.cedula);
                    } else {
                        cedulasSeleccionadas.delete(fila.cedula);
                    }
                    actualizarSeleccion();
                    renderPendientes();
                });
                tdCedula.className = 'ps-4 fw-semibold';
                tdCedula.textContent = fila.cedula;
                tdAccion.className = 'text-end pe-4';
                boton.type = 'button';
                boton.className = 'btn btn-sm btn-primary';
                boton.innerHTML = '<i class="ri-search-line me-1"></i>Consultar API';
                boton.addEventListener('click', () => consultarApi(fila.cedula, boton));

                tdAccion.appendChild(boton);
                tdSeleccion.appendChild(checkbox);
                tr.appendChild(tdSeleccion);
                tr.appendChild(tdCedula);
                tr.appendChild(tdAccion);
                tbody.appendChild(tr);
            });
        }

        function actualizarSeleccion() {
            const cantidad = cedulasSeleccionadas.size;
            const boton = document.getElementById('btnConsultarSeleccionadas');
            boton.disabled = cantidad === 0;
            boton.textContent = 'Consultar seleccionadas (' + cantidad + '/50)';
        }

        function cargarPendientes(refresh = false) {
            const params = new URLSearchParams();
            if (refresh) {
                params.set('refresh', '1');
            }

            return fetch(ventasBetSinMaestraListUrl + '?' + params.toString(), {
                headers: { 'Accept': 'application/json' },
            })
                .then(response => parsearRespuesta(response, 'No se pudieron consultar las cédulas pendientes.'))
                .then(payload => {
                    cedulasPendientes = Array.isArray(payload.data) ? payload.data : [];
                    const pendientesActuales = new Set(cedulasPendientes.map(fila => fila.cedula));
                    [...cedulasSeleccionadas].forEach(cedula => {
                        if (!pendientesActuales.has(cedula)) {
                            cedulasSeleccionadas.delete(cedula);
                        }
                    });
                    document.getElementById('totalPendientes').textContent = Number(payload.total || 0).toLocaleString('en-US');
                    document.getElementById('fechaVentas').textContent = payload?.meta?.fecha_ventas || 'Sin datos';
                    actualizarSeleccion();
                    renderPendientes();
                });
        }

        function enviarLote(cedulas) {
            return fetch(sincronizarLoteUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ cedulas }),
            }).then(response => parsearRespuesta(response, 'No se pudo consultar el lote en el API.'));
        }

        async function consultarApi(cedula, boton) {
            boton.disabled = true;
            boton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Consultando';

            try {
                const payload = await enviarLote([cedula]);
                const resultado = payload.resultados[0];
                const encontrado = resultado?.estado === 'sincronizado';
                await Swal.fire({
                    title: encontrado ? 'Empleado encontrado' : 'No encontrada',
                    text: encontrado
                        ? 'La cédula ' + cedula + ' fue sincronizada desde la empresa ' + resultado.empresa + '.'
                        : 'La cédula ' + cedula + ' no fue encontrada en las empresas 168 ni 169.',
                    icon: encontrado ? 'success' : 'warning',
                });
                await cargarPendientes(true);
            } catch (error) {
                Swal.fire('Error', error.message || 'No fue posible consultar el API.', 'error');
            } finally {
                boton.disabled = false;
                boton.innerHTML = '<i class="ri-search-line me-1"></i>Consultar API';
            }
        }

        document.getElementById('btnSeleccionarCincuenta').addEventListener('click', function () {
            const filtro = document.getElementById('buscarCedula').value.replace(/\D/g, '');
            cedulasSeleccionadas.clear();
            cedulasPendientes
                .filter(fila => !filtro || fila.cedula.includes(filtro))
                .slice(0, 50)
                .forEach(fila => cedulasSeleccionadas.add(fila.cedula));
            actualizarSeleccion();
            renderPendientes();
        });

        document.getElementById('btnConsultarSeleccionadas').addEventListener('click', async function () {
            const cedulas = [...cedulasSeleccionadas];
            if (cedulas.length === 0) {
                return;
            }

            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Consultando lote';

            try {
                const payload = await enviarLote(cedulas);
                const encontrados = payload.resultados.filter(resultado => resultado.estado === 'sincronizado').length;
                const noEncontrados = payload.resultados.filter(resultado => resultado.estado === 'no_encontrado').length;
                const errores = payload.resultados.filter(resultado => resultado.estado === 'error').length;
                cedulasSeleccionadas.clear();
                await Swal.fire({
                    title: 'Lote completado',
                    html: 'Encontrados: <strong>' + encontrados + '</strong><br>'
                        + 'No encontrados: <strong>' + noEncontrados + '</strong><br>'
                        + 'Con error: <strong>' + errores + '</strong>',
                    icon: errores > 0 ? 'warning' : 'success',
                });
                await cargarPendientes(true);
            } catch (error) {
                Swal.fire('Error', error.message || 'No fue posible consultar el lote.', 'error');
            } finally {
                actualizarSeleccion();
            }
        });

        document.getElementById('buscarCedula').addEventListener('input', renderPendientes);
        document.getElementById('btnActualizar').addEventListener('click', function () {
            this.disabled = true;
            cargarPendientes(true)
                .catch(error => Swal.fire('Error', error.message, 'error'))
                .finally(() => { this.disabled = false; });
        });

        document.addEventListener('DOMContentLoaded', function () {
            cargarPendientes().catch(error => Swal.fire('Error', error.message, 'error'));
        });
    </script>
@endsection
