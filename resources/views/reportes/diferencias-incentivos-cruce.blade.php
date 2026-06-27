@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Diferencias Incentivos vs Cruce</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('reportes.index') }}">Reportes</a></li>
                                    <li class="breadcrumb-item active">Diferencias Incentivos vs Cruce</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-info">
                            Esta vista compara las c&eacute;dulas pendientes en <strong>Reporte Nuevo Incentivo V5</strong>
                            contra las c&eacute;dulas visibles por defecto en <strong>Cruce de Usuarios</strong>.
                            Tambi&eacute;n muestra si la c&eacute;dula existe en la maestra y por qu&eacute; solo aparece en una de las dos pantallas.
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Filtros</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-2">
                                        <label for="sistema" class="form-label">Sistema</label>
                                        <select id="sistema" class="form-control">
                                            <option value="Todos">Todos</option>
                                            <option value="Lotobet">Lotobet</option>
                                            <option value="Lotonet">Lotonet</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="empresa" class="form-label">Empresa</label>
                                        <select id="empresa" class="form-control">
                                            <option value="todos">Todas</option>
                                            <option value="grupo_joselito">Grupo Joselito</option>
                                            <option value="negosur">Negosur</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="modo_calculo" class="form-label">Modo incentivo</label>
                                        <select id="modo_calculo" class="form-control">
                                            <option value="general">General consolidado</option>
                                            <option value="separado_empresa">Separado por empresa</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="filtro_cumplimiento" class="form-label">Cumplimiento</label>
                                        <select id="filtro_cumplimiento" class="form-control">
                                            <option value="todos">Todos</option>
                                            <option value="cumplidos">Cumplidos</option>
                                            <option value="no_cumplidos">No cumplidos</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="diferencia" class="form-label">Comparaci&oacute;n</label>
                                        <select id="diferencia" class="form-control">
                                            <option value="todas">Todas</option>
                                            <option value="solo_incentivos">Solo Incentivos</option>
                                            <option value="solo_cruce">Solo Cruce</option>
                                            <option value="ambos">Ambos</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="fecha_inicio" class="form-label">Fecha inicio</label>
                                        <input type="date" id="fecha_inicio" class="form-control" value="{{ date('Y-m-01') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="fecha_fin" class="form-label">Fecha fin</label>
                                        <input type="date" id="fecha_fin" class="form-control" value="{{ date('Y-m-d') }}">
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="button" class="btn btn-primary w-100" id="btnBuscar">
                                            <i class="ri-search-line"></i> Consultar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-2">
                        <div class="card card-animate h-100">
                            <div class="card-body">
                                <p class="text-muted text-uppercase mb-1">Incentivos</p>
                                <h4 class="mb-0" id="sumIncentivos">0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card card-animate h-100">
                            <div class="card-body">
                                <p class="text-muted text-uppercase mb-1">Cruce Visible</p>
                                <h4 class="mb-0" id="sumCruce">0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card card-animate h-100">
                            <div class="card-body">
                                <p class="text-muted text-uppercase mb-1">Solo Incentivos</p>
                                <h4 class="mb-0 text-warning" id="sumSoloIncentivos">0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card card-animate h-100">
                            <div class="card-body">
                                <p class="text-muted text-uppercase mb-1">Solo Cruce</p>
                                <h4 class="mb-0 text-danger" id="sumSoloCruce">0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card card-animate h-100">
                            <div class="card-body">
                                <p class="text-muted text-uppercase mb-1">Ambos</p>
                                <h4 class="mb-0 text-success" id="sumAmbos">0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card card-animate h-100">
                            <div class="card-body">
                                <p class="text-muted text-uppercase mb-1">Activos Ocultos</p>
                                <h4 class="mb-0 text-info" id="sumActivosOcultos">0</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Resultados por c&eacute;dula</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="tableDiferencias" class="table table-bordered table-striped align-middle" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>C&eacute;dula</th>
                                                <th>Clasificaci&oacute;n</th>
                                                <th>En Incentivos</th>
                                                <th>En Cruce</th>
                                                <th>Estatus Cruce</th>
                                                <th>Empleado ID</th>
                                                <th>Nombre Maestra</th>
                                                <th>Estado Maestra</th>
                                                <th>Empresa Incentivo</th>
                                                <th>Agencia</th>
                                                <th>Terminal</th>
                                                <th>&Uacute;ltima Venta</th>
                                                <th>Motivo</th>
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
        let table;

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function badgeClasificacion(value) {
            if (value === 'Solo Incentivos') {
                return '<span class="badge bg-warning text-dark">Solo Incentivos</span>';
            }
            if (value === 'Solo Cruce') {
                return '<span class="badge bg-danger">Solo Cruce</span>';
            }
            return '<span class="badge bg-success">Ambos</span>';
        }

        function badgeBoolean(value) {
            return value
                ? '<span class="badge bg-success">Si</span>'
                : '<span class="badge bg-secondary">No</span>';
        }

        function actualizarResumen(summary) {
            document.getElementById('sumIncentivos').textContent = Number(summary?.total_incentivos || 0).toLocaleString('en-US');
            document.getElementById('sumCruce').textContent = Number(summary?.total_cruce_visible || 0).toLocaleString('en-US');
            document.getElementById('sumSoloIncentivos').textContent = Number(summary?.solo_incentivos || 0).toLocaleString('en-US');
            document.getElementById('sumSoloCruce').textContent = Number(summary?.solo_cruce || 0).toLocaleString('en-US');
            document.getElementById('sumAmbos').textContent = Number(summary?.ambos || 0).toLocaleString('en-US');
            document.getElementById('sumActivosOcultos').textContent = Number(summary?.activos_ocultos_en_cruce || 0).toLocaleString('en-US');
        }

        function cargarDatos() {
            const params = {
                sistema: document.getElementById('sistema').value,
                empresa: document.getElementById('empresa').value,
                modo_calculo: document.getElementById('modo_calculo').value,
                filtro_cumplimiento: document.getElementById('filtro_cumplimiento').value,
                diferencia: document.getElementById('diferencia').value,
                fecha_inicio: document.getElementById('fecha_inicio').value,
                fecha_fin: document.getElementById('fecha_fin').value
            };

            if (!params.fecha_inicio || !params.fecha_fin) {
                Swal.fire('Validacion', 'Debes seleccionar fecha inicio y fecha fin.', 'warning');
                return;
            }

            Swal.fire({
                title: 'Cargando comparacion...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            if (table) {
                table.destroy();
                $('#tableDiferencias tbody').empty();
            }

            table = $('#tableDiferencias').DataTable({
                ajax: {
                    url: '/reportes-diferencias-incentivos-cruce/list',
                    type: 'GET',
                    data: params,
                    dataSrc: function(json) {
                        actualizarResumen(json.summary || {});
                        Swal.close();
                        return json.rows || [];
                    },
                    error: function(xhr) {
                        Swal.close();
                        const message = xhr.responseJSON?.message || 'No fue posible cargar la comparacion.';
                        Swal.fire('Error', message, 'error');
                    }
                },
                columns: [
                    { data: 'cedula' },
                    {
                        data: 'clasificacion_label',
                        render: function(data) {
                            return badgeClasificacion(String(data || ''));
                        }
                    },
                    {
                        data: 'en_incentivos',
                        render: function(data) {
                            return badgeBoolean(!!data);
                        }
                    },
                    {
                        data: 'en_cruce',
                        render: function(data) {
                            return badgeBoolean(!!data);
                        }
                    },
                    {
                        data: 'estatus_cruce',
                        render: function(data) {
                            return escapeHtml(data || '-');
                        }
                    },
                    { data: 'empleadoid_maestra' },
                    {
                        data: 'nombre_maestra',
                        render: function(data) {
                            return escapeHtml(data || '');
                        }
                    },
                    {
                        data: 'estado_maestra',
                        render: function(data) {
                            return escapeHtml(data || '');
                        }
                    },
                    {
                        data: 'empresa_incentivo',
                        render: function(data) {
                            return escapeHtml(data || '-');
                        }
                    },
                    {
                        data: 'agencia_incentivo',
                        render: function(data) {
                            return escapeHtml(data || '-');
                        }
                    },
                    {
                        data: 'terminal_incentivo',
                        render: function(data) {
                            return escapeHtml(data || '-');
                        }
                    },
                    { data: 'ultima_fecha_venta' },
                    {
                        data: 'motivo',
                        render: function(data) {
                            return escapeHtml(data || '');
                        }
                    }
                ],
                pageLength: 25,
                lengthMenu: [10, 25, 50, 100],
                responsive: true,
                scrollX: true,
                order: [[1, 'asc'], [11, 'desc']],
                dom: 'Bfrtip',
                buttons: ['copy', 'csv', 'excel', 'print'],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                }
            });
        }

        document.getElementById('btnBuscar').addEventListener('click', cargarDatos);
    </script>
@endsection
