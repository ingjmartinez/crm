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
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tabla = $('#tablaDepositoRuta');
            const dataUrl = tabla.data('url');

            if (!tabla.length || !dataUrl) {
                return;
            }

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
                    {
                        data: 'estado',
                        name: 'estado',
                        render: function (data) {
                            return `<span class="badge bg-warning-subtle text-warning">${data || 'Pendiente'}</span>`;
                        }
                    },
                    {
                        data: 'imagen_url',
                        orderable: false,
                        searchable: false,
                        render: function (data) {
                            if (!data) {
                                return '<span class="text-muted">Sin imagen</span>';
                            }

                            return `<a href="${data}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                <i class="ri-image-2-line me-1"></i>Ver imagen
                            </a>`;
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
