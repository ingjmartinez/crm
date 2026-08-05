@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <div>
                                <h4 class="mb-sm-0">Movimientos por Ruta V2</h4>
                                <p class="text-muted mb-0">Control persistente de retiro neto y depósitos bancarios por fecha.</p>
                            </div>
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('operaciones.index') }}">Operaciones</a></li>
                                <li class="breadcrumb-item active">Movimientos por Ruta V2</li>
                            </ol>
                        </div>
                    </div>
                </div>

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="row g-3 mb-4">
                    <div class="col-xl-4">
                        <div class="card h-100 mb-0">
                            <div class="card-header">
                                <h5 class="card-title mb-1">Actualizar movimientos por fecha</h5>
                                <p class="text-muted mb-0">La fecha seleccionada debe coincidir con la del documento.</p>
                            </div>
                            <div class="card-body d-flex align-items-center">
                                <form method="POST" action="{{ route('operaciones.movimientos-rutas-v2.procesar') }}" enctype="multipart/form-data" class="row g-2 align-items-end w-100">
                                    @csrf
                                    <div class="col-md-5">
                                        <label for="fecha_reporte" class="form-label">Fecha del reporte</label>
                                        <input type="date" class="form-control" id="fecha_reporte" name="fecha_reporte" value="{{ old('fecha_reporte', $fecha ?? now()->toDateString()) }}" required>
                                    </div>
                                    <div class="col-md-7">
                                        <label for="archivo_csv" class="form-label">Documento CSV</label>
                                        <input type="file" class="form-control" id="archivo_csv" name="archivo_csv" accept=".csv,.txt,text/csv,text/plain" required>
                                    </div>
                                    <div class="col-12 d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="ri-upload-cloud-2-line me-1"></i>Importar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4">
                        <div class="card h-100 mb-0">
                            <div class="card-header"><h5 class="card-title mb-0">Consultar día</h5></div>
                            <div class="card-body">
                                <form method="GET" action="{{ route('operaciones.movimientos-rutas-v2') }}" class="row g-3 align-items-end">
                                    <div class="col-md-8">
                                        <label for="fecha" class="form-label">Fecha del movimiento</label>
                                        <input type="date" class="form-control" id="fecha" name="fecha" value="{{ $fecha }}">
                                    </div>
                                    <div class="col-md-4 d-grid">
                                        <button type="submit" class="btn btn-outline-primary">Consultar</button>
                                    </div>
                                </form>
                                @if ($fechasDisponibles !== [])
                                    <div class="form-text mt-2">Último día disponible: {{ \Carbon\Carbon::parse($fechasDisponibles[0])->format('d/m/Y') }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4">
                        <div class="card h-100 mb-0">
                            <div class="card-header">
                                <h5 class="card-title mb-1">Rendimiento de ruta</h5>
                                <p class="text-muted mb-0">Cumplimiento del neto esperado depositado en banco.</p>
                            </div>
                            <div class="card-body d-flex flex-column justify-content-center">
                                @php
                                    $cumplimientoDepositos = (float) $resumen['cumplimiento_depositos'];
                                    $anchoCumplimiento = min(max($cumplimientoDepositos, 0), 100);
                                @endphp
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="text-muted fw-semibold mb-1">Neto esperado</div>
                                        <div class="fs-4 fw-bold text-warning lh-sm" id="rendimiento-neto-esperado">
                                            RD$ {{ number_format((float) $resumen['neto_esperado'], 2) }}
                                        </div>
                                    </div>
                                    <div class="col-6 text-end">
                                        <div class="text-muted fw-semibold mb-1">Depositado en banco</div>
                                        <div class="fs-4 fw-bold text-success lh-sm" id="rendimiento-depositado-banco">
                                            RD$ {{ number_format((float) $resumen['depositado_banco'], 2) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="progress position-relative" style="height: 28px;" role="progressbar" aria-label="Cumplimiento de depósitos" aria-valuenow="{{ round($cumplimientoDepositos, 1) }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar bg-success" style="width: {{ $anchoCumplimiento }}%"></div>
                                    <span class="position-absolute top-50 start-50 translate-middle fs-5 fw-bold {{ $anchoCumplimiento >= 55 ? 'text-white' : 'text-dark' }}" id="rendimiento-porcentaje">
                                        {{ number_format($cumplimientoDepositos, 1) }}%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    @php
                        $tarjetas = [
                            ['Rutas', $resumen['rutas'], false, 'text-primary'],
                            ['Transacciones', $resumen['transacciones'], false, 'text-info'],
                            ['Neto esperado', $resumen['neto_esperado'], true, 'text-warning'],
                            ['Depositado en banco', $resumen['depositado_banco'], true, 'text-success'],
                            ['Gastos de ruta', $resumen['gastos_ruta'], true, 'text-primary'],
                            ['Pendiente', $resumen['pendiente'], true, $resumen['pendiente'] > 0 ? 'text-danger' : 'text-success'],
                        ];
                    @endphp
                    @foreach ($tarjetas as [$titulo, $valor, $esMoneda, $color])
                        <div class="col-xl col-md-4 col-sm-6">
                            <div class="card h-100 mb-0"><div class="card-body">
                                <p class="text-muted mb-2">{{ $titulo }}</p>
                                <h4 class="mb-0 {{ $color }}">{{ $esMoneda ? 'RD$ '.number_format((float) $valor, 2) : number_format((int) $valor) }}</h4>
                            </div></div>
                        </div>
                    @endforeach
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <div>
                            <h5 class="card-title mb-1">Conciliación por ruta</h5>
                            <p class="text-muted mb-0">Fecha: {{ $fecha ? \Carbon\Carbon::parse($fecha)->format('d/m/Y') : 'sin información' }}</p>
                        </div>
                        <span class="badge bg-light text-dark border">Los depósitos de esta V2 son independientes</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle w-100" id="tabla-movimientos-rutas-v2">
                                <thead class="table-light">
                                    <tr>
                                        <th>Ruta</th>
                                        <th class="text-end">Transacciones</th>
                                        <th class="text-end">Depósitos CSV</th>
                                        <th class="text-end">Retiros</th>
                                        <th class="text-end">Neto esperado</th>
                                        <th class="text-end">Depositado banco</th>
                                        <th class="text-end">Gastos de ruta</th>
                                        <th class="text-end">Pendiente</th>
                                        <th class="text-end">Balance pendiente</th>
                                        <th class="text-end">Cumplimiento</th>
                                        <th>Estado</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rutas as $ruta)
                                        @php
                                            $estadoConfig = match ($ruta['estado']) {
                                                'conciliada' => ['success', 'Conciliada'],
                                                'parcial' => ['warning', 'Parcial'],
                                                'excedida' => ['info', 'Excedida'],
                                                default => ['danger', 'Pendiente'],
                                            };
                                        @endphp
                                        <tr>
                                            <td class="fw-semibold">{{ $ruta['ruta'] }}</td>
                                            <td class="text-end">{{ number_format($ruta['transacciones']) }}</td>
                                            <td class="text-end">RD$ {{ number_format($ruta['depositos_csv'], 2) }}</td>
                                            <td class="text-end">RD$ {{ number_format($ruta['retiros'], 2) }}</td>
                                            <td class="text-end fw-semibold">RD$ {{ number_format($ruta['neto_esperado'], 2) }}</td>
                                            <td class="text-end text-success">RD$ {{ number_format($ruta['depositado_banco'], 2) }}</td>
                                            <td class="text-end text-primary">RD$ {{ number_format($ruta['gastos_ruta'], 2) }}</td>
                                            <td class="text-end {{ $ruta['pendiente'] > 0 ? 'text-danger' : 'text-success' }}">RD$ {{ number_format($ruta['pendiente'], 2) }}</td>
                                            <td class="text-end fw-bold {{ $ruta['balance_pendiente'] > 0 ? 'text-danger' : 'text-success' }}">RD$ {{ number_format($ruta['balance_pendiente'], 2) }}</td>
                                            <td class="text-end">{{ number_format($ruta['cumplimiento'], 1) }}%</td>
                                            <td><span class="badge bg-{{ $estadoConfig[0] }}-subtle text-{{ $estadoConfig[0] }}" style="font-size: inherit;">{{ $estadoConfig[1] }}</span></td>
                                            <td class="text-center text-nowrap">
                                                <button type="button" class="btn btn-sm btn-success btn-elegir-aplicacion"
                                                    data-ruta-key="{{ $ruta['ruta_key'] }}" data-ruta="{{ $ruta['ruta'] }}"
                                                    data-neto="{{ $ruta['neto_esperado'] }}" data-depositado="{{ $ruta['depositado_banco'] }}"
                                                    data-gastos="{{ $ruta['gastos_ruta'] }}" data-pendiente="{{ $ruta['pendiente'] }}">
                                                    <i class="ri-bank-card-line"></i> Aplicar
                                                </button>
                                                <button type="button" class="btn btn-sm btn-soft-primary btn-ver-detalle"
                                                    data-ruta-key="{{ $ruta['ruta_key'] }}" data-ruta="{{ $ruta['ruta'] }}">
                                                    <i class="ri-eye-line"></i> Ver
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($rutas->isEmpty())
                            <div class="text-center text-muted py-4">No hay movimientos guardados para la fecha seleccionada.</div>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-1">Últimas importaciones del día</h5>
                        <p class="text-muted mb-0">{{ $fecha ? \Carbon\Carbon::parse($fecha)->format('d/m/Y') : 'Sin fecha seleccionada' }}</p>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Archivo</th><th>Período reemplazado</th><th>Filas</th><th>Usuario</th><th>Importado</th></tr></thead>
                            <tbody>
                                @forelse ($importaciones as $importacion)
                                    <tr>
                                        <td>{{ $importacion->nombre_archivo }}</td>
                                        <td>{{ $importacion->fecha_desde->format('d/m/Y') }} al {{ $importacion->fecha_hasta->format('d/m/Y') }}</td>
                                        <td>{{ number_format($importacion->filas_aceptadas) }}</td>
                                        <td>{{ $importacion->usuario?->name ?? 'Sistema' }}</td>
                                        <td>{{ $importacion->created_at->format('d/m/Y h:i A') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted">Aún no hay importaciones.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-elegir-aplicacion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div><h5 class="modal-title">¿Qué deseas aplicar?</h5><p class="text-muted mb-0" id="aplicacion-ruta-titulo"></p></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body d-grid gap-3">
                    <button type="button" class="btn btn-success btn-lg" id="elegir-deposito">
                        <i class="ri-bank-card-line me-1"></i> Depósito bancario
                    </button>
                    <button type="button" class="btn btn-primary btn-lg" id="elegir-gasto">
                        <i class="ri-receipt-line me-1"></i> Gasto de ruta
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-aplicar-deposito" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST" action="{{ route('operaciones.movimientos-rutas-v2.depositos.guardar') }}" enctype="multipart/form-data" class="modal-content">
                @csrf
                <div class="modal-header">
                    <div><h5 class="modal-title">Aplicar depósito bancario</h5><p class="text-muted mb-0" id="deposito-ruta-titulo"></p></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="fecha" value="{{ $fecha }}">
                    <input type="hidden" name="ruta_key" id="deposito-ruta-key">
                    <input type="hidden" name="ruta" id="deposito-ruta">
                    <div class="alert alert-light border" id="deposito-resumen"></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Monto depositado</label><input type="number" name="monto" class="form-control" min="0.01" step="0.01" required></div>
                        <div class="col-md-6"><label class="form-label">Banco</label><input type="text" name="banco" class="form-control" list="lista-bancos-v2" required></div>
                        <div class="col-md-6"><label class="form-label">Referencia bancaria</label><input type="text" name="referencia" class="form-control" maxlength="120"></div>
                        <div class="col-md-6"><label class="form-label">Comprobante</label><input type="file" name="comprobante" class="form-control" id="comprobante-deposito" accept="image/*"></div>
                        <div class="col-12">
                            <div class="border border-2 rounded p-3 text-center bg-light" id="zona-pegar-deposito" tabindex="0" role="button" style="border-style: dashed !important; cursor: pointer;">
                                <i class="ri-clipboard-line fs-4 text-success"></i>
                                <div class="fw-semibold" id="texto-pegar-deposito">Haz clic aquí y presiona Ctrl+V para pegar una captura</div>
                                <small class="text-muted">También puedes continuar seleccionando el archivo arriba.</small>
                                <img class="img-fluid rounded mt-2 d-none mx-auto" id="vista-previa-deposito" alt="Vista previa del comprobante pegado" style="max-height: 180px;">
                            </div>
                        </div>
                        <div class="col-12"><label class="form-label">Observación</label><textarea name="observacion" class="form-control" rows="3" maxlength="1000"></textarea></div>
                    </div>
                    <datalist id="lista-bancos-v2">@foreach ($bancos as $banco)<option value="{{ $banco->nombre }}">@endforeach</datalist>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-success">Guardar depósito</button></div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="modal-aplicar-gasto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST" action="{{ route('operaciones.movimientos-rutas-v2.gastos.guardar') }}" enctype="multipart/form-data" class="modal-content">
                @csrf
                <div class="modal-header">
                    <div><h5 class="modal-title">Aplicar gasto de ruta</h5><p class="text-muted mb-0" id="gasto-ruta-titulo"></p></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="fecha" value="{{ $fecha }}">
                    <input type="hidden" name="ruta_key" id="gasto-ruta-key">
                    <input type="hidden" name="ruta" id="gasto-ruta">
                    <div class="alert alert-light border" id="gasto-resumen"></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Monto del gasto</label><input type="number" name="monto" class="form-control" min="0.01" step="0.01" required></div>
                        <div class="col-md-6"><label class="form-label">Concepto</label><input type="text" name="concepto" class="form-control" maxlength="150" placeholder="Ej.: combustible, peaje o reparación" required></div>
                        <div class="col-md-6"><label class="form-label">Voucher o comprobante</label><input type="file" name="comprobante" class="form-control" id="comprobante-gasto" accept="image/*"></div>
                        <div class="col-12">
                            <div class="border border-2 rounded p-3 text-center bg-light" id="zona-pegar-gasto" tabindex="0" role="button" style="border-style: dashed !important; cursor: pointer;">
                                <i class="ri-clipboard-line fs-4 text-primary"></i>
                                <div class="fw-semibold" id="texto-pegar-gasto">Haz clic aquí y presiona Ctrl+V para pegar una captura</div>
                                <small class="text-muted">También puedes continuar seleccionando el archivo arriba.</small>
                                <img class="img-fluid rounded mt-2 d-none mx-auto" id="vista-previa-gasto" alt="Vista previa del comprobante pegado" style="max-height: 180px;">
                            </div>
                        </div>
                        <div class="col-12"><label class="form-label">Observación</label><textarea name="observacion" class="form-control" rows="3" maxlength="1000"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar gasto</button></div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="modal-detalle-ruta-v2" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="detalle-ruta-titulo">Detalle de ruta</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <h6>Depósitos bancarios aplicados</h6>
                    <div class="table-responsive mb-4"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Registrado</th><th>Banco</th><th>Referencia</th><th class="text-end">Monto</th><th>Usuario</th><th>Comprobante</th><th class="text-center">Acciones</th></tr></thead><tbody id="detalle-depositos-body"></tbody></table></div>
                    <h6>Gastos de ruta aplicados</h6>
                    <div class="table-responsive mb-4"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Registrado</th><th>Concepto</th><th class="text-end">Monto</th><th>Usuario</th><th>Comprobante</th><th class="text-center">Acciones</th></tr></thead><tbody id="detalle-gastos-body"></tbody></table></div>
                    <h6>Transacciones del CSV</h6>
                    <div class="table-responsive"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Transacción</th><th>Terminal</th><th>Agencia</th><th>Tipo</th><th class="text-end">Monto</th></tr></thead><tbody id="detalle-transacciones-body"></tbody></table></div>
                </div>
                <div class="modal-footer">
                    <a href="#" target="_blank" class="btn btn-danger" id="btn-informe-ruta-pdf">
                        <i class="ri-file-pdf-2-line me-1"></i> Informe PDF
                    </a>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const fecha = @json($fecha);
            const csrfToken = @json(csrf_token());
            const errorFechaImportacion = @json($errors->first('fecha_reporte'));
            const detalleUrl = @json(route('operaciones.movimientos-rutas-v2.detalle'));
            const pdfUrl = @json(route('operaciones.movimientos-rutas-v2.pdf'));
            const modalEleccionElement = document.getElementById('modal-elegir-aplicacion');
            const modalEleccion = new bootstrap.Modal(modalEleccionElement);
            const modalDeposito = new bootstrap.Modal(document.getElementById('modal-aplicar-deposito'));
            const modalGasto = new bootstrap.Modal(document.getElementById('modal-aplicar-gasto'));
            const modalDetalle = new bootstrap.Modal(document.getElementById('modal-detalle-ruta-v2'));
            let aplicacionActual = null;
            const moneda = valor => 'RD$ ' + Number(valor || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const escapar = valor => String(valor ?? '').replace(/[&<>"']/g, caracter => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[caracter]);

            if (errorFechaImportacion && typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Las fechas no corresponden',
                    text: errorFechaImportacion,
                    confirmButtonText: 'Revisar documento',
                });
            }

            function configurarPegadoComprobante(modalId, zonaId, inputId, vistaPreviaId, textoId, prefijoArchivo) {
                const modalElement = document.getElementById(modalId);
                const zona = document.getElementById(zonaId);
                const input = document.getElementById(inputId);
                const vistaPrevia = document.getElementById(vistaPreviaId);
                const texto = document.getElementById(textoId);
                let vistaPreviaUrl = null;

                const mostrarVistaPrevia = archivo => {
                    if (vistaPreviaUrl) URL.revokeObjectURL(vistaPreviaUrl);

                    if (!archivo) {
                        vistaPrevia.classList.add('d-none');
                        vistaPrevia.removeAttribute('src');
                        texto.textContent = 'Haz clic aquí y presiona Ctrl+V para pegar una captura';
                        return;
                    }

                    vistaPreviaUrl = URL.createObjectURL(archivo);
                    vistaPrevia.src = vistaPreviaUrl;
                    vistaPrevia.classList.remove('d-none');
                    texto.textContent = `Captura lista: ${archivo.name}`;
                };

                const asignarCaptura = archivo => {
                    if (!archivo?.type.startsWith('image/')) return;

                    if (archivo.size > 10 * 1024 * 1024) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Captura demasiado grande', 'La imagen no puede superar los 10 MB.', 'warning');
                        } else {
                            alert('La imagen no puede superar los 10 MB.');
                        }

                        return;
                    }

                    const extension = archivo.type.split('/')[1]?.replace('jpeg', 'jpg') || 'png';
                    const captura = new File([archivo], `${prefijoArchivo}-${Date.now()}.${extension}`, { type: archivo.type });
                    const transferencia = new DataTransfer();
                    transferencia.items.add(captura);
                    input.files = transferencia.files;
                    mostrarVistaPrevia(captura);
                };

                zona.addEventListener('click', () => zona.focus());
                input.addEventListener('change', () => mostrarVistaPrevia(input.files[0] || null));
                modalElement.addEventListener('paste', event => {
                    const imagen = Array.from(event.clipboardData?.items || [])
                        .find(item => item.type.startsWith('image/'))
                        ?.getAsFile();

                    if (!imagen) return;

                    event.preventDefault();
                    asignarCaptura(imagen);
                });
            }

            configurarPegadoComprobante('modal-aplicar-deposito', 'zona-pegar-deposito', 'comprobante-deposito', 'vista-previa-deposito', 'texto-pegar-deposito', 'deposito');
            configurarPegadoComprobante('modal-aplicar-gasto', 'zona-pegar-gasto', 'comprobante-gasto', 'vista-previa-gasto', 'texto-pegar-gasto', 'gasto');

            if ($('#tabla-movimientos-rutas-v2 tbody tr').length) {
                $('#tabla-movimientos-rutas-v2').DataTable({ responsive: true, pageLength: 25, order: [[0, 'asc']], columnDefs: [{ orderable: false, targets: 11 }], language: { search: 'Buscar:', lengthMenu: 'Mostrar _MENU_', info: 'Mostrando _START_ a _END_ de _TOTAL_', paginate: { next: 'Siguiente', previous: 'Anterior' } } });
            }

            function abrirLuegoDeEleccion(modalDestino) {
                modalEleccionElement.addEventListener('hidden.bs.modal', () => modalDestino.show(), { once: true });
                modalEleccion.hide();
            }

            document.getElementById('elegir-deposito').addEventListener('click', function () {
                if (!aplicacionActual) return;
                document.getElementById('deposito-ruta-key').value = aplicacionActual.rutaKey;
                document.getElementById('deposito-ruta').value = aplicacionActual.ruta;
                document.getElementById('deposito-ruta-titulo').textContent = `${aplicacionActual.ruta} · ${fecha || ''}`;
                document.getElementById('deposito-resumen').textContent = `Neto esperado: ${moneda(aplicacionActual.neto)} · Depositado: ${moneda(aplicacionActual.depositado)} · Gastos: ${moneda(aplicacionActual.gastos)} · Pendiente: ${moneda(aplicacionActual.pendiente)}`;
                abrirLuegoDeEleccion(modalDeposito);
            });

            document.getElementById('elegir-gasto').addEventListener('click', function () {
                if (!aplicacionActual) return;
                document.getElementById('gasto-ruta-key').value = aplicacionActual.rutaKey;
                document.getElementById('gasto-ruta').value = aplicacionActual.ruta;
                document.getElementById('gasto-ruta-titulo').textContent = `${aplicacionActual.ruta} · ${fecha || ''}`;
                document.getElementById('gasto-resumen').textContent = `Neto esperado: ${moneda(aplicacionActual.neto)} · Depositado: ${moneda(aplicacionActual.depositado)} · Gastos: ${moneda(aplicacionActual.gastos)} · Pendiente: ${moneda(aplicacionActual.pendiente)}`;
                abrirLuegoDeEleccion(modalGasto);
            });

            document.addEventListener('click', async function (event) {
                const botonEliminar = event.target.closest('.btn-eliminar-aplicacion');
                if (botonEliminar) {
                    const tipo = botonEliminar.dataset.tipo;
                    const confirmado = typeof Swal !== 'undefined'
                        ? await Swal.fire({
                            icon: 'warning',
                            title: `Eliminar ${tipo}`,
                            text: `Se eliminará este ${tipo} y su comprobante. Luego podrás cargarlo nuevamente.`,
                            showCancelButton: true,
                            confirmButtonText: 'Sí, eliminar',
                            cancelButtonText: 'Cancelar',
                            confirmButtonColor: '#dc3545',
                        }).then(resultado => resultado.isConfirmed)
                        : confirm(`¿Eliminar este ${tipo}?`);

                    if (!confirmado) return;

                    botonEliminar.disabled = true;

                    try {
                        const response = await fetch(botonEliminar.dataset.url, {
                            method: 'DELETE',
                            headers: {
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                        });
                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok) throw new Error(payload.message || `No se pudo eliminar el ${tipo}.`);

                        if (typeof Swal !== 'undefined') {
                            await Swal.fire('Registro eliminado', payload.message, 'success');
                        } else {
                            alert(payload.message);
                        }

                        window.location.reload();
                    } catch (error) {
                        botonEliminar.disabled = false;

                        if (typeof Swal !== 'undefined') {
                            Swal.fire('No se pudo eliminar', error.message, 'error');
                        } else {
                            alert(error.message);
                        }
                    }

                    return;
                }

                const botonAplicar = event.target.closest('.btn-elegir-aplicacion');
                if (botonAplicar) {
                    aplicacionActual = {
                        rutaKey: botonAplicar.dataset.rutaKey,
                        ruta: botonAplicar.dataset.ruta,
                        neto: botonAplicar.dataset.neto,
                        depositado: botonAplicar.dataset.depositado,
                        gastos: botonAplicar.dataset.gastos,
                        pendiente: botonAplicar.dataset.pendiente
                    };
                    document.getElementById('aplicacion-ruta-titulo').textContent = aplicacionActual.ruta;
                    modalEleccion.show();
                    return;
                }

                const botonDetalle = event.target.closest('.btn-ver-detalle');
                if (!botonDetalle) return;

                document.getElementById('detalle-ruta-titulo').textContent = `Detalle · ${botonDetalle.dataset.ruta}`;
                document.getElementById('btn-informe-ruta-pdf').href = `${pdfUrl}?${new URLSearchParams({ fecha, ruta_key: botonDetalle.dataset.rutaKey }).toString()}`;
                document.getElementById('detalle-depositos-body').innerHTML = '<tr><td colspan="7" class="text-center">Cargando...</td></tr>';
                document.getElementById('detalle-gastos-body').innerHTML = '<tr><td colspan="6" class="text-center">Cargando...</td></tr>';
                document.getElementById('detalle-transacciones-body').innerHTML = '<tr><td colspan="5" class="text-center">Cargando...</td></tr>';
                modalDetalle.show();

                try {
                    const params = new URLSearchParams({ fecha, ruta_key: botonDetalle.dataset.rutaKey });
                    const response = await fetch(`${detalleUrl}?${params.toString()}`, { headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error('No se pudo cargar el detalle.');
                    const payload = await response.json();
                    document.getElementById('detalle-depositos-body').innerHTML = payload.depositos.length ? payload.depositos.map(item => `<tr><td>${escapar(item.fecha_registro)}</td><td>${escapar(item.banco)}</td><td>${escapar(item.referencia || '-')}</td><td class="text-end">${moneda(item.monto)}</td><td>${escapar(item.usuario)}</td><td>${item.comprobante_url ? `<a class="btn btn-sm btn-soft-primary" href="${escapar(item.comprobante_url)}" target="_blank">Ver</a>` : '-'}</td><td class="text-center"><button type="button" class="btn btn-sm btn-soft-danger btn-eliminar-aplicacion" data-tipo="depósito" data-url="${escapar(item.eliminar_url)}"><i class="ri-delete-bin-line"></i> Eliminar</button></td></tr>`).join('') : '<tr><td colspan="7" class="text-center text-muted">No hay depósitos aplicados.</td></tr>';
                    document.getElementById('detalle-gastos-body').innerHTML = payload.gastos.length ? payload.gastos.map(item => `<tr><td>${escapar(item.fecha_registro)}</td><td>${escapar(item.concepto)}</td><td class="text-end">${moneda(item.monto)}</td><td>${escapar(item.usuario)}</td><td>${item.comprobante_url ? `<a class="btn btn-sm btn-soft-primary" href="${escapar(item.comprobante_url)}" target="_blank">Ver</a>` : '-'}</td><td class="text-center"><button type="button" class="btn btn-sm btn-soft-danger btn-eliminar-aplicacion" data-tipo="gasto" data-url="${escapar(item.eliminar_url)}"><i class="ri-delete-bin-line"></i> Eliminar</button></td></tr>`).join('') : '<tr><td colspan="6" class="text-center text-muted">No hay gastos aplicados.</td></tr>';
                    document.getElementById('detalle-transacciones-body').innerHTML = payload.transacciones.length ? payload.transacciones.map(item => `<tr><td>${escapar(item.id_trans)}</td><td>${escapar(item.terminal || '-')}</td><td>${escapar(item.nombre_agencia || '-')}</td><td>${escapar(item.tipo_etiqueta)}</td><td class="text-end">${moneda(item.monto_original)}</td></tr>`).join('') : '<tr><td colspan="5" class="text-center text-muted">No hay transacciones.</td></tr>';
                } catch (error) {
                    document.getElementById('detalle-depositos-body').innerHTML = `<tr><td colspan="7" class="text-center text-danger">${escapar(error.message)}</td></tr>`;
                    document.getElementById('detalle-gastos-body').innerHTML = '';
                    document.getElementById('detalle-transacciones-body').innerHTML = '';
                }
            });
        });
    </script>
@endsection
