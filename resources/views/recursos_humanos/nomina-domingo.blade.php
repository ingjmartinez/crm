@extends('app')

@section('content')
    <style>
        #tablaNominaDomingo_wrapper .buttons-excel {
            background: #198754 !important;
            border-color: #198754 !important;
            color: #fff !important;
        }

        #tablaNominaDomingo_wrapper .buttons-excel:hover,
        #tablaNominaDomingo_wrapper .buttons-excel:focus {
            background: #157347 !important;
            border-color: #146c43 !important;
            color: #fff !important;
        }
    </style>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Nómina Domingo</h4>
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('recursos-humanos.index') }}">Recursos Humanos</a></li>
                        <li class="breadcrumb-item active">Nómina Domingo</li>
                    </ol>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                <div class="card">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h5 class="card-title mb-1">Cargar documentos de ventas</h5>
                            <p class="text-muted mb-0">Carga los archivos Tradicional y No Tradicional para obtener la última transacción real.</p>
                        </div>
                        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#modalConfiguracion">
                            <i class="ri-settings-3-line me-1"></i> Configurar nómina
                        </button>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('reportes.gestion-agencias.procesar') }}" enctype="multipart/form-data" class="row g-3 align-items-end" id="formCargarNominaDomingo">
                            @csrf
                            <input type="hidden" name="destino" value="nomina_domingo">
                            <div class="col-lg-2">
                                <label for="fecha_nomina" class="form-label">Domingo</label>
                                <input type="date" class="form-control" id="fecha_nomina" name="fecha_nomina" value="{{ $fecha }}" required>
                            </div>
                            <div class="col-lg-4">
                                <label for="tradicional" class="form-label">Tradicional</label>
                                <input type="file" class="form-control" id="tradicional" name="tradicional" accept=".xlsx,.csv,.txt" required>
                            </div>
                            <div class="col-lg-4">
                                <label for="no_tradicional" class="form-label">No Tradicional</label>
                                <input type="file" class="form-control" id="no_tradicional" name="no_tradicional" accept=".xlsx,.csv,.txt" required>
                            </div>
                            <div class="col-lg-2">
                                <button class="btn btn-primary w-100" type="submit" id="btnCargarNominaDomingo"><i class="ri-filter-3-line me-1"></i> Cargar y validar</button>
                            </div>
                        </form>
                        @if (! empty($archivos))
                            <div class="alert alert-info mt-3 mb-0">
                                <strong>Archivos:</strong> {{ $archivos['tradicional'] ?? '-' }} / {{ $archivos['no_tradicional'] ?? '-' }}
                            </div>
                        @endif
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4"><div class="card card-animate"><div class="card-body"><span class="text-muted">Horas requeridas</span><h4>{{ number_format($configuracion['horas_requeridas'], 2) }}</h4></div></div></div>
                    <div class="col-md-4"><div class="card card-animate"><div class="card-body"><span class="text-muted">Monto fijo</span><h4>RD$ {{ number_format($configuracion['monto_fijo'], 2) }}</h4></div></div></div>
                    <div class="col-md-4"><div class="card card-animate"><div class="card-body"><span class="text-muted">Total a pagar</span><h4>RD$ {{ number_format($filas->sum('monto_pagar'), 2) }}</h4></div></div></div>
                </div>

                @if ($filas->isNotEmpty() && $totalConEntrada === 0)
                    <div class="alert alert-warning">
                        <strong>No se encontraron ponches para el {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}.</strong>
                        Las ventas fueron cargadas, pero sin un primer login en <code>asistencias_bet</code> o <code>asistencias_net</code> no es posible calcular las horas trabajadas.
                        <button type="button" class="btn btn-sm btn-warning ms-2" data-bs-toggle="modal" data-bs-target="#modalSinPrimerLogin">
                            Ver registros
                        </button>
                    </div>
                @elseif ($totalSinEntrada > 0)
                    <div class="alert alert-warning">
                        {{ number_format($totalSinEntrada) }} registro(s) no tienen un primer login coincidente por fecha, terminal y cédula; sus horas se muestran en cero.
                        <button type="button" class="btn btn-sm btn-warning ms-2" data-bs-toggle="modal" data-bs-target="#modalSinPrimerLogin">
                            Ver registros
                        </button>
                    </div>
                @endif

                <div class="card">
                    <div class="card-header d-flex flex-wrap align-items-end justify-content-between gap-3">
                        <div><h5 class="card-title mb-1">Datos limpios</h5><p class="text-muted mb-0">La hora de salida se corrige con la última transacción cuando esta es posterior al ponche.</p></div>
                        <form method="GET" action="{{ route('recursos-humanos.nomina-domingo.index') }}" class="d-flex flex-wrap align-items-end gap-2">
                            <input type="hidden" name="fecha" value="{{ $fecha }}">
                            <input type="hidden" name="consultar" value="1">
                            <div>
                                <label for="estatus" class="form-label mb-1">Cumplimiento</label>
                                <select class="form-select" id="estatus" name="estatus">
                                    <option value="todos" @selected($estatus === 'todos')>Todos</option>
                                    <option value="cumple" @selected($estatus === 'cumple')>Cumplen</option>
                                    <option value="no_cumple" @selected($estatus === 'no_cumple')>No cumplen</option>
                                </select>
                            </div>
                            <button class="btn btn-primary" type="submit"><i class="ri-filter-3-line me-1"></i> Filtrar</button>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle w-100" id="tablaNominaDomingo">
                                <thead><tr><th>Terminal</th><th>Cédula</th><th>Empleado</th><th>Horas trabajadas</th><th>Estatus</th><th>Monto a pagar</th></tr></thead>
                                <tbody>
                                    @foreach ($filas as $fila)
                                        <tr title="Entrada: {{ $fila['entrada'] ?? 'Sin entrada' }} | Salida ponche: {{ $fila['salida_ponche'] ?? 'Sin salida' }} | Última transacción: {{ $fila['ultima_transaccion'] ?? 'Sin transacción' }} | Fuente: {{ $fila['fuente_salida'] }}">
                                            <td>{{ $fila['terminal'] }}</td><td>{{ $fila['cedula'] }}</td><td>{{ $fila['empleado'] }}</td>
                                            <td data-order="{{ $fila['horas_trabajadas'] }}">{{ number_format($fila['horas_trabajadas'], 2) }} horas<br><small class="text-muted">Primer login: {{ $fila['entrada'] ? \Carbon\Carbon::parse($fila['entrada'])->format('h:i A') : 'No disponible' }} | Salida: {{ $fila['salida_efectiva'] ? \Carbon\Carbon::parse($fila['salida_efectiva'])->format('h:i A') : 'No disponible' }}</small></td>
                                            <td><span class="badge {{ $fila['estatus'] === 'Cumple' ? 'bg-success' : 'bg-danger' }}">{{ $fila['estatus'] }}</span></td>
                                            <td data-order="{{ $fila['monto_pagar'] }}">RD$ {{ number_format($fila['monto_pagar'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalConfiguracion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="{{ route('recursos-humanos.nomina-domingo.configuracion') }}">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Configurar Nómina Domingo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label" for="horas_requeridas">Horas requeridas</label><input type="number" step="0.01" min="0.01" max="24" class="form-control" id="horas_requeridas" name="horas_requeridas" value="{{ $configuracion['horas_requeridas'] }}" required></div>
                    <div><label class="form-label" for="monto_fijo">Monto fijo</label><div class="input-group"><span class="input-group-text">RD$</span><input type="number" step="0.01" min="0" class="form-control" id="monto_fijo" name="monto_fijo" value="{{ $configuracion['monto_fijo'] }}" required></div></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Guardar configuración</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="modalSinPrimerLogin" tabindex="-1" aria-labelledby="modalSinPrimerLoginLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="modalSinPrimerLoginLabel">Registros sin primer login</h5>
                        <p class="text-muted mb-0">No se encontró un ponche de entrada coincidente para la fecha, terminal y cédula.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle w-100" id="tablaSinPrimerLogin">
                            <thead>
                                <tr><th>Terminal</th><th>Cédula</th><th>Empleado</th><th>Última transacción</th><th>Salida calculada</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($filasSinEntrada as $fila)
                                    <tr>
                                        <td>{{ $fila['terminal'] }}</td>
                                        <td>{{ $fila['cedula'] }}</td>
                                        <td>{{ $fila['empleado'] }}</td>
                                        <td>{{ $fila['ultima_transaccion'] ? \Carbon\Carbon::parse($fila['ultima_transaccion'])->format('d/m/Y h:i A') : 'Sin transacción' }}</td>
                                        <td>{{ $fila['salida_efectiva'] ? \Carbon\Carbon::parse($fila['salida_efectiva'])->format('d/m/Y h:i A') : 'Sin salida disponible' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button></div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const formCargar = document.getElementById('formCargarNominaDomingo');

            if (formCargar) {
                formCargar.addEventListener('submit', function () {
                    const boton = document.getElementById('btnCargarNominaDomingo');

                    if (boton) {
                        boton.disabled = true;
                    }

                    Swal.fire({
                        title: 'Generando data...',
                        text: 'Estamos procesando los archivos. Este proceso puede tardar unos minutos.',
                        icon: 'info',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => Swal.showLoading(),
                    });
                });
            }

            if (window.jQuery && $.fn.DataTable) {
                $('#tablaNominaDomingo').DataTable({
                    pageLength: 25,
                    order: [[3, 'desc']],
                    dom: 'Bfrtip',
                    buttons: [
                        {
                            extend: 'excelHtml5',
                            text: '<i class="ri-file-excel-2-line me-1"></i> Descargar Excel',
                            className: 'btn btn-success mb-3',
                            title: 'Nomina_Domingo_{{ $fecha }}',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5],
                                modifier: { search: 'applied' },
                            },
                        },
                    ],
                    language: { url: '{{ asset('assets/json/es-ES.json') }}' },
                });

                if (document.getElementById('tablaSinPrimerLogin')) {
                    $('#tablaSinPrimerLogin').DataTable({ pageLength: 25, order: [[0, 'asc']], language: { url: '{{ asset('assets/json/es-ES.json') }}' } });
                }
            }
        });
    </script>
@endsection
