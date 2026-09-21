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
                                        <th class="ps-4">Cédula</th>
                                        <th class="text-end pe-4">Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaPendientes">
                                    <tr><td colspan="2" class="text-center text-muted py-5">Cargando...</td></tr>
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
        const sincronizarEmpleadoUrl = @json(url('/empleados/sincronizar'));
        let cedulasPendientes = [];

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
                tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-5">No hay cédulas pendientes para mostrar.</td></tr>';
                return;
            }

            filas.forEach(fila => {
                const tr = document.createElement('tr');
                const tdCedula = document.createElement('td');
                const tdAccion = document.createElement('td');
                const boton = document.createElement('button');

                tdCedula.className = 'ps-4 fw-semibold';
                tdCedula.textContent = fila.cedula;
                tdAccion.className = 'text-end pe-4';
                boton.type = 'button';
                boton.className = 'btn btn-sm btn-primary';
                boton.innerHTML = '<i class="ri-search-line me-1"></i>Consultar API';
                boton.addEventListener('click', () => consultarApi(fila.cedula, boton));

                tdAccion.appendChild(boton);
                tr.appendChild(tdCedula);
                tr.appendChild(tdAccion);
                tbody.appendChild(tr);
            });
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
                    document.getElementById('totalPendientes').textContent = Number(payload.total || 0).toLocaleString('en-US');
                    document.getElementById('fechaVentas').textContent = payload?.meta?.fecha_ventas || 'Sin datos';
                    renderPendientes();
                });
        }

        async function consultarApi(cedula, boton) {
            boton.disabled = true;
            boton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Consultando';

            try {
                for (const empresa of ['168', '169']) {
                    const params = new URLSearchParams({ empresa, cedula });
                    const response = await fetch(sincronizarEmpleadoUrl + '?' + params.toString(), {
                        headers: { 'Accept': 'application/json' },
                    });
                    const payload = await parsearRespuesta(response, 'No se pudo consultar el API de empleados.');

                    if (Number(payload.procesados || 0) > 0) {
                        await Swal.fire({
                            title: 'Empleado encontrado',
                            text: 'La cédula ' + cedula + ' fue sincronizada desde la empresa ' + empresa + '.',
                            icon: 'success',
                        });
                        await cargarPendientes(true);
                        return;
                    }
                }

                Swal.fire({
                    title: 'No encontrada',
                    text: 'La cédula ' + cedula + ' no fue encontrada en las empresas 168 ni 169.',
                    icon: 'warning',
                });
            } catch (error) {
                Swal.fire('Error', error.message || 'No fue posible consultar el API.', 'error');
            } finally {
                boton.disabled = false;
                boton.innerHTML = '<i class="ri-search-line me-1"></i>Consultar API';
            }
        }

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
