@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Consolidado de Retiros y Depósitos por Ruta</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('operaciones.index') }}">Operaciones</a></li>
                                    <li class="breadcrumb-item active">Movimientos por Ruta</li>
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

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-1">Cargar informe de transacciones</h5>
                        <p class="text-muted mb-0">
                            El análisis utiliza Referencia para identificar los retiros marcados como egreso.
                        </p>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('operaciones.movimientos-rutas.procesar') }}" enctype="multipart/form-data" class="row g-3 align-items-end">
                            @csrf
                            <div class="col-lg-8">
                                <label for="archivo_csv" class="form-label">Documento CSV</label>
                                <input type="file" class="form-control" id="archivo_csv" name="archivo_csv" accept=".csv,.txt,text/csv,text/plain" required>
                                <div class="form-text">Tamaño máximo: 50 MB. El archivo se procesa en memoria y no se almacena.</div>
                            </div>
                            <div class="col-lg-4">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="ri-upload-cloud-2-line align-bottom me-1"></i>
                                    Cargar y analizar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                @if (!empty($resumen))
                    <div class="alert alert-info d-flex flex-wrap justify-content-between gap-2">
                        <span>
                            <i class="ri-file-list-3-line me-1"></i>
                            Archivo: <strong>{{ $nombreArchivo }}</strong>
                        </span>
                        <span>
                            Período:
                            <strong>
                                {{ $resumen['fecha_desde'] }}
                                @if ($resumen['fecha_hasta'] !== $resumen['fecha_desde'])
                                    al {{ $resumen['fecha_hasta'] }}
                                @endif
                            </strong>
                        </span>
                    </div>

                    <div class="row g-3 mb-4">
                        @php
                            $tarjetas = [
                                ['Rutas', $resumen['total_rutas'], 'ri-route-line', 'text-primary', false],
                                ['Transacciones', $resumen['total_transacciones'], 'ri-exchange-funds-line', 'text-info', false],
                                ['Depósitos', $resumen['total_depositos'], 'ri-arrow-down-circle-line', 'text-success', true],
                                ['Retiros', $resumen['total_retiros'], 'ri-arrow-up-circle-line', 'text-danger', true],
                                ['Retiro neto', $resumen['retiro_neto'], 'ri-scales-3-line', $resumen['retiro_neto'] >= 0 ? 'text-warning' : 'text-success', true],
                            ];
                        @endphp
                        @foreach ($tarjetas as [$titulo, $valor, $icono, $color, $esMoneda])
                            <div class="col-xl col-md-4 col-sm-6">
                                <div class="card h-100 mb-0">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <p class="text-muted mb-2">{{ $titulo }}</p>
                                                <h4 class="mb-0 {{ $color }}">
                                                    {{ $esMoneda ? 'RD$ '.number_format((float) $valor, 2) : number_format((int) $valor) }}
                                                </h4>
                                            </div>
                                            <i class="{{ $icono }} fs-22 {{ $color }}"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="alert alert-light border d-flex flex-wrap justify-content-between gap-2">
                        <span><strong>{{ number_format($control['filas_aceptadas']) }}</strong> filas aceptadas</span>
                        <span><strong>{{ number_format($control['filas_descartadas']) }}</strong> descartadas</span>
                        <span>{{ number_format($control['descartadas_tipo']) }} de otros tipos</span>
                        <span>{{ number_format($control['duplicadas']) }} duplicadas</span>
                        <span>{{ number_format($control['inconsistentes']) }} inconsistentes</span>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-12">
                            <div class="card h-100 mb-0">
                                <div class="card-header">
                                    <h5 class="card-title mb-1">Rutas con mayor movimiento</h5>
                                    <p class="text-muted mb-0">Depósitos frente a retiros en las 25 rutas con mayor movimiento.</p>
                                </div>
                                <div class="card-body">
                                    <div id="grafico-movimientos-rutas" style="min-height: 500px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="card">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <h5 class="card-title mb-1">Resumen por ruta</h5>
                            <p class="text-muted mb-0">Retiro neto = retiros − depósitos. Los retiros se muestran como valores positivos.</p>
                        </div>
                        @if (!empty($rutas))
                            <button type="button" class="btn btn-danger" id="btn-pdf-movimientos">
                                <i class="ri-file-pdf-2-line align-bottom me-1"></i>
                                Generar PDF
                            </button>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle w-100" id="tabla-movimientos-rutas">
                                <thead class="table-light">
                                    <tr>
                                        <th>Ruta</th>
                                        <th class="text-end">Transacciones</th>
                                        <th class="text-end">Depósitos</th>
                                        <th class="text-end">Retiros</th>
                                        <th class="text-end">Retiro neto</th>
                                        <th class="text-center">Ver</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($rutas as $ruta)
                                        <tr>
                                            <td>{{ $ruta['ruta'] }}</td>
                                            <td class="text-end">{{ number_format($ruta['transacciones']) }}</td>
                                            <td class="text-end">RD$ {{ number_format($ruta['depositos'], 2) }}</td>
                                            <td class="text-end">RD$ {{ number_format($ruta['retiros'], 2) }}</td>
                                            <td class="text-end fw-semibold {{ $ruta['neto'] >= 0 ? 'text-warning' : 'text-success' }}">
                                                RD$ {{ number_format($ruta['neto'], 2) }}
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-soft-primary btn-ver-ruta" data-ruta-key="{{ $ruta['ruta_key'] }}">
                                                    <i class="ri-eye-line me-1"></i>Ver
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">Carga un CSV para generar el reporte.</td>
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

    <div class="modal fade" id="modal-detalle-ruta" tabindex="-1" aria-labelledby="titulo-modal-detalle-ruta" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="titulo-modal-detalle-ruta">Detalle de ruta</h5>
                        <p class="text-muted mb-0" id="resumen-modal-detalle-ruta"></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Transacción</th>
                                    <th>Terminal</th>
                                    <th>Nombre de agencia</th>
                                    <th>Tipo</th>
                                    <th class="text-end">Monto original</th>
                                </tr>
                            </thead>
                            <tbody id="detalle-ruta-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="{{ asset('libs/apexcharts/apexcharts.min.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.0/jspdf.plugin.autotable.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const rutas = @json($rutas ?? []);
            const transacciones = @json($transacciones ?? []);
            const graficoRutas = @json($grafico_rutas ?? []);
            const resumen = @json($resumen);
            const nombreArchivo = @json($nombreArchivo);
            const tabla = $('#tabla-movimientos-rutas');

            function moneda(valor) {
                return 'RD$ ' + Number(valor || 0).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            if (rutas.length && tabla.length) {
                tabla.DataTable({
                    responsive: true,
                    pageLength: 25,
                    order: [[0, 'asc']],
                    columnDefs: [{ orderable: false, targets: 5 }],
                    language: {
                        search: 'Buscar:',
                        lengthMenu: 'Mostrar _MENU_ registros',
                        info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                        infoEmpty: 'Mostrando 0 a 0 de 0 registros',
                        emptyTable: 'No hay datos disponibles',
                        paginate: { next: 'Siguiente', previous: 'Anterior' }
                    }
                });
            }

            if (typeof ApexCharts !== 'undefined' && graficoRutas.length) {
                new ApexCharts(document.querySelector('#grafico-movimientos-rutas'), {
                    chart: {
                        type: 'bar',
                        height: Math.max(500, graficoRutas.length * 34),
                        toolbar: { show: false }
                    },
                    series: [
                        { name: 'Depósitos', data: graficoRutas.map(item => item.depositos) },
                        { name: 'Retiros', data: graficoRutas.map(item => item.retiros) }
                    ],
                    colors: ['#0ab39c', '#f06548'],
                    plotOptions: {
                        bar: {
                            horizontal: true,
                            barHeight: '68%',
                            borderRadius: 3
                        }
                    },
                    dataLabels: { enabled: false },
                    xaxis: {
                        categories: graficoRutas.map(item => item.ruta),
                        labels: {
                            formatter: value => 'RD$ ' + Number(value).toLocaleString('en-US', { maximumFractionDigits: 0 })
                        }
                    },
                    yaxis: { labels: { maxWidth: 310 } },
                    tooltip: { y: { formatter: moneda } },
                    legend: { position: 'top' },
                    grid: { borderColor: '#e9ebec' }
                }).render();
            }

            const modalElement = document.getElementById('modal-detalle-ruta');
            const modal = modalElement ? new bootstrap.Modal(modalElement) : null;
            const escaparHtml = valor => String(valor).replace(
                /[&<>"']/g,
                caracter => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[caracter]
            );

            document.addEventListener('click', function (event) {
                const boton = event.target.closest('.btn-ver-ruta');

                if (!boton || !modal) {
                    return;
                }

                const rutaKey = boton.dataset.rutaKey;
                const ruta = rutas.find(item => item.ruta_key === rutaKey);
                const detalle = transacciones.filter(item => item.ruta_key === rutaKey);
                const fechas = [...new Map(
                    detalle.map(item => [item.fecha_iso, item.fecha])
                ).entries()]
                    .sort((fechaA, fechaB) => fechaA[0].localeCompare(fechaB[0]))
                    .map(fecha => fecha[1]);
                const periodo = fechas.length > 1
                    ? `${fechas[0]} al ${fechas[fechas.length - 1]}`
                    : (fechas[0] || '-');
                document.getElementById('titulo-modal-detalle-ruta').textContent = ruta ? ruta.ruta : 'Detalle de ruta';
                document.getElementById('resumen-modal-detalle-ruta').textContent = ruta
                    ? `Fecha: ${periodo} · ${ruta.transacciones} transacciones · Depósitos ${moneda(ruta.depositos)} · Retiros ${moneda(ruta.retiros)}`
                    : '';
                document.getElementById('detalle-ruta-body').innerHTML = detalle.map(item => `
                    <tr>
                        <td>${escaparHtml(item.id_trans)}</td>
                        <td>${escaparHtml(item.terminal || '-')}</td>
                        <td>${escaparHtml(item.nombre_agencia || 'Terminal no registrada')}</td>
                        <td>
                            <span class="badge ${item.tipo === 'deposito' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'}">
                                ${escaparHtml(item.tipo_etiqueta)}
                            </span>
                        </td>
                        <td class="text-end ${item.monto_original < 0 ? 'text-danger' : 'text-success'}">${moneda(item.monto_original)}</td>
                    </tr>
                `).join('');
                modal.show();
            });

            const btnPdf = document.getElementById('btn-pdf-movimientos');

            if (btnPdf) {
                btnPdf.addEventListener('click', function () {
                    if (!window.jspdf?.jsPDF) {
                        alert('No se pudo cargar la librería para generar el PDF.');
                        return;
                    }

                    const doc = new window.jspdf.jsPDF({ orientation: 'landscape', unit: 'pt', format: 'letter' });
                    const margen = 36;
                    const ancho = doc.internal.pageSize.getWidth();

                    doc.setFontSize(17);
                    doc.text('Consolidado de Retiros y Depósitos por Ruta', margen, 40);
                    doc.setFontSize(9);
                    doc.setTextColor(100);
                    doc.text(`Archivo: ${nombreArchivo || '-'}`, margen, 58);
                    doc.text(`Generado: ${new Date().toLocaleString('es-DO')}`, ancho - margen, 58, { align: 'right' });

                    doc.autoTable({
                        head: [['Rutas', 'Transacciones', 'Depósitos', 'Retiros', 'Retiro neto']],
                        body: [[
                            Number(resumen?.total_rutas || 0).toLocaleString('en-US'),
                            Number(resumen?.total_transacciones || 0).toLocaleString('en-US'),
                            moneda(resumen?.total_depositos),
                            moneda(resumen?.total_retiros),
                            moneda(resumen?.retiro_neto)
                        ]],
                        startY: 75,
                        theme: 'grid',
                        margin: { left: margen, right: margen },
                        headStyles: { fillColor: [64, 81, 137] }
                    });

                    doc.autoTable({
                        head: [['Ruta', 'Transacciones', 'Depósitos', 'Retiros', 'Retiro neto']],
                        body: rutas.map(item => [
                            item.ruta,
                            item.transacciones,
                            moneda(item.depositos),
                            moneda(item.retiros),
                            moneda(item.neto)
                        ]),
                        startY: doc.lastAutoTable.finalY + 16,
                        theme: 'grid',
                        margin: { left: margen, right: margen, bottom: 28 },
                        headStyles: { fillColor: [64, 81, 137] },
                        styles: { fontSize: 8, cellPadding: 4 },
                        columnStyles: {
                            0: { cellWidth: 250 },
                            1: { halign: 'right' },
                            2: { halign: 'right' },
                            3: { halign: 'right' },
                            4: { halign: 'right', fontStyle: 'bold' }
                        }
                    });

                    doc.save('movimientos_por_ruta.pdf');
                });
            }
        });
    </script>
@endsection
