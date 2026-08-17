@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Volantes de Pago de Socios</h4>
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('contabilidad.index') }}">Contabilidad</a></li>
                                <li class="breadcrumb-item active">Volantes de pago</li>
                            </ol>
                        </div>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-1">Cargar archivo de pagos</h5>
                        <p class="text-muted mb-0">Los datos se conservarán tal como vienen abreviados en el archivo del banco.</p>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('contabilidad.volantes-pago-socios.procesar') }}"
                            enctype="multipart/form-data" class="row g-3 align-items-end">
                            @csrf
                            <div class="col-lg-9">
                                <label for="archivo_csv" class="form-label">Archivo CSV de Banco Santa Cruz</label>
                                <input type="file" class="form-control" id="archivo_csv" name="archivo_csv"
                                    accept=".csv,.txt,text/csv,text/plain" required>
                            </div>
                            <div class="col-lg-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="ri-upload-cloud-2-line align-bottom me-1"></i>Cargar archivo
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                @if ($carga)
                    <div class="row g-3 mb-4">
                        <div class="col-xl-4 col-md-6">
                            <div class="card h-100 mb-0"><div class="card-body">
                                <p class="text-muted mb-1">Cuenta de origen</p>
                                <h6 class="mb-1">{{ $carga->empresa_origen }}</h6>
                                <span>{{ $carga->cuenta_origen }}</span>
                            </div></div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="card h-100 mb-0"><div class="card-body">
                                <p class="text-muted mb-1">Fecha de transacción</p>
                                <h5 class="mb-0">{{ $carga->fecha_transaccion->format('d/m/Y h:i:s A') }}</h5>
                            </div></div>
                        </div>
                        <div class="col-xl-2 col-md-6">
                            <div class="card h-100 mb-0"><div class="card-body">
                                <p class="text-muted mb-1">Transacciones</p>
                                <h3 class="mb-0 text-primary">{{ number_format($carga->cantidad_transacciones) }}</h3>
                            </div></div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="card h-100 mb-0"><div class="card-body">
                                <p class="text-muted mb-1">Monto total validado</p>
                                <h3 class="mb-0 text-success">RD${{ number_format((float) $carga->monto_total, 2) }}</h3>
                            </div></div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header d-flex flex-wrap justify-content-between gap-2">
                            <div>
                                <h5 class="card-title mb-1">Detalle del archivo</h5>
                                <p class="text-muted mb-0">{{ $carga->nombre_archivo }}</p>
                            </div>
                            <span class="badge bg-success-subtle text-success align-self-center">{{ $carga->estado }}</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered align-middle w-100" id="tabla-volantes">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Nombre</th>
                                            <th>Identificación</th>
                                            <th>Cuenta</th>
                                            <th>Tipo de cuenta</th>
                                            <th class="text-end">Monto</th>
                                            <th>Estado</th>
                                            <th class="text-center">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($detalles as $detalle)
                                            <tr>
                                                <td>{{ $detalle->nombre }}</td>
                                                <td>{{ $detalle->tipo_identificacion }} · {{ $detalle->identificacion }}</td>
                                                <td>{{ $detalle->cuenta }}</td>
                                                <td>{{ $detalle->tipo_cuenta }}</td>
                                                <td class="text-end fw-semibold">RD${{ number_format((float) $detalle->monto, 2) }}</td>
                                                <td><span class="badge bg-success-subtle text-success">{{ $detalle->estado }}</span></td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-primary btn-ver-volante"
                                                        data-nombre="{{ $detalle->nombre }}"
                                                        data-preview="{{ route('contabilidad.volantes-pago-socios.vista-previa', $detalle) }}"
                                                        data-download="{{ route('contabilidad.volantes-pago-socios.pdf', $detalle) }}"
                                                        data-email="{{ route('contabilidad.volantes-pago-socios.correo', $detalle) }}">
                                                        <i class="ri-eye-line me-1"></i>Ver volante
                                                    </button>
                                                </td>
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

    <div class="modal fade" id="modal-volante" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Volante de pago</h5>
                        <small class="text-muted" id="volante-nombre"></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0 bg-light">
                    <iframe id="volante-preview" title="Vista previa del volante" class="w-100 border-0" style="height:65vh"></iframe>
                </div>
                <div class="modal-footer justify-content-between">
                    <form method="POST" id="form-enviar-volante" class="d-flex flex-grow-1 gap-2">
                        @csrf
                        <input type="email" name="correo" class="form-control" placeholder="correo@ejemplo.com" required>
                        <button type="submit" class="btn btn-outline-primary text-nowrap">
                            <i class="ri-mail-send-line me-1"></i>Enviar por correo
                        </button>
                    </form>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="btn-compartir-volante" disabled>
                            <i class="ri-share-forward-line me-1"></i>Compartir PDF
                        </button>
                        <a class="btn btn-primary" id="btn-generar-volante" href="#">
                            <i class="ri-file-pdf-2-line me-1"></i>Generar PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalElement = document.getElementById('modal-volante');
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            const preview = document.getElementById('volante-preview');
            const download = document.getElementById('btn-generar-volante');
            const emailForm = document.getElementById('form-enviar-volante');
            const shareButton = document.getElementById('btn-compartir-volante');
            let currentDownloadUrl = '';
            let currentName = '';
            let currentShareFile = null;
            let shareLoadToken = 0;

            if (window.jQuery && jQuery.fn.DataTable && document.getElementById('tabla-volantes')) {
                jQuery('#tabla-volantes').DataTable({
                    pageLength: 25,
                    order: [],
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' }
                });
            }

            document.addEventListener('click', function (event) {
                const button = event.target.closest('.btn-ver-volante');
                if (!button) return;

                currentDownloadUrl = button.dataset.download;
                currentName = button.dataset.nombre;
                document.getElementById('volante-nombre').textContent = currentName;
                preview.src = button.dataset.preview;
                download.href = currentDownloadUrl;
                emailForm.action = button.dataset.email;
                emailForm.reset();
                prepareShareFile();
                modal.show();
            });

            shareButton.addEventListener('click', function () {
                if (!currentShareFile) {
                    return;
                }

                try {
                    if (navigator.canShare?.({ files: [currentShareFile] })) {
                        navigator.share({
                            title: `Volante de pago - ${currentName}`,
                            files: [currentShareFile]
                        }).catch(showShareError);
                    } else {
                        download.click();
                    }
                } catch (error) {
                    showShareError(error);
                }
            });

            modalElement.addEventListener('hidden.bs.modal', function () {
                preview.src = 'about:blank';
                shareLoadToken++;
                currentShareFile = null;
                setShareButtonLoading();
            });

            function prepareShareFile() {
                const token = ++shareLoadToken;
                currentShareFile = null;
                setShareButtonLoading();

                fetch(currentDownloadUrl)
                    .then(response => {
                        if (!response.ok) throw new Error('No se pudo generar el PDF.');
                        return response.blob();
                    })
                    .then(blob => {
                        if (token !== shareLoadToken) return;

                        currentShareFile = new File(
                            [blob],
                            `volante_${currentName.replace(/[^a-z0-9]+/gi, '_')}.pdf`,
                            { type: 'application/pdf' }
                        );
                        shareButton.disabled = false;
                        shareButton.innerHTML = '<i class="ri-share-forward-line me-1"></i>Compartir PDF';
                    })
                    .catch(error => {
                        if (token !== shareLoadToken) return;

                        shareButton.disabled = false;
                        shareButton.innerHTML = '<i class="ri-download-2-line me-1"></i>Descargar PDF';
                        shareButton.onclick = () => download.click();
                        console.error(error);
                    });
            }

            function setShareButtonLoading() {
                shareButton.disabled = true;
                shareButton.onclick = null;
                shareButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Preparando PDF';
            }

            function showShareError(error) {
                if (error.name === 'AbortError') return;

                window.Swal
                    ? Swal.fire('No se pudo compartir', error.message, 'error')
                    : alert(error.message);
            }
        });
    </script>
@endsection
