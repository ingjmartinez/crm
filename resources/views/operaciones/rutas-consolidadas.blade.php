@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Rutas Consolidadas</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('operaciones.index') }}">Operaciones</a></li>
                                    <li class="breadcrumb-item active">Rutas Consolidadas</li>
                                </ol>
                            </div>
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

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div>
                                    <h5 class="card-title mb-1">Carga de balance consolidado</h5>
                                    <p class="text-muted mb-0">Se limpiaran las columnas Ruta, Deposito, Retiro y Neto retiro-deposito.</p>
                                </div>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('operaciones.rutas-consolidadas.procesar') }}" enctype="multipart/form-data" class="row g-3 align-items-end">
                                    @csrf
                                    <div class="col-lg-8">
                                        <label for="archivo_csv" class="form-label">Documento CSV</label>
                                        <input type="file" class="form-control" id="archivo_csv" name="archivo_csv" accept=".csv,text/csv,text/plain" required>
                                    </div>
                                    <div class="col-lg-4">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="ri-upload-cloud-2-line align-bottom me-1"></i>
                                            Cargar y limpiar CSV
                                        </button>
                                    </div>
                                </form>

                                @if (!empty($nombreArchivo))
                                    <div class="alert alert-info mt-3 mb-0">
                                        Archivo procesado: <strong>{{ $nombreArchivo }}</strong>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                @if (!empty($resumen))
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body">
                                    <p class="text-muted mb-1">Rutas</p>
                                    <h4 class="mb-0">{{ number_format($resumen['total_rutas'] ?? 0) }}</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body">
                                    <p class="text-muted mb-1">Deposito</p>
                                    <h4 class="mb-0">{{ number_format($resumen['total_deposito'] ?? 0, 2) }}</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body">
                                    <p class="text-muted mb-1">Retiro</p>
                                    <h4 class="mb-0">{{ number_format($resumen['total_retiro'] ?? 0, 2) }}</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body">
                                    <p class="text-muted mb-1">Neto retiro-deposito</p>
                                    @php($totalNeto = (float) ($resumen['total_neto'] ?? 0))
                                    <h4 class="mb-0 {{ $totalNeto < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($totalNeto, 2) }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <h5 class="card-title mb-0">Datos limpios</h5>
                                @if (($filas ?? collect())->isNotEmpty())
                                    <button type="button" class="btn btn-danger" id="btn-generar-pdf-rutas">
                                        <i class="ri-file-pdf-2-line align-bottom me-1"></i>
                                        Generar PDF
                                    </button>
                                @endif
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle w-100" id="table-rutas-consolidadas">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Ruta</th>
                                                <th class="text-end">Deposito</th>
                                                <th class="text-end">Retiro</th>
                                                <th class="text-end">Neto retiro-deposito</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse(($filas ?? collect()) as $fila)
                                                @php($neto = (float) ($fila['neto'] ?? 0))
                                                <tr>
                                                    <td>{{ $fila['ruta'] }}</td>
                                                    <td class="text-end">{{ number_format((float) $fila['deposito'], 2) }}</td>
                                                    <td class="text-end">{{ number_format((float) $fila['retiro'], 2) }}</td>
                                                    <td class="text-end {{ $neto < 0 ? 'text-danger' : 'text-success' }} fw-semibold">{{ number_format($neto, 2) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="text-center text-muted">Carga un CSV para ver la data limpia.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.0/jspdf.plugin.autotable.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const table = $('#table-rutas-consolidadas');
            const filasPdf = @json(($filas ?? collect())->values());
            const resumenPdf = @json($resumen);
            const nombreArchivo = @json($nombreArchivo);
            const btnGenerarPdf = document.getElementById('btn-generar-pdf-rutas');

            if (!table.length || table.find('tbody tr td[colspan]').length) {
                return;
            }

            table.DataTable({
                responsive: true,
                pageLength: 25,
                order: [[0, 'asc']],
                language: {
                    search: 'Buscar:',
                    lengthMenu: 'Mostrar _MENU_ registros',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                    infoEmpty: 'Mostrando 0 a 0 de 0 registros',
                    emptyTable: 'No hay datos disponibles',
                    paginate: {
                        first: 'Primero',
                        last: 'Ultimo',
                        next: 'Siguiente',
                        previous: 'Anterior'
                    }
                }
            });

            function formatoNumero(valor) {
                return Number(valor || 0).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function generarPdfRutasConsolidadas() {
                if (!filasPdf.length) {
                    return;
                }

                if (!window.jspdf || !window.jspdf.jsPDF) {
                    alert('No se pudo cargar la libreria para generar el PDF.');
                    return;
                }

                const doc = new window.jspdf.jsPDF({
                    orientation: 'landscape',
                    unit: 'pt',
                    format: 'letter'
                });
                const pageWidth = doc.internal.pageSize.getWidth();
                const pageHeight = doc.internal.pageSize.getHeight();
                const margin = 36;
                const now = new Date();
                const fecha = now.toLocaleDateString('es-DO');
                const hora = now.toLocaleTimeString('es-DO', {
                    hour: '2-digit',
                    minute: '2-digit'
                });

                doc.setFontSize(18);
                doc.setTextColor(33, 37, 41);
                doc.text('Rutas Consolidadas', margin, 42);

                doc.setFontSize(10);
                doc.setTextColor(100, 100, 100);
                doc.text('Archivo: ' + (nombreArchivo || '-'), margin, 60);
                doc.text('Generado: ' + fecha + ' ' + hora, pageWidth - margin, 60, { align: 'right' });

                const resumen = resumenPdf || {};
                const resumenRows = [
                    ['Rutas', Number(resumen.total_rutas || 0).toLocaleString('en-US')],
                    ['Deposito', formatoNumero(resumen.total_deposito)],
                    ['Retiro', formatoNumero(resumen.total_retiro)],
                    ['Neto retiro-deposito', formatoNumero(resumen.total_neto)]
                ];

                doc.autoTable({
                    body: [resumenRows.map(row => row[0]), resumenRows.map(row => row[1])],
                    startY: 82,
                    margin: { left: margin, right: margin },
                    theme: 'grid',
                    styles: {
                        fontSize: 9,
                        cellPadding: 6,
                        halign: 'center',
                        lineColor: [220, 220, 220],
                        lineWidth: 0.5
                    },
                    bodyStyles: {
                        fillColor: [248, 249, 250]
                    },
                    didParseCell: function (data) {
                        if (data.row.index === 0) {
                            data.cell.styles.fontStyle = 'bold';
                            data.cell.styles.textColor = [80, 80, 80];
                        }
                        if (data.row.index === 1 && data.column.index === 3) {
                            const neto = Number(resumen.total_neto || 0);
                            data.cell.styles.fontStyle = 'bold';
                            data.cell.styles.textColor = neto < 0 ? [220, 53, 69] : [25, 135, 84];
                        }
                    }
                });

                const body = filasPdf.map(function (fila) {
                    return [
                        fila.ruta || '-',
                        formatoNumero(fila.deposito),
                        formatoNumero(fila.retiro),
                        formatoNumero(fila.neto)
                    ];
                });

                doc.autoTable({
                    head: [['Ruta', 'Deposito', 'Retiro', 'Neto retiro-deposito']],
                    body,
                    startY: doc.lastAutoTable.finalY + 18,
                    margin: { left: margin, right: margin, top: 24, bottom: 28 },
                    theme: 'grid',
                    styles: {
                        fontSize: 8.5,
                        cellPadding: 4,
                        overflow: 'linebreak',
                        valign: 'middle',
                        lineColor: [225, 225, 225],
                        lineWidth: 0.5
                    },
                    headStyles: {
                        fillColor: [41, 128, 185],
                        textColor: [255, 255, 255],
                        halign: 'center',
                        fontStyle: 'bold'
                    },
                    columnStyles: {
                        0: { cellWidth: 310 },
                        1: { halign: 'right' },
                        2: { halign: 'right' },
                        3: { halign: 'right' }
                    },
                    didParseCell: function (data) {
                        if (data.section !== 'body' || data.column.index !== 3) {
                            return;
                        }

                        const value = Number(String(data.cell.raw || '').replace(/,/g, ''));
                        data.cell.styles.fontStyle = 'bold';
                        data.cell.styles.textColor = value < 0 ? [220, 53, 69] : [25, 135, 84];
                    },
                    didDrawPage: function () {
                        doc.setFontSize(9);
                        doc.setTextColor(120, 120, 120);
                        doc.text('Rutas Consolidadas', margin, pageHeight - 14);
                        doc.text('Pagina ' + doc.internal.getNumberOfPages(), pageWidth - margin, pageHeight - 14, { align: 'right' });
                    }
                });

                doc.save('rutas_consolidadas.pdf');
            }

            if (btnGenerarPdf) {
                btnGenerarPdf.addEventListener('click', generarPdfRutasConsolidadas);
            }
        });
    </script>
@endsection
