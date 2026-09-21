@extends('app')

@section('content')
    <style>
        #tablaNominaDomingo_wrapper .buttons-excel,
        #tablaSinPrimerLogin_wrapper .buttons-excel {
            background: #198754 !important;
            border-color: #198754 !important;
            color: #fff !important;
        }

        #tablaNominaDomingo_wrapper .buttons-excel:hover,
        #tablaNominaDomingo_wrapper .buttons-excel:focus,
        #tablaSinPrimerLogin_wrapper .buttons-excel:hover,
        #tablaSinPrimerLogin_wrapper .buttons-excel:focus {
            background: #157347 !important;
            border-color: #146c43 !important;
            color: #fff !important;
        }

        #tablaNominaDomingo .empleado-sin-maestra {
            --bs-table-accent-bg: #fff3cd;
            background-color: #fff3cd !important;
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
                        <div class="d-flex flex-wrap gap-2">
                            @if ($consultar)
                            <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalCoordinadores">
                                <i class="ri-team-line me-1"></i> Coordinador
                            </button>
                            @endif
                            <button class="btn btn-info" type="button" data-bs-toggle="modal" data-bs-target="#modalConfiguracion">
                                <i class="ri-settings-3-line me-1"></i> Configurar nómina
                            </button>
                            <button class="btn btn-warning" type="button" id="btnAbrirTerminalesExcluidas" data-bs-toggle="modal" data-bs-target="#modalTerminalesExcluidas">
                                <i class="ri-forbid-line me-1"></i> Terminales excluidas <span class="badge bg-dark ms-1" id="cantidadTerminalesExcluidas">0</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('recursos-humanos.nomina-domingo.cargar-ventas') }}" enctype="multipart/form-data" class="row g-3 align-items-end" id="formCargarNominaDomingo">
                            @csrf
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

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-1">Generar reporte</h5>
                        <p class="text-muted mb-0">Selecciona el domingo que deseas consultar. La carga de archivos solo guarda las ventas.</p>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ route('recursos-humanos.nomina-domingo.index') }}" class="row g-3 align-items-end" id="formGenerarNominaDomingo">
                            <input type="hidden" name="consultar" value="1">
                            <div class="col-md-3">
                                <label for="fecha_reporte" class="form-label">Domingo del reporte</label>
                                <input type="date" class="form-control" id="fecha_reporte" name="fecha" value="{{ $fecha }}" required>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-primary w-100" type="submit" id="btnGenerarNominaDomingo">Generar reporte</button>
                            </div>
                        </form>
                    </div>
                </div>

                @if ($consultar)
                @if ($totalIncidencias > 0)
                    <div class="alert alert-warning">
                        {{ number_format($totalIncidencias) }} registro(s) requieren revisión por login faltante, secuencia inválida o venta anterior al primer login. No se incluyen en el pago automático.
                    </div>
                @endif
                @if ($conciliacionVentas !== null)
                    <div class="row g-3 mb-3">
                        @foreach (['tradicional' => 'Tradicional', 'no_tradicional' => 'No Tradicional'] as $tipo => $etiqueta)
                            @php($conciliacion = $conciliacionVentas[$tipo])
                            <div class="col-md-6">
                                <div class="card h-100 mb-0 border {{ abs($conciliacion['diferencia']) < 0.01 ? 'border-success' : 'border-warning' }}">
                                    <div class="card-body py-3">
                                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                            <h6 class="mb-0">Conciliación {{ $etiqueta }}</h6>
                                            <span class="badge {{ abs($conciliacion['diferencia']) < 0.01 ? 'bg-success' : 'bg-warning text-dark' }}">
                                                {{ abs($conciliacion['diferencia']) < 0.01 ? 'Cuadrado' : 'Diferencia' }}
                                            </span>
                                        </div>
                                        <div class="d-flex flex-wrap gap-4">
                                            <div><small class="text-muted d-block">Archivo</small><strong>RD$ {{ number_format($conciliacion['archivo'], 2) }}</strong></div>
                                            <div><small class="text-muted d-block">API</small><strong>RD$ {{ number_format($conciliacion['api'], 2) }}</strong></div>
                                            <div><small class="text-muted d-block">Archivo - API</small><strong>RD$ {{ number_format($conciliacion['diferencia'], 2) }}</strong></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="row">
                    <div class="col-md-4"><div class="card card-animate"><div class="card-body"><span class="text-muted">Horas requeridas</span><h4>{{ (int) floor($configuracion['horas_requeridas']) }} h {{ str_pad((string) round(($configuracion['horas_requeridas'] - floor($configuracion['horas_requeridas'])) * 60), 2, '0', STR_PAD_LEFT) }} min</h4></div></div></div>
                    <div class="col-md-4"><div class="card card-animate"><div class="card-body"><span class="text-muted">Monto fijo</span><h4>RD$ {{ number_format($configuracion['monto_fijo'], 2) }}</h4></div></div></div>
                    <div class="col-md-4"><div class="card card-animate"><div class="card-body"><span class="text-muted">Total a pagar</span><h4>RD$ {{ number_format($filas->sum('monto_pagar'), 2) }}</h4></div></div></div>
                </div>

                @if ($filas->isNotEmpty() && $totalConEntrada === 0)
                    <div class="alert alert-warning">
                        <strong>No se encontraron ponches para el {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}.</strong>
                        Las ventas fueron cargadas, pero sin un primer login asociado no es posible calcular las horas trabajadas.
                        <button type="button" class="btn btn-sm btn-warning ms-2" data-bs-toggle="modal" data-bs-target="#modalSinPrimerLogin">
                            Ver registros
                        </button>
                    </div>
                @elseif ($totalSinEntrada > 0)
                    <div class="alert alert-warning">
                        {{ number_format($totalSinEntrada) }} registro(s) no tienen un primer login asociado por fecha, terminal y usuario de venta; sus horas se muestran en cero.
                        <button type="button" class="btn btn-sm btn-warning ms-2" data-bs-toggle="modal" data-bs-target="#modalSinPrimerLogin">
                            Ver registros
                        </button>
                    </div>
                @endif

                <div class="card">
                    <div class="card-header d-flex flex-wrap align-items-end justify-content-between gap-3">
                        <div><h5 class="card-title mb-1">Datos limpios</h5><p class="text-muted mb-0">Las horas parten del primer login. Si la última venta supera la salida registrada, se usa esa venta más 5 minutos como salida calculada.</p></div>
                        <form method="GET" action="{{ route('recursos-humanos.nomina-domingo.index') }}" class="d-flex flex-wrap align-items-end gap-2" id="formFiltrarNominaDomingo">
                            <input type="hidden" name="fecha" value="{{ $fecha }}">
                            <input type="hidden" name="consultar" value="1">
                            <div>
                                <label for="empresa" class="form-label mb-1">Empresa</label>
                                <select class="form-select" id="empresa" name="empresa">
                                    <option value="">Todas</option>
                                    @foreach ($empresas as $empresaOpcion)
                                        <option value="{{ $empresaOpcion }}" @selected($empresa === $empresaOpcion)>{{ $empresaOpcion }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="estatus" class="form-label mb-1">Cumplimiento</label>
                                <select class="form-select" id="estatus" name="estatus">
                                    <option value="todos" @selected($estatus === 'todos')>Todos</option>
                                    <option value="cumple" @selected($estatus === 'cumple')>Cumplen</option>
                                    <option value="no_cumple" @selected($estatus === 'no_cumple')>No cumplen</option>
                                    <option value="revisar" @selected($estatus === 'revisar')>Revisar</option>
                                </select>
                            </div>
                            <button class="btn btn-primary" type="submit" id="btnFiltrarNominaDomingo"><i class="ri-filter-3-line me-1"></i> Filtrar</button>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle w-100" id="tablaNominaDomingo">
                                <thead><tr><th>Terminal</th><th>Cédula</th><th>Empleado</th><th>Empresa</th><th>Coordinador</th><th>Ponche resumido</th><th>Horas trabajadas (h/min)</th><th>Estatus</th><th>Monto a pagar</th></tr></thead>
                                <tbody>
                                    @foreach ($filas as $fila)
                                        <tr title="Entrada: {{ $fila['entrada'] ?? 'Sin entrada' }} | Salida ponche: {{ $fila['salida_ponche'] ?? 'Sin salida' }} | Última transacción: {{ $fila['ultima_transaccion'] ?? 'Sin transacción' }} | Fuente: {{ $fila['fuente_salida'] }}">
                                            <td>{{ $fila['terminal'] }}</td><td>{{ $fila['cedula'] }}</td><td @class(['empleado-sin-maestra' => ! $fila['coincide_maestra']])>{{ $fila['empleado'] }}@if (! $fila['coincide_maestra'])<br><small>Validar usuario con la maestra de empleados</small>@endif</td><td>{{ $fila['empresa'] }}</td><td>{{ $fila['coordinador'] }}</td>
                                            <td>{{ $fila['entrada'] ? 'Primer login: '.\Carbon\Carbon::parse($fila['entrada'])->format('h:i A') : 'Sin primer login' }} | {{ $fila['salida_efectiva'] ? $fila['fuente_salida'].': '.\Carbon\Carbon::parse($fila['salida_efectiva'])->format('h:i A') : 'Sin último login' }}</td>
                                            <td data-order="{{ $fila['minutos_trabajados'] }}">{{ $fila['horas_trabajadas_formato'] }}</td>
                                            <td><span class="badge {{ $fila['estatus'] === 'Cumple' ? 'bg-success' : ($fila['estatus'] === 'Revisar' ? 'bg-warning text-dark' : 'bg-danger') }}">{{ $fila['estatus'] }}</span>@if ($fila['incidencia'])<br><small class="text-muted">{{ $fila['incidencia'] }}</small>@endif</td>
                                            <td data-order="{{ $fila['monto_pagar'] }}">RD$ {{ number_format($fila['monto_pagar'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalTerminalesExcluidas" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><div><h5 class="modal-title">Terminales excluidas</h5><small class="text-muted">Estas terminales no se incluirán en el cálculo, totales ni reportes de Nómina Domingo.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="archivoTerminalesExcluidas">Archivo Excel o CSV</label><input class="form-control" id="archivoTerminalesExcluidas" type="file" accept=".xlsx,.xls,.csv"><a class="small d-inline-block mt-2" href="{{ route('recursos-humanos.nomina-domingo.terminales-excluidas.plantilla') }}"><i class="ri-download-line"></i> Descargar plantilla</a></div>
                    <div class="col-md-6"><label class="form-label" for="textoTerminalesExcluidas">Agregar manualmente</label><textarea class="form-control" id="textoTerminalesExcluidas" rows="4" placeholder="Una terminal por línea o separadas por coma"></textarea></div>
                </div>
                <div class="d-flex gap-2 mt-3"><button class="btn btn-primary" type="button" id="btnReconocerTerminales"><i class="ri-search-line me-1"></i> Reconocer terminales</button><button class="btn btn-outline-danger" type="button" id="btnLimpiarTerminales"><i class="ri-delete-bin-line me-1"></i> Quitar todas</button></div>
                <div class="alert alert-info mt-3 mb-2">Marca las terminales que deseas excluir. Puedes desmarcar cualquiera para volver a incluirla.</div>
                <div id="resultadoTerminalesExcluidas" class="border rounded p-3"><span class="text-muted">Cargando terminales guardadas...</span></div>
                <div id="terminalesNoEncontradas" class="mt-3"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-warning" id="btnGuardarTerminalesExcluidas">Aplicar exclusiones</button></div>
        </div></div>
    </div>

    <div class="modal fade" id="modalConfiguracion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="{{ route('recursos-humanos.nomina-domingo.configuracion') }}">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Configurar Nómina Domingo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tiempo requerido</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="input-group"><input type="number" min="0" max="24" step="1" class="form-control" id="horas_requeridas_horas" name="horas_requeridas_horas" value="{{ old('horas_requeridas_horas', (int) floor($configuracion['horas_requeridas'])) }}" required><span class="input-group-text">horas</span></div>
                            </div>
                            <div class="col-6">
                                <div class="input-group"><input type="number" min="0" max="59" step="1" class="form-control" id="horas_requeridas_minutos" name="horas_requeridas_minutos" value="{{ old('horas_requeridas_minutos', (int) round(($configuracion['horas_requeridas'] - floor($configuracion['horas_requeridas'])) * 60)) }}" required><span class="input-group-text">minutos</span></div>
                            </div>
                        </div>
                        <small class="text-muted">Los minutos deben estar entre 0 y 59.</small>
                    </div>
                    <div><label class="form-label" for="monto_fijo">Monto fijo</label><div class="input-group"><span class="input-group-text">RD$</span><input type="number" step="0.01" min="0" class="form-control" id="monto_fijo" name="monto_fijo" value="{{ $configuracion['monto_fijo'] }}" required></div></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Guardar configuración</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="modalCoordinadores" tabindex="-1" aria-labelledby="modalCoordinadoresLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="modalCoordinadoresLabel">Resumen por coordinador</h5>
                        <p class="text-muted mb-0">Una agencia cumple cuando al menos uno de sus empleados cumple la jornada.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table id="tablaCoordinadoresNomina" class="table table-bordered table-striped align-middle w-100">
                            <thead class="table-light">
                                <tr>
                                    <th>Coordinador</th>
                                    <th class="text-center">Agencias evaluadas</th>
                                    <th class="text-center">Agencias cumplieron</th>
                                    <th class="text-center">Agencias no cumplieron</th>
                                    <th class="text-center">Empleados cumplieron</th>
                                    <th class="text-center">No cumplen o revisar</th>
                                    <th class="text-center">Telegram</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($resumenCoordinadores as $indice => $coordinador)
                                    <tr>
                                        <td class="fw-semibold">{{ $coordinador['coordinador'] }}</td>
                                        <td class="text-center">{{ number_format($coordinador['agencias_asignadas']) }}</td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-link text-success fw-semibold p-0 coordinador-detalle-trigger" data-indice="{{ $indice }}" data-tipo="cumplieron">
                                                {{ number_format($coordinador['agencias_cumplieron']) }}
                                            </button>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-link text-danger fw-semibold p-0 coordinador-detalle-trigger" data-indice="{{ $indice }}" data-tipo="no_cumplieron">
                                                {{ number_format($coordinador['agencias_no_cumplieron']) }}
                                            </button>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-link text-success fw-semibold p-0 coordinador-detalle-trigger" data-indice="{{ $indice }}" data-tipo="empleados_cumplieron">
                                                {{ number_format($coordinador['empleados_cumplieron']) }}
                                            </button>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-link text-danger fw-semibold p-0 coordinador-detalle-trigger" data-indice="{{ $indice }}" data-tipo="empleados_no_cumplieron">
                                                {{ number_format($coordinador['empleados_no_cumplieron']) }}
                                            </button>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-info text-white telegram-coordinador-trigger" data-indice="{{ $indice }}">
                                                <i class="ri-telegram-line me-1"></i> Enviar
                                            </button>
                                        </td>
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

    <div class="modal fade" id="modalDetalleCoordinadorNomina" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div><h5 class="modal-title" id="tituloDetalleCoordinadorNomina">Detalle de agencias</h5><small class="text-muted" id="subtituloDetalleCoordinadorNomina"></small></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table id="tablaDetalleCoordinadorNomina" class="table table-bordered table-striped align-middle w-100">
                            <thead class="table-light" id="encabezadoDetalleCoordinadorNomina"></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalTelegramCoordinador" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form id="formTelegramCoordinador">
                    <div class="modal-header">
                        <div><h5 class="modal-title">Enviar nómina por Telegram</h5><small class="text-muted" id="telegramCoordinadorNombre"></small></div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="telegramCoordinadorChatId">Chat ID o username</label>
                            <input type="text" class="form-control" id="telegramCoordinadorChatId" placeholder="Ej: 123456789 o @usuario" maxlength="255" required>
                            <small class="text-muted">El coordinador debe haber iniciado una conversación con el bot.</small>
                        </div>
                        <div>
                            <label class="form-label" for="telegramCoordinadorVistaPrevia">Archivos a enviar</label>
                            <textarea class="form-control" id="telegramCoordinadorVistaPrevia" rows="6" readonly></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-info text-white" id="btnEnviarTelegramCoordinador"><i class="ri-telegram-line me-1"></i> Enviar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalSinPrimerLogin" tabindex="-1" aria-labelledby="modalSinPrimerLoginLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="modalSinPrimerLoginLabel">Registros sin primer login</h5>
                        <p class="text-muted mb-0">No se encontró un primer login asociado al usuario de venta en esa terminal y fecha.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle w-100" id="tablaSinPrimerLogin">
                            <thead>
                                <tr><th>Terminal</th><th>Cédula</th><th>ID</th><th>Empleado</th><th>Ventas</th><th>Primera venta</th><th>Última venta</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($filasSinEntrada as $fila)
                                    <tr>
                                        <td>{{ $fila['terminal'] }}</td>
                                        <td>{{ $fila['origen_identidad'] === 'Usuario de venta sin verificar' ? '' : $fila['cedula'] }}</td>
                                        <td>{{ $fila['usuario_venta'] ?? '-' }}</td>
                                        <td>{{ $fila['empleado'] }}</td>
                                        <td>{{ number_format($fila['tradicional_cantidad'] + $fila['no_tradicional_cantidad']) }} ventas<br><small>RD$ {{ number_format($fila['tradicional_monto'] + $fila['no_tradicional_monto'], 2) }}</small></td>
                                        <td>{{ $fila['primera_transaccion'] ? \Carbon\Carbon::parse($fila['primera_transaccion'])->format('d/m/Y h:i A') : 'Sin transacción' }}</td>
                                        <td>{{ $fila['ultima_transaccion'] ? \Carbon\Carbon::parse($fila['ultima_transaccion'])->format('d/m/Y h:i A') : 'Sin transacción' }}</td>
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
            const formGenerar = document.getElementById('formGenerarNominaDomingo');
            const formFiltrar = document.getElementById('formFiltrarNominaDomingo');
            const urlsTerminales = {
                listar: @json(route('recursos-humanos.nomina-domingo.terminales-excluidas.index')),
                reconocer: @json(route('recursos-humanos.nomina-domingo.terminales-excluidas.reconocer')),
                guardar: @json(route('recursos-humanos.nomina-domingo.terminales-excluidas.store')),
            };
            let terminalesSeleccionadas = new Set();
            const contenedorTerminales = document.getElementById('resultadoTerminalesExcluidas');
            const tokenCsrf = document.querySelector('meta[name="csrf-token"]').content;
            const escaparTerminal = (valor) => String(valor ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
            const renderTerminales = () => {
                const terminales = [...terminalesSeleccionadas].sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
                document.getElementById('cantidadTerminalesExcluidas').textContent = terminales.length;
                contenedorTerminales.innerHTML = terminales.length
                    ? `<div class="row g-2">${terminales.map((terminal) => `<div class="col-sm-6 col-md-4"><label class="form-check border rounded p-2 w-100"><input class="form-check-input ms-0 me-2 terminal-excluida-check" type="checkbox" value="${escaparTerminal(terminal)}" checked><span>${escaparTerminal(terminal)}</span></label></div>`).join('')}</div>`
                    : '<span class="text-muted">No hay terminales excluidas.</span>';
            };
            const cargarTerminales = async () => {
                const response = await fetch(urlsTerminales.listar, { headers: { Accept: 'application/json' } });
                const data = await response.json();
                terminalesSeleccionadas = new Set(data.terminales || []);
                renderTerminales();
            };
            const guardarTerminales = async () => {
                const response = await fetch(urlsTerminales.guardar, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': tokenCsrf }, body: JSON.stringify({ terminales: [...terminalesSeleccionadas] }) });
                const data = await response.json();
                if (! response.ok) throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'No se pudieron guardar las terminales.');
                return data;
            };

            cargarTerminales().catch(() => { contenedorTerminales.innerHTML = '<span class="text-danger">No se pudieron cargar las terminales.</span>'; });
            contenedorTerminales?.addEventListener('change', (event) => { if (event.target.matches('.terminal-excluida-check') && ! event.target.checked) { terminalesSeleccionadas.delete(event.target.value); renderTerminales(); } });
            document.getElementById('btnReconocerTerminales')?.addEventListener('click', async function () {
                const formData = new FormData();
                const archivo = document.getElementById('archivoTerminalesExcluidas').files[0];
                if (archivo) formData.append('file', archivo);
                formData.append('terminales_manual', document.getElementById('textoTerminalesExcluidas').value);
                this.disabled = true;
                try {
                    const response = await fetch(urlsTerminales.reconocer, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': tokenCsrf }, body: formData });
                    const data = await response.json();
                    if (! response.ok) throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'No se pudieron reconocer las terminales.');
                    (data.terminales_encontradas || []).forEach((terminal) => terminalesSeleccionadas.add(terminal));
                    renderTerminales();
                    document.getElementById('terminalesNoEncontradas').innerHTML = data.terminales_no_encontradas?.length ? `<div class="alert alert-warning mb-0"><strong>No encontradas:</strong> ${data.terminales_no_encontradas.map(escaparTerminal).join(', ')}</div>` : '<div class="alert alert-success mb-0">Todas las terminales fueron reconocidas.</div>';
                } catch (error) { Swal.fire({ icon: 'error', title: 'No se pudo reconocer', text: error.message }); } finally { this.disabled = false; }
            });
            document.getElementById('btnLimpiarTerminales')?.addEventListener('click', () => { terminalesSeleccionadas.clear(); renderTerminales(); });
            document.getElementById('btnGuardarTerminalesExcluidas')?.addEventListener('click', async function () {
                this.disabled = true;
                try { const data = await guardarTerminales(); await Swal.fire({ icon: 'success', title: 'Exclusiones actualizadas', text: data.message }); window.location.reload(); }
                catch (error) { Swal.fire({ icon: 'error', title: 'No se pudo guardar', text: error.message }); } finally { this.disabled = false; }
            });

            formFiltrar?.addEventListener('submit', function () {
                document.getElementById('btnFiltrarNominaDomingo').disabled = true;
                Swal.fire({
                    title: 'Aplicando el filtro...',
                    icon: 'info',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading(),
                });
            });

            formGenerar?.addEventListener('submit', function () {
                document.getElementById('btnGenerarNominaDomingo').disabled = true;
                Swal.fire({
                    title: 'Generando reporte...',
                    text: 'Estamos calculando la nómina del domingo seleccionado.',
                    icon: 'info',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading(),
                });
            });

            if (formCargar) {
                formCargar.addEventListener('submit', function () {
                    const boton = document.getElementById('btnCargarNominaDomingo');

                    if (boton) {
                        boton.disabled = true;
                    }

                    Swal.fire({
                        title: 'Guardando ventas...',
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
                const resumenCoordinadores = @json($resumenCoordinadores);
                const telegramEnviarUrl = @json(route('recursos-humanos.nomina-domingo.enviar-telegram'));
                let tablaCoordinadores = null;
                let tablaDetalleCoordinador = null;
                let coordinadorTelegramSeleccionado = null;
                const escaparHtml = (valor) => String(valor ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');

                document.getElementById('modalCoordinadores')?.addEventListener('shown.bs.modal', function () {
                    if (! tablaCoordinadores) {
                        tablaCoordinadores = $('#tablaCoordinadoresNomina').DataTable({
                            pageLength: 25,
                            order: [[1, 'desc']],
                            dom: 'Bfrtip',
                            buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
                            language: { url: '{{ asset('assets/json/es-ES.json') }}' },
                        });
                    } else {
                        tablaCoordinadores.columns.adjust();
                    }
                });

                document.addEventListener('click', function (event) {
                    const telegramTrigger = event.target.closest('.telegram-coordinador-trigger');
                    if (telegramTrigger) {
                        coordinadorTelegramSeleccionado = resumenCoordinadores[Number(telegramTrigger.dataset.indice)];
                        const lineas = [
                            `Coordinador: ${coordinadorTelegramSeleccionado.coordinador}`,
                            `Fecha: {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}`,
                            '',
                            `📄 PDF 1: Empleados que cumplieron (${coordinadorTelegramSeleccionado.empleados_cumplieron})`,
                            `📄 PDF 2: No cumplen o requieren revisión (${coordinadorTelegramSeleccionado.empleados_no_cumplieron})`,
                        ];
                        document.getElementById('telegramCoordinadorNombre').textContent = coordinadorTelegramSeleccionado.coordinador;
                        document.getElementById('telegramCoordinadorVistaPrevia').value = lineas.join('\n');
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCoordinadores')).hide();
                        setTimeout(() => bootstrap.Modal.getOrCreateInstance(document.getElementById('modalTelegramCoordinador')).show(), 200);

                        return;
                    }

                    const trigger = event.target.closest('.coordinador-detalle-trigger');
                    if (! trigger) {
                        return;
                    }

                    const coordinador = resumenCoordinadores[Number(trigger.dataset.indice)];
                    const tipo = trigger.dataset.tipo;
                    const esEmpleado = tipo.startsWith('empleados_');
                    const cumple = tipo.endsWith('cumplieron') && ! tipo.endsWith('no_cumplieron');
                    const filas = coordinador[`detalle_${tipo === 'cumplieron' || tipo === 'no_cumplieron' ? `agencias_${tipo}` : tipo}`];
                    const encabezado = document.getElementById('encabezadoDetalleCoordinadorNomina');
                    const tbody = document.querySelector('#tablaDetalleCoordinadorNomina tbody');

                    if (tablaDetalleCoordinador) {
                        tablaDetalleCoordinador.destroy();
                        tablaDetalleCoordinador = null;
                    }

                    document.getElementById('tituloDetalleCoordinadorNomina').textContent = esEmpleado
                        ? (cumple ? 'Empleados que cumplieron' : 'No cumplen o requieren revisión')
                        : (cumple ? 'Agencias que cumplieron' : 'Agencias que no cumplieron');
                    document.getElementById('subtituloDetalleCoordinadorNomina').textContent = coordinador.coordinador;
                    encabezado.innerHTML = esEmpleado
                        ? '<tr><th>Terminal</th><th>Cédula</th><th>Empleado</th><th class="text-end">Horas trabajadas (h/min)</th><th>Estado</th></tr>'
                        : '<tr><th>Terminal</th><th>Empresa</th><th class="text-center">Empleados evaluados</th></tr>';
                    tbody.innerHTML = esEmpleado
                        ? filas.map((fila) => `<tr><td>${escaparHtml(fila.terminal)}</td><td>${escaparHtml(fila.cedula)}</td><td>${escaparHtml(fila.empleado)}</td><td class="text-end" data-order="${Number(fila.minutos_trabajados)}">${escaparHtml(fila.horas_trabajadas_formato)}</td><td><span class="badge ${fila.estatus === 'Cumple' ? 'bg-success' : (fila.estatus === 'Revisar' ? 'bg-warning text-dark' : 'bg-danger')}">${escaparHtml(fila.estatus)}</span></td></tr>`).join('')
                        : filas.map((fila) => `<tr><td>${escaparHtml(fila.terminal)}</td><td>${escaparHtml(fila.empresa)}</td><td class="text-center">${Number(fila.empleados)}</td></tr>`).join('');

                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalleCoordinadorNomina')).show();
                    setTimeout(() => {
                        tablaDetalleCoordinador = $('#tablaDetalleCoordinadorNomina').DataTable({
                            pageLength: 25,
                            order: [[0, 'asc']],
                            dom: 'Bfrtip',
                            buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
                            language: { url: '{{ asset('assets/json/es-ES.json') }}' },
                        });
                    }, 180);
                });

                document.getElementById('formTelegramCoordinador')?.addEventListener('submit', async function (event) {
                    event.preventDefault();
                    if (! coordinadorTelegramSeleccionado) {
                        return;
                    }

                    const boton = document.getElementById('btnEnviarTelegramCoordinador');
                    boton.disabled = true;
                    Swal.fire({ title: 'Enviando reporte...', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });

                    try {
                        const response = await fetch(telegramEnviarUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({
                                chat_id: document.getElementById('telegramCoordinadorChatId').value.trim(),
                                coordinador: coordinadorTelegramSeleccionado.coordinador,
                                fecha: @json($fecha),
                                empresa: @json($empresa),
                            }),
                        });
                        const data = await response.json();
                        if (! response.ok) {
                            throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'No se pudo enviar el reporte.');
                        }

                        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalTelegramCoordinador')).hide();
                        Swal.fire({ icon: 'success', title: 'Reporte enviado', text: data.message });
                    } catch (error) {
                        Swal.fire({ icon: 'error', title: 'Error al enviar', text: error.message });
                    } finally {
                        boton.disabled = false;
                    }
                });

                if (document.getElementById('tablaNominaDomingo')) {
                    $('#tablaNominaDomingo').DataTable({
                        pageLength: 25,
                        order: [[6, 'desc']],
                        dom: 'Bfrtip',
                        buttons: [
                            {
                                extend: 'excelHtml5',
                                text: '<i class="ri-file-excel-2-line me-1"></i> Descargar Excel',
                                className: 'btn btn-success mb-3',
                                title: 'Nomina_Domingo_{{ $fecha }}',
                                exportOptions: {
                                    columns: [0, 1, 2, 3, 4, 5, 6, 7, 8],
                                    modifier: { search: 'applied', order: 'applied', page: 'all' },
                                },
                            },
                        ],
                        language: { url: '{{ asset('assets/json/es-ES.json') }}' },
                    });
                }

                if (document.getElementById('tablaSinPrimerLogin')) {
                    const tablaSinPrimerLogin = $('#tablaSinPrimerLogin').DataTable({
                        pageLength: 25,
                        order: [[0, 'asc']],
                        dom: 'Bfrtip',
                        buttons: [{
                            extend: 'excelHtml5',
                            text: '<i class="ri-file-excel-2-line me-1"></i> Descargar Excel',
                            className: 'btn btn-success mb-3',
                            title: 'Nomina_Domingo_Sin_Primer_Login_{{ $fecha }}',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6],
                                modifier: { search: 'applied', order: 'applied', page: 'all' },
                            },
                        }],
                        language: { url: '{{ asset('assets/json/es-ES.json') }}' },
                    });
                    document.getElementById('modalSinPrimerLogin')?.addEventListener('shown.bs.modal', function () {
                        tablaSinPrimerLogin.columns.adjust();
                    });
                }
            }
        });
    </script>
@endsection
