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
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle w-100" id="tablaDepositoRuta"
                                        data-url="{{ route('operaciones.deposito-ruta.data') }}">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Telefono</th>
                                                <th>Banco</th>
                                                <th>Ruta</th>
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
            const modalImagen = new bootstrap.Modal(document.getElementById('modalImagenDepositoRuta'));

            if (!tabla.length || !dataUrl) {
                return;
            }

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

            tabla.DataTable({
                processing: true,
                serverSide: true,
                ajax: dataUrl,
                pageLength: 25,
                order: [[0, 'desc']],
                columns: [
                    { data: 'fecha', name: 'fecha' },
                    { data: 'whatsapp_phone', name: 'whatsapp_phone' },
                    { data: 'banco', name: 'banco' },
                    { data: 'ruta_nombre', name: 'ruta_nombre' },
                    {
                        data: 'estado',
                        name: 'estado',
                        render: function (data) {
                            return `<span class="badge bg-warning-subtle text-warning">${data || 'Pendiente'}</span>`;
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
        });
    </script>
@endsection
