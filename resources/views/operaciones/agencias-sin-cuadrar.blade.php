@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Agencia sin cuadrar</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('operaciones.index') }}">Operaciones</a></li>
                                    <li class="breadcrumb-item active">Agencia sin cuadrar</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                @if (isset($errors) && $errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-1">Cargar agencias pendientes de cuadre</h5>
                        <p class="text-muted mb-0">La columna A identifica la ruta, la C el nombre de agencia, la D la terminal y la E el importe asignado.</p>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('operaciones.agencias-sin-cuadrar.procesar') }}" enctype="multipart/form-data" class="row g-3 align-items-end">
                            @csrf
                            <div class="col-lg-8">
                                <label for="archivo_csv" class="form-label">Archivo CSV</label>
                                <input type="file" class="form-control" id="archivo_csv" name="archivo_csv" accept=".csv,text/csv,text/plain" required>
                            </div>
                            <div class="col-lg-4">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="ri-upload-cloud-2-line align-bottom me-1"></i>
                                    Procesar reporte
                                </button>
                            </div>
                        </form>

                        @if (! empty($nombreArchivo))
                            <div class="alert alert-info mt-3 mb-0">Archivo procesado: <strong>{{ $nombreArchivo }}</strong></div>
                        @endif
                    </div>
                </div>

                @if (! empty($resumen))
                    <div class="row">
                        @foreach ([
                            ['Agencias', $resumen['total_agencias'], 'text-primary'],
                            ['Rutas', $resumen['total_rutas'], 'text-info'],
                            ['Depósitos', number_format($resumen['total_depositos'], 2), 'text-success'],
                            ['Retiros', number_format($resumen['total_retiros'], 2), 'text-danger'],
                        ] as [$etiqueta, $valor, $clase])
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <p class="text-muted mb-1">{{ $etiqueta }}</p>
                                        <h4 class="mb-0 {{ $clase }}">{{ $valor }}</h4>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Resumen por ruta y tipo</h5></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle w-100" id="tabla-agencias-sin-cuadrar">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID ruta</th>
                                        <th>Ruta</th>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th class="text-center">Terminales</th>
                                        <th class="text-end">Total asignado</th>
                                        <th class="text-center">Detalle</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($grupos as $indice => $grupo)
                                        <tr>
                                            <td>{{ $grupo['ruta_id'] ?: '-' }}</td>
                                            <td>{{ $grupo['ruta'] ?: '-' }}</td>
                                            <td>{{ $grupo['fecha'] ?: '-' }}</td>
                                            <td>
                                                <span class="badge fs-6 px-2 py-1 {{ $grupo['tipo'] === 'Retiro' ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }}">
                                                    {{ $grupo['tipo'] }}
                                                </span>
                                            </td>
                                            <td class="text-center">{{ number_format($grupo['cantidad_terminales']) }}</td>
                                            <td class="text-end fw-semibold">{{ number_format($grupo['total_monto'], 2) }}</td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-primary btn-ver-terminales" data-grupo="{{ $indice }}" data-bs-toggle="modal" data-bs-target="#modal-terminales-ruta">
                                                    <i class="ri-eye-line align-bottom me-1"></i>
                                                    Ver
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="text-center text-muted">Carga el CSV para construir el reporte.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-terminales-ruta" tabindex="-1" aria-labelledby="titulo-modal-terminales" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="titulo-modal-terminales">Terminales de la ruta</h5>
                        <p class="mb-1 mt-1">
                            <span class="badge bg-primary-subtle text-primary fs-6">
                                Serial de ruta: <span id="serial-modal-terminales">-</span>
                            </span>
                        </p>
                        <p class="text-muted mb-0" id="subtitulo-modal-terminales"></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Terminal</th>
                                    <th>Agencia</th>
                                    <th class="text-end" id="encabezado-monto-terminal">Monto asignado</th>
                                </tr>
                            </thead>
                            <tbody id="detalle-terminales-ruta"></tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="2" class="text-end">Total</th>
                                    <th class="text-end" id="total-terminales-ruta">0.00</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tabla = $('#tabla-agencias-sin-cuadrar');
            const grupos = {{ Illuminate\Support\Js::from($grupos) }};
            const cuerpoDetalle = document.getElementById('detalle-terminales-ruta');
            const tituloModal = document.getElementById('titulo-modal-terminales');
            const serialModal = document.getElementById('serial-modal-terminales');
            const subtituloModal = document.getElementById('subtitulo-modal-terminales');
            const encabezadoMonto = document.getElementById('encabezado-monto-terminal');
            const totalDetalle = document.getElementById('total-terminales-ruta');

            if (!tabla.length || tabla.find('tbody td[colspan]').length) {
                return;
            }

            tabla.DataTable({
                responsive: true,
                pageLength: 25,
                order: [[1, 'asc'], [3, 'asc']],
                columnDefs: [{ orderable: false, searchable: false, targets: 6 }],
                language: {
                    search: 'Buscar:',
                    lengthMenu: 'Mostrar _MENU_ registros',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                    infoEmpty: 'Mostrando 0 a 0 de 0 registros',
                    emptyTable: 'No hay datos disponibles',
                    paginate: { next: 'Siguiente', previous: 'Anterior' }
                }
            });

            document.querySelectorAll('.btn-ver-terminales').forEach(function (boton) {
                boton.addEventListener('click', function () {
                    const grupo = grupos[Number(boton.dataset.grupo)];

                    if (!grupo) {
                        return;
                    }

                    tituloModal.textContent = grupo.ruta || 'Ruta sin nombre';
                    serialModal.textContent = grupo.ruta_id || 'No disponible';
                    subtituloModal.textContent = `${grupo.tipo} · ${grupo.fecha || 'Sin fecha'} · ${grupo.cantidad_terminales} terminales`;
                    encabezadoMonto.textContent = grupo.tipo === 'Retiro' ? 'Monto a retirar' : 'Monto a depositar';
                    totalDetalle.textContent = formatoMonto(grupo.total_monto);
                    cuerpoDetalle.replaceChildren();

                    grupo.terminales.forEach(function (detalle) {
                        const fila = document.createElement('tr');
                        fila.appendChild(crearCelda(detalle.terminal || '-'));
                        fila.appendChild(crearCelda(detalle.agencia || 'Sin nombre'));
                        fila.appendChild(crearCelda(formatoMonto(detalle.monto_asignado), 'text-end fw-semibold'));
                        cuerpoDetalle.appendChild(fila);
                    });
                });
            });

            function crearCelda(texto, clase = '') {
                const celda = document.createElement('td');
                celda.textContent = texto;
                celda.className = clase;

                return celda;
            }

            function formatoMonto(monto) {
                return Number(monto || 0).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
        });
    </script>
@endsection
