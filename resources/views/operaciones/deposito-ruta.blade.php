@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Deposito Ruta</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('operaciones.index') }}">Operaciones</a></li>
                                    <li class="breadcrumb-item active">Deposito Ruta</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <p class="text-muted mb-1">Estatus pendiente</p>
                                    <h3 class="mb-0" id="cardPendienteDepositoRuta">0</h3>
                                </div>
                                <div class="avatar-sm">
                                    <span class="avatar-title rounded bg-warning-subtle text-warning fs-3">
                                        <i class="ri-time-line"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <p class="text-muted mb-1">Estatus recibido</p>
                                    <h3 class="mb-0" id="cardRecibidoDepositoRuta">0</h3>
                                </div>
                                <div class="avatar-sm">
                                    <span class="avatar-title rounded bg-success-subtle text-success fs-3">
                                        <i class="ri-checkbox-circle-line"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <p class="text-muted mb-1">Monto total mostrado</p>
                                    <h3 class="mb-0" id="cardMontoDepositoRuta">0.00</h3>
                                </div>
                                <div class="avatar-sm">
                                    <span class="avatar-title rounded bg-primary-subtle text-primary fs-3">
                                        <i class="ri-money-dollar-circle-line"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div>
                                    <h5 class="card-title mb-1">Deposito ruta</h5>
                                    <p class="text-muted mb-0">Comprobantes bancarios recibidos desde el chatbot en la opcion Operador.</p>
                                </div>
                                <span class="badge bg-soft-primary text-primary">WhatsApp</span>
                            </div>
                            <div class="card-body">
                                <div class="row g-2 align-items-end mb-3">
                                    <div class="col-md-4 col-lg-3">
                                        <label for="filtroFechaDepositoRuta" class="form-label">Filtrar por fecha</label>
                                        <input type="date" id="filtroFechaDepositoRuta" class="form-control">
                                    </div>
                                    <div class="col-md-auto">
                                        <button type="button" class="btn btn-primary" id="btnFiltrarFechaDepositoRuta">
                                            <i class="ri-filter-3-line align-bottom me-1"></i>Filtrar
                                        </button>
                                    </div>
                                    <div class="col-md-auto">
                                        <button type="button" class="btn btn-outline-secondary" id="btnLimpiarFechaDepositoRuta">
                                            <i class="ri-eraser-line align-bottom me-1"></i>Limpiar
                                        </button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle w-100" id="tablaDepositoRuta"
                                        data-url="{{ route('operaciones.deposito-ruta.data') }}"
                                        data-estado-url-template="{{ route('operaciones.deposito-ruta.estado', ['deposito' => '__ID__']) }}">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Telefono</th>
                                                <th>Banco</th>
                                                <th>Ruta</th>
                                                <th class="text-end">Monto depositado</th>
                                                <th>Estado</th>
                                                <th>Imagen</th>
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
    </div>

    <div class="modal fade" id="modalImagenDepositoRuta" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalImagenDepositoRutaTitulo">Imagen del deposito</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <a id="modalImagenDepositoRutaLink" href="#" target="_blank" rel="noopener noreferrer">
                        <img id="modalImagenDepositoRutaPreview" src="" alt="Imagen del deposito" class="img-fluid rounded border">
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tabla = $('#tablaDepositoRuta');
            const dataUrl = tabla.data('url');
            const estadoUrlTemplate = tabla.data('estado-url-template');
            const filtroFecha = document.getElementById('filtroFechaDepositoRuta');
            const btnFiltrarFecha = document.getElementById('btnFiltrarFechaDepositoRuta');
            const btnLimpiarFecha = document.getElementById('btnLimpiarFechaDepositoRuta');
            const modalImagen = new bootstrap.Modal(document.getElementById('modalImagenDepositoRuta'));
            const today = new Date().toISOString().slice(0, 10);
            let autoRefreshTimer = null;

            if (!tabla.length || !dataUrl) {
                return;
            }

            filtroFecha.value = filtroFecha.value || today;

            function escapeJs(value) {
                return String(value ?? '')
                    .replace(/\\/g, '\\\\')
                    .replace(/'/g, "\\'")
                    .replace(/\n/g, '\\n')
                    .replace(/\r/g, '\\r');
            }

            window.abrirImagenDepositoRuta = function (url, banco) {
                document.getElementById('modalImagenDepositoRutaPreview').src = url;
                document.getElementById('modalImagenDepositoRutaLink').href = url;
                document.getElementById('modalImagenDepositoRutaTitulo').textContent = `Deposito - ${banco || 'Banco'}`;
                modalImagen.show();
            };

            function actualizarTarjetas(resumen) {
                document.getElementById('cardPendienteDepositoRuta').textContent = Number(resumen?.pendiente || 0).toLocaleString('en-US');
                document.getElementById('cardRecibidoDepositoRuta').textContent = Number(resumen?.recibido || 0).toLocaleString('en-US');
                document.getElementById('cardMontoDepositoRuta').textContent = Number(resumen?.monto_total || 0).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function badgeEstado(estado, id) {
                const normalized = String(estado || 'Pendiente').toLowerCase();
                const esRecibido = normalized === 'recibido';
                const nextEstado = esRecibido ? 'pendiente' : 'recibido';
                const btnClass = esRecibido ? 'btn-success' : 'btn-warning';
                const label = esRecibido ? 'Recibido' : 'Pendiente';

                return `<button type="button" class="btn btn-sm ${btnClass} btn-cambiar-estado"
                    data-id="${id}" data-next-estado="${nextEstado}">
                    ${label}
                </button>`;
            }

            const dataTable = tabla.DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: dataUrl,
                    data: function (params) {
                        params.fecha = filtroFecha.value || '';
                    },
                    dataSrc: function (json) {
                        actualizarTarjetas(json.resumen || {});

                        return json.data || [];
                    }
                },
                pageLength: 25,
                order: [[0, 'desc']],
                columns: [
                    { data: 'fecha', name: 'fecha' },
                    { data: 'whatsapp_phone', name: 'whatsapp_phone' },
                    { data: 'banco', name: 'banco' },
                    { data: 'ruta_nombre', name: 'ruta_nombre' },
                    {
                        data: 'monto_depositado',
                        name: 'monto_depositado',
                        className: 'text-end',
                        render: function (data) {
                            return `<span class="fw-semibold">${data || '0.00'}</span>`;
                        }
                    },
                    {
                        data: 'estado',
                        name: 'estado',
                        render: function (data, type, row) {
                            return badgeEstado(data, row.id);
                        }
                    },
                    {
                        data: 'comprobante_url',
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            if (!data) {
                                return '<span class="text-muted">Sin imagen</span>';
                            }

                            return `
                                <button type="button" class="btn btn-sm btn-info" onclick="abrirImagenDepositoRuta('${escapeJs(data)}', '${escapeJs(row.banco)}')">
                                    <i class="ri-image-2-line me-1"></i>Ver
                                </button>
                            `;
                        }
                    }
                ],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                }
            });

            btnFiltrarFecha.addEventListener('click', function () {
                dataTable.ajax.reload();
            });

            filtroFecha.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    dataTable.ajax.reload();
                }
            });

            btnLimpiarFecha.addEventListener('click', function () {
                filtroFecha.value = today;
                dataTable.ajax.reload();
            });

            tabla.on('click', '.btn-cambiar-estado', function () {
                const button = this;
                const id = button.dataset.id;
                const nextEstado = button.dataset.nextEstado;

                if (!id || !nextEstado || !estadoUrlTemplate) {
                    return;
                }

                button.disabled = true;

                fetch(String(estadoUrlTemplate).replace('__ID__', id), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ estado: nextEstado })
                })
                    .then(async function (response) {
                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok) {
                            throw new Error(payload.message || 'No se pudo actualizar el estado.');
                        }

                        dataTable.ajax.reload(null, false);
                    })
                    .catch(function (error) {
                        if (window.Swal) {
                            Swal.fire('Error', error.message || 'No se pudo actualizar el estado.', 'error');
                        } else {
                            alert(error.message || 'No se pudo actualizar el estado.');
                        }

                        button.disabled = false;
                    });
            });

            autoRefreshTimer = setInterval(function () {
                if (document.hidden || document.body.classList.contains('modal-open')) {
                    return;
                }

                dataTable.ajax.reload(null, false);
            }, 15000);

            window.addEventListener('beforeunload', function () {
                if (autoRefreshTimer) {
                    clearInterval(autoRefreshTimer);
                }
            });
        });
    </script>
@endsection
