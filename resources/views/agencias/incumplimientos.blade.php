@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">

                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Incumplimiento de Horario por Agencia</h4>

                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('agencias.index') }}">Agencias</a></li>
                                    <li class="breadcrumb-item active">Incumplimientos</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-3">
                                        <label for="fecha" class="form-label mb-1">Fecha</label>
                                        <input type="date" id="fecha" class="form-control" value="{{ now()->toDateString() }}">
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check form-switch mt-4">
                                            <input class="form-check-input" type="checkbox" id="soloIncumplidas" checked>
                                            <label class="form-check-label" for="soloIncumplidas">Solo incumplidas</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <button class="btn btn-primary" id="btnConsultar">
                                            <i class="ri-search-line me-1"></i> Consultar
                                        </button>
                                        <a href="{{ route('agencias.index') }}" class="btn btn-light">
                                            <i class="ri-arrow-left-line me-1"></i> Volver
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info mb-3" id="resumenBox">
                                    Selecciona una fecha para consultar.
                                </div>

                                <div class="table-responsive">
                                    <table id="tableIncumplimientos" class="table table-bordered table-striped align-middle" style="width:100%; font-size:0.85rem;">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Terminal</th>
                                                <th>Agencia</th>
                                                <th>Horario AM</th>
                                                <th>Horario PM</th>
                                                <th>Entrada Real</th>
                                                <th>Salida Real</th>
                                                <th>Min. Tarde</th>
                                                <th>Min. Salida Antes</th>
                                                <th>Fuente</th>
                                                <th>Estado</th>
                                                <th>Observaciones</th>
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
                        <script>document.write(new Date().getFullYear())</script> © CRM.
                    </div>
                </div>
            </div>
        </footer>
    </div>
@endsection

@section('script')
<script>
    let table;

    function cargarData() {
        const fecha = $('#fecha').val();
        const soloIncumplidas = $('#soloIncumplidas').is(':checked') ? 1 : 0;

        $.ajax({
            url: '{{ route('agencias.incumplimientos.list') }}',
            method: 'GET',
            data: {
                fecha: fecha,
                solo_incumplidas: soloIncumplidas
            },
            success: function(resp) {
                const data = resp.data || [];

                if (table) {
                    table.clear().rows.add(data).draw();
                }

                $('#resumenBox').html(
                    `<strong>Fecha:</strong> ${resp.fecha} | <strong>Total:</strong> ${resp.total} | <strong>Incumplidas:</strong> ${resp.incumplidas}`
                );
            },
            error: function() {
                Swal.fire('Error', 'No se pudo cargar la información.', 'error');
            }
        });
    }

    $(document).ready(function() {
        table = $('#tableIncumplimientos').DataTable({
            data: [],
            responsive: true,
            scrollX: true,
            columns: [
                { data: 'terminal' },
                { data: 'nombre_agencia', render: function(data, type, row) {
                    return (data || row.agencia || '-') + `<div class="text-muted fs-11">Cod: ${row.agencia || '-'}</div>`;
                }},
                { data: 'horario_am', defaultContent: '-' },
                { data: 'horario_pm', defaultContent: '-' },
                { data: 'entrada_real', className: 'text-center' },
                { data: 'salida_real', className: 'text-center' },
                { data: 'minutos_tarde', className: 'text-center', render: function(data) {
                    const minutos = Math.round(parseFloat(data || 0));
                    return minutos > 0 ? `<span class="badge bg-danger">${minutos}</span>` : '<span class="badge bg-success">0</span>';
                }},
                { data: 'minutos_salida_antes', className: 'text-center', render: function(data) {
                    const minutos = Math.round(parseFloat(data || 0));
                    return minutos > 0 ? `<span class="badge bg-warning text-dark">${minutos}</span>` : '<span class="badge bg-success">0</span>';
                }},
                { data: 'fuente', className: 'text-center' },
                { data: 'estado', className: 'text-center', render: function(data) {
                    if (data === 'INCUMPLE') {
                        return '<span class="badge bg-danger">INCUMPLE</span>';
                    }
                    return '<span class="badge bg-success">CUMPLE</span>';
                }},
                { data: 'observaciones' },
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
            },
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
        });

        $('#btnConsultar').on('click', cargarData);

        cargarData();
    });
</script>
@endsection
