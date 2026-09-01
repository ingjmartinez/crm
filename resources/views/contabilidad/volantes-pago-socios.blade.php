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
                            <div class="col-lg-3">
                                <label for="banco" class="form-label">Banco</label>
                                <select class="form-select" id="banco" name="banco" required>
                                    @foreach (\App\Models\VolantePagoSocioCarga::BANCOS as $valorBanco => $nombreBanco)
                                        <option value="{{ $valorBanco }}" @selected(old('banco', \App\Models\VolantePagoSocioCarga::BANCO_SANTA_CRUZ) === $valorBanco)>
                                            {{ $nombreBanco }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-4">
                                <label for="archivo_csv" class="form-label">Archivo CSV o TXT del banco</label>
                                <input type="file" class="form-control" id="archivo_csv" name="archivo_csv"
                                    accept=".csv,.txt,text/csv,text/plain" required>
                            </div>
                            <div class="col-lg-2">
                                <label for="fecha_correspondiente" class="form-label">Fecha correspondiente</label>
                                <input type="date" class="form-control" id="fecha_correspondiente"
                                    name="fecha_correspondiente" value="{{ old('fecha_correspondiente') }}" required>
                            </div>
                            <div class="col-lg-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="ri-save-line align-bottom me-1"></i>Guardar volantes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card border-primary-subtle">
                    <div class="card-header bg-primary-subtle">
                        <h5 class="card-title mb-1">Buscar volantes</h5>
                        <p class="text-muted mb-0">Localiza los volantes guardados por nombre del socio y fecha correspondiente.</p>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ route('contabilidad.volantes-pago-socios') }}" class="row g-3 align-items-end">
                            <div class="col-lg-4">
                                <label for="nombre" class="form-label">Nombre del socio</label>
                                <input type="search" class="form-control" id="nombre" name="nombre"
                                    value="{{ $nombreBuscado }}" placeholder="Escribe el nombre del socio">
                            </div>
                            <div class="col-lg-2">
                                <label for="fecha_desde" class="form-label">Fecha desde</label>
                                <input type="date" class="form-control" id="fecha_desde" name="fecha_desde"
                                    value="{{ $fechaDesdeBuscada }}">
                            </div>
                            <div class="col-lg-2">
                                <label for="fecha_hasta" class="form-label">Fecha hasta</label>
                                <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta"
                                    value="{{ $fechaHastaBuscada }}">
                            </div>
                            <div class="col-lg-4 d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="ri-search-line align-bottom me-1"></i>Buscar
                                </button>
                                <a href="{{ route('contabilidad.volantes-pago-socios') }}" class="btn btn-light">Limpiar</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-1">Volantes guardados</h5>
                        <p class="text-muted mb-0">Consulta los volantes conservados por socio y fecha correspondiente.</p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered align-middle w-100">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Fecha correspondiente</th>
                                        <th>Identificación</th>
                                        <th>Banco</th>
                                        <th>Archivo</th>
                                        <th class="text-end">Monto</th>
                                        <th>Estado</th>
                                        <th class="text-center">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($historial as $detalle)
                                        <tr>
                                            <td>{{ $detalle->nombre }}</td>
                                            <td>{{ ($detalle->carga->fecha_correspondiente ?? $detalle->carga->fecha_transaccion)->format('d/m/Y') }}</td>
                                            <td>{{ $detalle->tipo_identificacion }} · {{ $detalle->identificacion }}</td>
                                            <td>{{ $detalle->carga->nombreBanco() }}</td>
                                            <td>{{ $detalle->carga->nombre_archivo }}</td>
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
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                {{ $consultaRealizada ? 'No se encontraron volantes con los filtros indicados.' : 'Utiliza el panel de búsqueda para consultar los volantes guardados.' }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">{{ $historial->links() }}</div>
                    </div>
                </div>
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
