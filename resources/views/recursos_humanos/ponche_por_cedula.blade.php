<div>
    <!-- He who is contented is rich. - Laozi -->
</div>
@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Ponche por Cédula</h4>
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('recursos-humanos.index') }}">Recursos Humanos</a></li>
                        <li class="breadcrumb-item active">Ponche por Cédula</li>
                    </ol>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Cargar ventas del día</h5></div>
                    <div class="card-body">
                        <form id="formCargarPoncheVentas" method="POST" action="{{ route('recursos-humanos.ponche-por-cedula.cargar-ventas') }}" enctype="multipart/form-data">
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
                    <div class="card-header"><h5 class="card-title mb-0">Filtros</h5></div>
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="cedula" class="form-label">Cédula</label>
                                <input type="text" id="cedula" class="form-control" placeholder="Ej: 40240461497" inputmode="numeric" maxlength="13">
                            </div>
                            <div class="col-md-2">
                                <label for="sistema" class="form-label">Sistema</label>
                                <select id="sistema" class="form-control">
                                    <option value="todos">Todos</option>
                                    <option value="lotonet">Lotonet</option>
                                    <option value="lotobet">Lotobet</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                                <input type="date" id="fecha_inicio" class="form-control" value="{{ today()->startOfMonth()->toDateString() }}">
                            </div>
                            <div class="col-md-2">
                                <label for="fecha_fin" class="form-label">Fecha Fin</label>
                                <input type="date" id="fecha_fin" class="form-control" value="{{ today()->toDateString() }}">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-primary w-100" id="btnBuscar"><i class="ri-search-line"></i> Consultar</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Resultados</h5></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="tablePoncheCedula" class="table table-bordered table-striped align-middle" style="width: 100%;">
                                <thead><tr><th>Cédula</th><th>Nombre</th><th>Día</th><th>Sistema</th><th>Primer login</th><th>Último login</th><th>Salida calculada</th><th class="text-end">Total de horas</th></tr></thead>
                                <tbody></tbody>
                                <tfoot><tr><th colspan="7" class="text-end">Total de horas</th><th id="totalHoras" class="text-end">0,00</th></tr></tfoot>
                            </table>
                        </div>
                        <small class="text-muted">Total de registros: <span id="totalRegistros" class="fw-semibold">0</span></small>
                        <p class="text-muted small mt-2 mb-0">Si hay una venta posterior al último login, la salida calculada será la última venta más cinco minutos.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        let table;

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

        function cargarDatos() {
            const cedula = document.getElementById('cedula').value.trim();
            const sistema = document.getElementById('sistema').value;
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;

            if (!/^\d{11}$/.test(cedula.replace(/\D/g, ''))) {
                Swal.fire('Error', 'Ingrese una cédula de 11 dígitos.', 'error');
                return;
            }
            if (!fechaInicio || !fechaFin || fechaInicio > fechaFin) {
                Swal.fire('Error', 'Seleccione un rango de fechas válido.', 'error');
                return;
            }

            Swal.fire({title: 'Consultando...', allowOutsideClick: false, didOpen: () => Swal.showLoading()});
            if (table) table.destroy();

            table = $('#tablePoncheCedula').DataTable({
                ajax: {
                    url: @json(route('recursos-humanos.ponche-por-cedula.list')),
                    type: 'GET',
                    data: {cedula, sistema, fecha_inicio: fechaInicio, fecha_fin: fechaFin},
                    dataSrc: '',
                    complete: () => Swal.close(),
                    error: function(xhr) {
                        Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo consultar el reporte.', 'error');
                    }
                },
                columns: [
                    {data: 'cedula'},
                    {data: 'nombre'},
                    {data: 'fecha'},
                    {data: 'sistema'},
                    {data: 'primer_login', defaultContent: '—', render: data => data || '—'},
                    {data: 'ultimo_login', defaultContent: '—', render: data => data || '—'},
                    {data: 'salida_calculada', defaultContent: '—', render: data => data || '—'},
                    {data: 'total_horas', className: 'text-end', render: data => data === null ? '—' : Number(data).toLocaleString('es-DO', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                ],
                autoWidth: false,
                dom: 'Bfrtip',
                buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
                language: {url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'},
                order: [[2, 'asc'], [3, 'asc']],
                paging: false,
                searching: false,
                info: false,
                drawCallback: settings => {
                    document.getElementById('totalRegistros').textContent = settings.json?.length || 0;
                },
                footerCallback: function() {
                    const total = this.api().rows({search: 'applied'}).data().toArray()
                        .reduce((suma, row) => suma + Number(row.total_horas || 0), 0);
                    document.getElementById('totalHoras').textContent = total.toLocaleString('es-DO', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }
            });
        }

        document.getElementById('btnBuscar').addEventListener('click', cargarDatos);
        document.getElementById('cedula').addEventListener('keypress', event => {
            if (event.key === 'Enter') cargarDatos();
        });
    </script>
@endsection
