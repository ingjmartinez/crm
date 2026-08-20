@extends('app')

@section('content')
    <style>
    .beneficio-summary-card {
        border: 1px solid #d9dee5;
        box-shadow: 0 4px 14px rgba(31, 42, 55, 0.07);
    }

    .beneficio-summary-card .card-header {
        border-bottom: 0;
    }

    .beneficio-summary-card .card-header .card-title,
    .beneficio-summary-card .card-header .terminales-encabezado {
        color: #fff !important;
    }

    .beneficio-summary-card .card-header .terminales-encabezado {
        background-color: rgba(255, 255, 255, 0.16) !important;
        border-radius: 4px;
        font-weight: 500;
        padding: 0.35rem 0.55rem;
        white-space: nowrap;
    }

    .beneficio-summary-card--tradicional .card-header { background-color: #293b50; }
    .beneficio-summary-card--no-tradicional .card-header { background-color: #415964; }
    .beneficio-summary-card--recargas .card-header { background-color: #6b604c; }
    .beneficio-summary-card--externas .card-header { background-color: #50666c; }
    .beneficio-summary-card--balance { border-color: #3b5148; }
    .beneficio-summary-card--balance .card-header { background-color: #3b5148; }
    </style>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Beneficio Bruto</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('gerencia.index') }}">Gerencia</a></li>
                                    <li class="breadcrumb-item active">Beneficio Bruto</li>
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
                        <h5 class="card-title mb-0">Cargar resumen de ventas, premios y pagos</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('gerencia.beneficio-bruto.procesar') }}" enctype="multipart/form-data" class="row g-3 align-items-end" id="form-beneficio-bruto">
                            @csrf
                            <div class="col-lg-4">
                                <label for="archivo_joselito" class="form-label">Documento Joselito</label>
                                <input type="file" class="form-control" id="archivo_joselito" name="archivo_joselito" accept=".csv,.txt,text/csv,text/plain">
                            </div>
                            <div class="col-lg-4">
                                <label for="archivo_negosur" class="form-label">Documento Negosur</label>
                                <input type="file" class="form-control" id="archivo_negosur" name="archivo_negosur" accept=".csv,.txt,text/csv,text/plain">
                            </div>
                            <div class="col-lg-4">
                                <label for="archivo_higuey" class="form-label">Documento Higuey</label>
                                <input type="file" class="form-control" id="archivo_higuey" name="archivo_higuey" accept=".csv,.txt,text/csv,text/plain">
                            </div>
                            <div class="col-12">
                                <div class="form-text mb-2">Puedes cargar uno, dos o los tres documentos. Todos deben mantener la misma estructura: terminal en columna C y bloques de ventas en E–P.</div>
                                <button type="submit" class="btn btn-primary w-100" id="btn-procesar-beneficio">
                                    <i class="ri-upload-cloud-2-line align-bottom me-1"></i>
                                    Procesar y consolidar documentos
                                </button>
                            </div>
                        </form>

                        @if ($nombresArchivos)
                            <div class="alert alert-info mt-3 mb-0">
                                <div class="row g-2">
                                    @foreach ($nombresArchivos as $archivoProcesado)
                                        <div class="col-md-4">
                                            <strong>{{ $archivoProcesado['grupo'] }}:</strong> {{ $archivoProcesado['nombre'] }}
                                            <span class="d-block small">{{ number_format($archivoProcesado['filas']) }} registros</span>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="small mt-1">
                                    Cruce con <strong>crm.agencias</strong>:
                                    {{ number_format($cruceAgencias['identificadas']) }} de {{ number_format($cruceAgencias['total']) }} terminales identificadas,
                                    {{ number_format($cruceAgencias['con_ciudad']) }} con ciudad y
                                    {{ number_format($cruceAgencias['con_ruta']) }} con ruta.
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                @if ($filas->isNotEmpty())
                    <div class="d-flex flex-wrap justify-content-end gap-2 mb-3">
                        <button type="button" class="btn btn-danger" id="btn-pdf-tarjetas">
                            <i class="ri-file-pdf-2-line align-bottom me-1"></i>
                            PDF de tarjetas
                        </button>
                        <button type="button" class="btn btn-dark" id="btn-pdf-estado-resultados">
                            <i class="ri-file-chart-line align-bottom me-1"></i>
                            PDF estado de resultado
                        </button>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-xl-6 d-flex">
                            <div class="card w-100 mb-0 beneficio-summary-card beneficio-summary-card--tradicional">
                                <div class="card-header d-flex align-items-center justify-content-between gap-2">
                                    <h4 class="card-title mb-0">Tradicional</h4>
                                    <span class="terminales-encabezado">
                                        {{ number_format($resumen['tradicional']['terminales']) }}
                                        {{ $resumen['tradicional']['terminales'] === 1 ? 'terminal vendió' : 'terminales vendieron' }}
                                    </span>
                                </div>
                                <div class="card-body py-4">
                                    <div class="row g-4">
                                        <div class="col-sm-6">
                                            <p class="text-muted mb-1">Total vendido</p>
                                            <h4 class="mb-0">RD$ {{ number_format($resumen['tradicional']['total_vendido'], 2) }}</h4>
                                        </div>
                                        <div class="col-sm-6">
                                            <p class="text-muted mb-1">Premios sacados</p>
                                            <h4 class="mb-0">RD$ {{ number_format($resumen['tradicional']['premios_sacados'], 2) }}</h4>
                                        </div>
                                        <div class="col-sm-6">
                                            <p class="text-muted mb-1">Premios pagados</p>
                                            <h4 class="mb-0">RD$ {{ number_format($resumen['tradicional']['premios_pagados'], 2) }}</h4>
                                        </div>
                                        <div class="col-sm-6">
                                            <p class="text-muted mb-1">Balance general</p>
                                            <h4 class="mb-0 {{ $resumen['tradicional']['balance_general'] < 0 ? 'text-danger' : 'text-success' }}">
                                                RD$ {{ number_format($resumen['tradicional']['balance_general'], 2) }}
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-6 d-flex">
                            <div class="card w-100 mb-0 beneficio-summary-card beneficio-summary-card--no-tradicional">
                                <div class="card-header d-flex align-items-center justify-content-between gap-2">
                                    <h4 class="card-title mb-0">No Tradicional</h4>
                                    <span class="terminales-encabezado">
                                        {{ number_format($resumen['no_tradicional']['terminales']) }}
                                        {{ $resumen['no_tradicional']['terminales'] === 1 ? 'terminal vendió' : 'terminales vendieron' }}
                                    </span>
                                </div>
                                <div class="card-body py-4">
                                    <div class="row g-4">
                                        <div class="col-sm-6">
                                            <p class="text-muted mb-1">Total vendido</p>
                                            <h4 class="mb-0">RD$ {{ number_format($resumen['no_tradicional']['total_vendido'], 2) }}</h4>
                                        </div>
                                        <div class="col-sm-6">
                                            <p class="text-muted mb-1">Premios sacados</p>
                                            <h4 class="mb-0">RD$ {{ number_format($resumen['no_tradicional']['premios_sacados'], 2) }}</h4>
                                        </div>
                                        <div class="col-sm-6">
                                            <p class="text-muted mb-1">Premios pagados</p>
                                            <h4 class="mb-0">RD$ {{ number_format($resumen['no_tradicional']['premios_pagados'], 2) }}</h4>
                                        </div>
                                        <div class="col-sm-6">
                                            <p class="text-muted mb-1">Balance general</p>
                                            <h4 class="mb-0 {{ $resumen['no_tradicional']['balance_general'] < 0 ? 'text-danger' : 'text-success' }}">
                                                RD$ {{ number_format($resumen['no_tradicional']['balance_general'], 2) }}
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-4 d-flex">
                            <div class="card w-100 mb-0 beneficio-summary-card beneficio-summary-card--recargas">
                                <div class="card-header d-flex align-items-center justify-content-between gap-2">
                                    <h4 class="card-title mb-0">Recargas</h4>
                                    <span class="terminales-encabezado">
                                        {{ number_format($resumen['recargas']['terminales']) }}
                                        {{ $resumen['recargas']['terminales'] === 1 ? 'terminal vendió' : 'terminales vendieron' }}
                                    </span>
                                </div>
                                <div class="card-body py-4">
                                    <p class="text-muted mb-1">Total vendido</p>
                                    <h3 class="mb-3">RD$ {{ number_format($resumen['recargas']['total_vendido'], 2) }}</h3>
                                    <div class="row g-3 border-top pt-3">
                                        <div class="col-6">
                                            <span>Recargas: <strong>RD$ {{ number_format($resumen['recargas']['recargas'], 2) }}</strong></span>
                                            <small class="text-muted d-block">{{ number_format($resumen['recargas']['terminales_recargas']) }} terminales</small>
                                        </div>
                                        <div class="col-6">
                                            <span>Paqueticos: <strong>RD$ {{ number_format($resumen['recargas']['paqueticos'], 2) }}</strong></span>
                                            <small class="text-muted d-block">{{ number_format($resumen['recargas']['terminales_paqueticos']) }} terminales</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-4 d-flex">
                            <div class="card w-100 mb-0 beneficio-summary-card beneficio-summary-card--externas">
                                <div class="card-header d-flex align-items-center justify-content-between gap-2">
                                    <h4 class="card-title mb-0">Ventas externas</h4>
                                    <span class="terminales-encabezado">
                                        {{ number_format($resumen['ventas_externas']['terminales']) }}
                                        {{ $resumen['ventas_externas']['terminales'] === 1 ? 'terminal vendió' : 'terminales vendieron' }}
                                    </span>
                                </div>
                                <div class="card-body py-4">
                                    <p class="text-muted mb-1">Total vendido</p>
                                    <h3 class="mb-3">RD$ {{ number_format($resumen['ventas_externas']['total_vendido'], 2) }}</h3>
                                    <div class="row g-3 border-top pt-3">
                                        <div class="col-6">
                                            <span>Seguros: <strong>RD$ {{ number_format($resumen['ventas_externas']['seguros'], 2) }}</strong></span>
                                            <small class="text-muted d-block">{{ number_format($resumen['ventas_externas']['terminales_seguros']) }} terminales</small>
                                        </div>
                                        <div class="col-6">
                                            <span>Boletos: <strong>RD$ {{ number_format($resumen['ventas_externas']['boletos'], 2) }}</strong></span>
                                            <small class="text-muted d-block">{{ number_format($resumen['ventas_externas']['terminales_boletos']) }} terminales</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 col-xl-4 d-flex">
                            <div class="card w-100 mb-0 beneficio-summary-card beneficio-summary-card--balance">
                                <div class="card-header">
                                    <h4 class="card-title mb-0">Balance</h4>
                                </div>
                                <div class="card-body py-4">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <p class="text-muted mb-1">Total vendido</p>
                                            <h3 class="mb-0 text-primary">RD$ {{ number_format($resumen['balance'], 2) }}</h3>
                                        </div>
                                        <div class="col-6">
                                            <p class="text-muted mb-1">Balance de loterías</p>
                                            <h5 class="mb-0">RD$ {{ number_format($informeGerencial['balance_loterias'], 2) }}</h5>
                                        </div>
                                        <div class="col-6">
                                            <p class="text-muted mb-1">Ventas Recargas</p>
                                            <h5 class="mb-0">RD$ {{ number_format($informeGerencial['ventas_recargas'], 2) }}</h5>
                                        </div>
                                        <div class="col-12">
                                            <p class="text-muted mb-1">Ventas externas</p>
                                            <h5 class="mb-0">RD$ {{ number_format($informeGerencial['ventas_externas'], 2) }}</h5>
                                        </div>
                                        <div class="col-12 border-top pt-3">
                                            <p class="text-muted mb-1">Balance general neto</p>
                                            <h3 class="mb-0 {{ $informeGerencial['balance_general_neto'] < 0 ? 'text-danger' : 'text-success' }}">
                                                RD$ {{ number_format($informeGerencial['balance_general_neto'], 2) }}
                                            </h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-1">Informe gerencial</h4>
                            <p class="text-muted mb-0">Resumen ejecutivo de ventas, participación y alcance por terminal.</p>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle mb-3">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Bloque</th>
                                            <th class="text-end">Total vendido</th>
                                            <th class="text-end">Participación</th>
                                            <th class="text-end">Terminales que vendieron</th>
                                            <th class="text-end">Promedio por terminal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($informeGerencial['bloques'] as $bloque)
                                            <tr>
                                                <td class="fw-semibold">{{ $bloque['nombre'] }}</td>
                                                <td class="text-end">RD$ {{ number_format($bloque['ventas'], 2) }}</td>
                                                <td class="text-end">{{ number_format($bloque['participacion'], 2) }}%</td>
                                                <td class="text-end">{{ number_format($bloque['terminales']) }}</td>
                                                <td class="text-end">RD$ {{ number_format($bloque['promedio_terminal'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="table-light fw-bold">
                                        <tr>
                                            <td>Total general</td>
                                            <td class="text-end">RD$ {{ number_format($resumen['balance'], 2) }}</td>
                                            <td class="text-end">100.00%</td>
                                            <td class="text-end text-muted">No acumulable</td>
                                            <td class="text-end text-muted">—</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="card">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <h5 class="card-title mb-0">Datos formateados</h5>
                        @if ($filas->isNotEmpty())
                            <span class="badge bg-primary-subtle text-primary">{{ number_format($filas->count()) }} terminales</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle w-100" id="tabla-beneficio-bruto">
                                <thead class="table-light text-center align-middle">
                                    <tr>
                                        <th rowspan="2">Nombre de la terminal</th>
                                        <th rowspan="2">Ciudad</th>
                                        <th rowspan="2">Ruta</th>
                                        <th colspan="4" class="table-primary">Ventas Tradicionales</th>
                                        <th colspan="4" class="table-success">Ventas No Tradicionales</th>
                                        <th colspan="2" class="table-warning">Recargas y Paqueticos</th>
                                        <th colspan="2" class="table-info">Ventas externas</th>
                                    </tr>
                                    <tr>
                                        <th>Ventas</th>
                                        <th>Premios</th>
                                        <th>Pagados</th>
                                        <th>Resultados</th>
                                        <th>Ventas</th>
                                        <th>Premios</th>
                                        <th>Pagados</th>
                                        <th>Resultados</th>
                                        <th>Recargas</th>
                                        <th>Paqueticos</th>
                                        <th>Seguros</th>
                                        <th>Boletos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($filas as $fila)
                                        <tr>
                                            <td>{{ $fila['terminal'] }}</td>
                                            <td>{{ $fila['ciudad'] ?? 'No identificada' }}</td>
                                            <td>{{ $fila['ruta'] ?? 'No identificada' }}</td>
                                            @foreach (array_keys($totales) as $campo)
                                                @php($valor = (float) $fila[$campo])
                                                <td class="text-end {{ str_ends_with($campo, 'resultados') ? ($valor < 0 ? 'text-danger fw-semibold' : 'text-success fw-semibold') : '' }}">
                                                    {{ number_format($valor, 2) }}
                                                </td>
                                            @endforeach
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="15" class="text-center text-muted py-4">Carga un documento CSV para generar el reporte.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if ($filas->isNotEmpty())
                                    <tfoot class="table-light fw-semibold">
                                        <tr>
                                            <td>Totales</td>
                                            <td></td>
                                            <td></td>
                                            @foreach ($totales as $total)
                                                <td class="text-end">{{ number_format($total, 2) }}</td>
                                            @endforeach
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.0/jspdf.plugin.autotable.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('form-beneficio-bruto');
            const button = document.getElementById('btn-procesar-beneficio');
            const fileInputs = form ? Array.from(form.querySelectorAll('input[type="file"]')) : [];
            const pdfButton = document.getElementById('btn-pdf-tarjetas');
            const estadoResultadosPdfButton = document.getElementById('btn-pdf-estado-resultados');
            const resumenPdf = @json($resumen);
            const resumenPorGrupoPdf = @json($resumenPorGrupo);
            const informePdf = @json($informeGerencial);
            const gruposCargadosPdf = @json(array_keys($nombresArchivos ?? []));

            if (form && button) {
                form.addEventListener('submit', function (event) {
                    if (!fileInputs.some((input) => input.files.length > 0)) {
                        event.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'Selecciona un documento',
                            text: 'Debes cargar al menos uno de los tres documentos CSV.',
                        });

                        return;
                    }

                    button.disabled = true;
                    Swal.fire({
                        title: 'Procesando documento...',
                        text: 'Estamos organizando los datos del reporte.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => Swal.showLoading(),
                    });
                });
            }

            function formatoMontoPdf(valor) {
                return 'RD$ ' + Number(valor || 0).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            }

            function generarPdfTarjetas() {
                if (!window.jspdf || !window.jspdf.jsPDF) {
                    Swal.fire({
                        icon: 'error',
                        title: 'No se pudo generar el PDF',
                        text: 'La librería de PDF no está disponible. Intenta nuevamente.',
                    });

                    return;
                }

                const doc = new window.jspdf.jsPDF({
                    orientation: 'landscape',
                    unit: 'pt',
                    format: 'letter',
                });
                const pageWidth = doc.internal.pageSize.getWidth();
                const margin = 32;
                const gap = 14;
                const topCardWidth = (pageWidth - (margin * 2) - gap) / 2;
                const bottomCardWidth = (pageWidth - (margin * 2) - (gap * 2)) / 3;

                doc.setFont('helvetica', 'bold');
                doc.setFontSize(19);
                doc.setTextColor(33, 37, 41);
                doc.text('Informe Gerencial - Beneficio Bruto', margin, 34);
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(9);
                doc.setTextColor(100, 100, 100);
                doc.text('Generado: ' + new Date().toLocaleString('es-DO'), pageWidth - margin, 49, { align: 'right' });

                function tarjeta(titulo, x, y, width, height, color, datos, destacarUltimo, terminales) {
                    doc.setDrawColor(color[0], color[1], color[2]);
                    doc.setLineWidth(1);
                    doc.roundedRect(x, y, width, height, 5, 5, 'S');
                    doc.setFillColor(color[0], color[1], color[2]);
                    doc.roundedRect(x, y, width, 30, 5, 5, 'F');
                    doc.rect(x, y + 20, width, 10, 'F');
                    doc.setFont('helvetica', 'bold');
                    doc.setFontSize(12);
                    doc.setTextColor(255, 255, 255);
                    doc.text(titulo, x + 12, y + 20);

                    if (terminales !== null) {
                        const cantidadTerminales = Number(terminales || 0);
                        const textoTerminales = cantidadTerminales.toLocaleString('en-US')
                            + (cantidadTerminales === 1 ? ' terminal vendió' : ' terminales vendieron');
                        doc.setFont('helvetica', 'normal');
                        doc.setFontSize(8.5);
                        doc.setTextColor(255, 255, 255);
                        doc.text(textoTerminales, x + width - 12, y + 20, { align: 'right' });
                    }

                    const rowHeight = (height - 42) / datos.length;
                    datos.forEach(function (dato, index) {
                        const rowY = y + 46 + (index * rowHeight);
                        const isHighlighted = destacarUltimo && index === datos.length - 1;

                        if (index > 0) {
                            doc.setDrawColor(230, 230, 230);
                            doc.line(x + 12, rowY - 8, x + width - 12, rowY - 8);
                        }

                        doc.setFont('helvetica', 'normal');
                        doc.setFontSize(8.5);
                        doc.setTextColor(105, 105, 105);
                        doc.text(dato.etiqueta, x + 12, rowY);
                        doc.setFont('helvetica', 'bold');
                        doc.setFontSize(isHighlighted ? 12 : 10.5);
                        doc.setTextColor(isHighlighted ? color[0] : 35, isHighlighted ? color[1] : 35, isHighlighted ? color[2] : 35);
                        doc.text(formatoMontoPdf(dato.valor), x + width - 12, rowY, { align: 'right' });
                    });
                }

                tarjeta('Tradicional', margin, 66, topCardWidth, 172, [41, 59, 80], [
                    { etiqueta: 'Total vendido', valor: resumenPdf.tradicional.total_vendido },
                    { etiqueta: 'Premios sacados', valor: resumenPdf.tradicional.premios_sacados },
                    { etiqueta: 'Premios pagados', valor: resumenPdf.tradicional.premios_pagados },
                    { etiqueta: 'Balance general', valor: resumenPdf.tradicional.balance_general },
                ], true, resumenPdf.tradicional.terminales);
                tarjeta('No Tradicional', margin + topCardWidth + gap, 66, topCardWidth, 172, [65, 89, 100], [
                    { etiqueta: 'Total vendido', valor: resumenPdf.no_tradicional.total_vendido },
                    { etiqueta: 'Premios sacados', valor: resumenPdf.no_tradicional.premios_sacados },
                    { etiqueta: 'Premios pagados', valor: resumenPdf.no_tradicional.premios_pagados },
                    { etiqueta: 'Balance general', valor: resumenPdf.no_tradicional.balance_general },
                ], true, resumenPdf.no_tradicional.terminales);
                tarjeta('Recargas', margin, 252, bottomCardWidth, 184, [107, 96, 76], [
                    { etiqueta: 'Total vendido', valor: resumenPdf.recargas.total_vendido },
                    { etiqueta: 'Recargas', valor: resumenPdf.recargas.recargas },
                    { etiqueta: 'Paqueticos', valor: resumenPdf.recargas.paqueticos },
                ], false, resumenPdf.recargas.terminales);
                tarjeta('Ventas externas', margin + bottomCardWidth + gap, 252, bottomCardWidth, 184, [80, 102, 108], [
                    { etiqueta: 'Total vendido', valor: resumenPdf.ventas_externas.total_vendido },
                    { etiqueta: 'Seguros', valor: resumenPdf.ventas_externas.seguros },
                    { etiqueta: 'Boletos', valor: resumenPdf.ventas_externas.boletos },
                ], false, resumenPdf.ventas_externas.terminales);
                tarjeta('Balance', margin + ((bottomCardWidth + gap) * 2), 252, bottomCardWidth, 184, [59, 81, 72], [
                    { etiqueta: 'Total vendido', valor: resumenPdf.balance },
                    { etiqueta: 'Balance de loterías', valor: informePdf.balance_loterias },
                    { etiqueta: 'Ventas Recargas', valor: informePdf.ventas_recargas },
                    { etiqueta: 'Ventas externas', valor: informePdf.ventas_externas },
                    { etiqueta: 'Balance general neto', valor: informePdf.balance_general_neto },
                ], true, null);

                doc.setFont('helvetica', 'normal');
                doc.setFontSize(8);
                doc.setTextColor(120, 120, 120);
                doc.text('El balance general neto suma el balance de loterías, Recargas/Paqueticos y ventas externas.', margin, 458);

                doc.addPage('letter', 'landscape');
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(19);
                doc.setTextColor(41, 50, 60);
                doc.text('Informe Gerencial - Resumen Ejecutivo', margin, 38);
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(10);
                doc.setTextColor(90, 98, 108);

                doc.text('Comparativo de ventas, participación, alcance y promedio por terminal.', margin, 58);

                const filasInformePdf = (informePdf.bloques || []).map(function (bloque) {
                    return [
                        bloque.nombre,
                        formatoMontoPdf(bloque.ventas),
                        Number(bloque.participacion || 0).toFixed(2) + '%',
                        Number(bloque.terminales || 0).toLocaleString('en-US'),
                        formatoMontoPdf(bloque.promedio_terminal),
                    ];
                });

                doc.autoTable({
                    head: [['Bloque', 'Total vendido', 'Participación', 'Terminales que vendieron', 'Promedio por terminal']],
                    body: filasInformePdf,
                    foot: [['Total general', formatoMontoPdf(resumenPdf.balance), '100.00%', 'No acumulable', '-']],
                    startY: 76,
                    margin: { left: margin, right: margin },
                    theme: 'grid',
                    styles: {
                        fontSize: 9,
                        cellPadding: 7,
                        lineColor: [215, 220, 226],
                        lineWidth: 0.5,
                    },
                    headStyles: {
                        fillColor: [41, 59, 80],
                        textColor: [255, 255, 255],
                        fontStyle: 'bold',
                    },
                    footStyles: {
                        fillColor: [235, 238, 241],
                        textColor: [41, 50, 60],
                        fontStyle: 'bold',
                    },
                    columnStyles: {
                        1: { halign: 'right' },
                        2: { halign: 'right' },
                        3: { halign: 'right' },
                        4: { halign: 'right' },
                    },
                });

                const resumenY = doc.lastAutoTable.finalY + 24;
                doc.setDrawColor(190, 198, 205);
                doc.setFillColor(247, 248, 249);
                doc.roundedRect(margin, resumenY, pageWidth - (margin * 2), 92, 5, 5, 'FD');
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(12);
                doc.setTextColor(41, 50, 60);
                doc.text('Balance general neto', margin + 14, resumenY + 22);
                doc.setFontSize(18);
                doc.setTextColor(59, 81, 72);
                doc.text(formatoMontoPdf(informePdf.balance_general_neto), margin + 14, resumenY + 47);
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(9);
                doc.setTextColor(90, 98, 108);
                doc.text(
                    'Balance de loterías ' + formatoMontoPdf(informePdf.balance_loterias)
                        + ' + Recargas/Paqueticos ' + formatoMontoPdf(informePdf.ventas_recargas)
                        + ' + Ventas externas ' + formatoMontoPdf(informePdf.ventas_externas),
                    margin + 14,
                    resumenY + 68
                );
                doc.save('informe_gerencial_beneficio_bruto.pdf');
            }

            function generarPdfEstadoResultados() {
                if (!window.jspdf || !window.jspdf.jsPDF) {
                    Swal.fire({
                        icon: 'error',
                        title: 'No se pudo generar el PDF',
                        text: 'La librería de PDF no está disponible. Intenta nuevamente.',
                    });

                    return;
                }

                const doc = new window.jspdf.jsPDF({
                    orientation: 'landscape',
                    unit: 'pt',
                    format: 'letter',
                });
                const pageWidth = doc.internal.pageSize.getWidth();
                const margin = 28;
                const gruposEstado = [
                    { clave: 'joselito', nombre: 'Joselito' },
                    { clave: 'negosur', nombre: 'Negosur' },
                    { clave: 'higuey', nombre: 'Higuey' },
                ].filter((grupo) => gruposCargadosPdf.includes(grupo.clave));
                const cantidadColumnasEstado = gruposEstado.length + 3;
                const indiceTotalConsolidado = cantidadColumnasEstado - 1;
                const filaMonto = (concepto, clasificacion, calcular, deduccion = false) => {
                    const valores = gruposEstado.map((grupo) => Number(calcular(resumenPorGrupoPdf[grupo.clave]) || 0));
                    const total = valores.reduce((acumulado, valor) => acumulado + valor, 0);
                    const formatear = (valor) => (deduccion ? '- ' : '') + formatoMontoPdf(valor);

                    return [concepto, clasificacion, ...valores.map(formatear), formatear(total)];
                };
                const seccion = (titulo) => [titulo, ...Array(cantidadColumnasEstado - 1).fill('')];
                const ventasLoterias = (grupo) => Number(grupo.tradicional.total_vendido || 0)
                    + Number(grupo.no_tradicional.total_vendido || 0);
                const premiosSacados = (grupo) => Number(grupo.tradicional.premios_sacados || 0)
                    + Number(grupo.no_tradicional.premios_sacados || 0);
                const premiosPagados = (grupo) => Number(grupo.tradicional.premios_pagados || 0)
                    + Number(grupo.no_tradicional.premios_pagados || 0);
                const balanceLoterias = (grupo) => Number(grupo.tradicional.balance_general || 0)
                    + Number(grupo.no_tradicional.balance_general || 0);
                const balanceGeneralNeto = (grupo) => balanceLoterias(grupo)
                    + Number(grupo.recargas.total_vendido || 0)
                    + Number(grupo.ventas_externas.total_vendido || 0);

                doc.setFont('helvetica', 'bold');
                doc.setFontSize(18);
                doc.setTextColor(38, 48, 58);
                doc.text('Estado de Resultado Consolidado', pageWidth / 2, 38, { align: 'center' });
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(9);
                doc.setTextColor(95, 103, 112);
                doc.text('Beneficio Bruto', pageWidth / 2, 54, { align: 'center' });
                doc.text('Generado: ' + new Date().toLocaleString('es-DO'), pageWidth - margin, 72, { align: 'right' });

                const filasEstado = [
                    seccion('INGRESOS DE LOTERÍAS'),
                    filaMonto('Ventas Tradicionales', 'Ventas brutas', (grupo) => grupo.tradicional.total_vendido),
                    filaMonto('Ventas No Tradicionales', 'Ventas brutas', (grupo) => grupo.no_tradicional.total_vendido),
                    filaMonto('Total ventas de loterías', '', ventasLoterias),
                    seccion('MOVIMIENTO DE PREMIOS'),
                    filaMonto('Premios sacados Tradicional', 'Informativo', (grupo) => grupo.tradicional.premios_sacados),
                    filaMonto('Premios sacados No Tradicional', 'Informativo', (grupo) => grupo.no_tradicional.premios_sacados),
                    filaMonto('Total premios sacados', 'No afecta el balance', premiosSacados),
                    filaMonto('Premios pagados Tradicional', 'Deducción', (grupo) => grupo.tradicional.premios_pagados, true),
                    filaMonto('Premios pagados No Tradicional', 'Deducción', (grupo) => grupo.no_tradicional.premios_pagados, true),
                    filaMonto('Total premios pagados', 'Deducción aplicada', premiosPagados, true),
                    seccion('RESULTADO DE LOTERÍAS'),
                    filaMonto('Balance Tradicional', 'Según columna Resultados', (grupo) => grupo.tradicional.balance_general),
                    filaMonto('Balance No Tradicional', 'Según columna Resultados', (grupo) => grupo.no_tradicional.balance_general),
                    filaMonto('Balance de loterías', '', balanceLoterias),
                    seccion('OTRAS VENTAS'),
                    filaMonto('Recargas', '', (grupo) => grupo.recargas.recargas),
                    filaMonto('Paqueticos', '', (grupo) => grupo.recargas.paqueticos),
                    filaMonto('Total Recargas y Paqueticos', '', (grupo) => grupo.recargas.total_vendido),
                    filaMonto('Seguros', '', (grupo) => grupo.ventas_externas.seguros),
                    filaMonto('Boletos', '', (grupo) => grupo.ventas_externas.boletos),
                    filaMonto('Total ventas externas', '', (grupo) => grupo.ventas_externas.total_vendido),
                    filaMonto('BALANCE GENERAL NETO', 'Loterías + Recargas + Externas', balanceGeneralNeto),
                ];
                const secciones = [
                    'INGRESOS DE LOTERÍAS',
                    'MOVIMIENTO DE PREMIOS',
                    'RESULTADO DE LOTERÍAS',
                    'OTRAS VENTAS',
                ];
                const totales = [
                    'Total ventas de loterías',
                    'Total premios sacados',
                    'Total premios pagados',
                    'Balance de loterías',
                    'Total Recargas y Paqueticos',
                    'Total ventas externas',
                ];

                doc.autoTable({
                    head: [['Concepto', 'Clasificación', ...gruposEstado.map((grupo) => grupo.nombre), 'Total consolidado']],
                    body: filasEstado,
                    startY: 86,
                    margin: { left: margin, right: margin, bottom: 36 },
                    theme: 'plain',
                    styles: {
                        fontSize: 7.5,
                        cellPadding: 4,
                        textColor: [45, 52, 60],
                        lineColor: [220, 224, 228],
                        lineWidth: { bottom: 0.35 },
                    },
                    headStyles: {
                        fillColor: [41, 59, 80],
                        textColor: [255, 255, 255],
                        fontStyle: 'bold',
                        lineWidth: 0,
                    },
                    columnStyles: {
                        0: { cellWidth: 155 },
                        1: { cellWidth: 105 },
                        2: { halign: 'right' },
                        3: { halign: 'right' },
                        4: { halign: 'right' },
                        [indiceTotalConsolidado]: { halign: 'right', fontStyle: 'bold' },
                    },
                    didParseCell: function (data) {
                        if (data.section === 'head') {
                            data.cell.styles.halign = data.column.index >= 2 ? 'right' : 'left';

                            return;
                        }

                        if (data.section !== 'body') {
                            return;
                        }

                        const concepto = String(data.row.raw[0] || '');

                        if (secciones.includes(concepto)) {
                            data.cell.styles.fillColor = [237, 240, 243];
                            data.cell.styles.textColor = [41, 59, 80];
                            data.cell.styles.fontStyle = 'bold';
                            data.cell.styles.lineWidth = 0;
                        }

                        if (totales.includes(concepto)) {
                            data.cell.styles.fontStyle = 'bold';
                            data.cell.styles.fillColor = [248, 249, 250];
                        }

                        if (concepto === 'BALANCE GENERAL NETO') {
                            data.cell.styles.fillColor = [59, 81, 72];
                            data.cell.styles.textColor = [255, 255, 255];
                            data.cell.styles.fontStyle = 'bold';
                            data.cell.styles.fontSize = 10;
                            data.cell.styles.lineWidth = 0;
                        }
                    },
                });

                doc.setFont('helvetica', 'normal');
                doc.setFontSize(8);
                doc.setTextColor(110, 116, 122);
                doc.text(
                    'Nota: Los premios sacados se presentan como referencia. El balance suministrado por el archivo utiliza los premios pagados.',
                    margin,
                    doc.internal.pageSize.getHeight() - 18
                );

                doc.addPage('letter', 'landscape');
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(19);
                doc.setTextColor(41, 50, 60);
                doc.text('Informe Gerencial - Promedios', margin, 38);
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(10);
                doc.setTextColor(90, 98, 108);
                doc.text('Promedio de ventas por cada terminal que vendió en el período.', margin, 58);

                const filasPromediosEstado = (informePdf.bloques || []).map(function (bloque) {
                    return [
                        bloque.nombre,
                        formatoMontoPdf(bloque.ventas),
                        Number(bloque.participacion || 0).toFixed(2) + '%',
                        Number(bloque.terminales || 0).toLocaleString('en-US'),
                        formatoMontoPdf(bloque.promedio_terminal),
                    ];
                });

                doc.autoTable({
                    head: [['Bloque', 'Total vendido', 'Participación', 'Terminales que vendieron', 'Promedio por terminal']],
                    body: filasPromediosEstado,
                    foot: [['Total general', formatoMontoPdf(resumenPdf.balance), '100.00%', 'No acumulable', '-']],
                    startY: 76,
                    margin: { left: margin, right: margin },
                    theme: 'grid',
                    styles: {
                        fontSize: 10,
                        cellPadding: 8,
                        lineColor: [215, 220, 226],
                        lineWidth: 0.5,
                    },
                    headStyles: {
                        fillColor: [41, 59, 80],
                        textColor: [255, 255, 255],
                        fontStyle: 'bold',
                    },
                    footStyles: {
                        fillColor: [235, 238, 241],
                        textColor: [41, 50, 60],
                        fontStyle: 'bold',
                    },
                    columnStyles: {
                        1: { halign: 'right' },
                        2: { halign: 'right' },
                        3: { halign: 'right' },
                        4: { halign: 'right', fontStyle: 'bold' },
                    },
                });

                doc.setFont('helvetica', 'normal');
                doc.setFontSize(8);
                doc.setTextColor(110, 116, 122);
                doc.text(
                    'El promedio por terminal se calcula dividiendo el total vendido entre las terminales que registraron ventas.',
                    margin,
                    doc.lastAutoTable.finalY + 22
                );

                const fechaGeneracion = new Date();
                const fechaArchivo = [
                    fechaGeneracion.getFullYear(),
                    String(fechaGeneracion.getMonth() + 1).padStart(2, '0'),
                    String(fechaGeneracion.getDate()).padStart(2, '0'),
                ].join('-');
                doc.save(`Beneficio Bruto ${fechaArchivo}.pdf`);
            }

            if (pdfButton) {
                pdfButton.addEventListener('click', generarPdfTarjetas);
            }

            if (estadoResultadosPdfButton) {
                estadoResultadosPdfButton.addEventListener('click', generarPdfEstadoResultados);
            }

            const table = $('#tabla-beneficio-bruto');

            if (!table.length || table.find('tbody td[colspan]').length || !$.fn.DataTable) {
                return;
            }

            table.DataTable({
                scrollX: true,
                pageLength: 25,
                order: [[0, 'asc']],
                buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
                dom: 'Bfrtip',
                language: {
                    search: 'Buscar:',
                    lengthMenu: 'Mostrar _MENU_ registros',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ terminales',
                    infoEmpty: 'No hay terminales para mostrar',
                    zeroRecords: 'No se encontraron coincidencias',
                    paginate: {
                        first: 'Primero',
                        last: 'Último',
                        next: 'Siguiente',
                        previous: 'Anterior',
                    },
                },
            });
        });
    </script>
@endsection
