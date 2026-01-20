@extends('app')

@section('title', 'Dashboard Financiero')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h1 class="h3 mb-0">Dashboard Financiero LotoNet - Ventas por Tipo de Producto</h1>
                            <span id="badge-agencia" class="badge bg-primary fs-6" style="display: none; padding: 8px 12px;">Agencia: <span id="agencia-id-badge" style="font-weight: bold;"></span></span>
                        </div>
                    </div>
                </div>

                <!-- Datepicker -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                        <input type="date" id="fecha_inicio" class="form-control" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-md-3">
                        <label for="fecha_fin" class="form-label">Fecha Fin</label>
                        <input type="date" id="fecha_fin" class="form-control" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button id="filtrar-btn" class="btn btn-primary">Filtrar</button>
                    </div>
                </div>

                <!-- KPIs -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">Total Vendido</h5>
                                <h3 id="kpi-total" class="text-primary">RD$ 0.00</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">Transacciones</h5>
                                <h3 id="kpi-transacciones" class="text-success">0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">Ticket Promedio</h5>
                                <h3 id="kpi-ticket" class="text-info">RD$ 0.00</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mt-3">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">Total Agencias</h5>
                                <h3 id="kpi-agencias" class="text-warning">0</h3>
                                <div class="mt-2">
                                    <button id="btn-ver-agencias" class="btn btn-sm btn-outline-warning">Ver Detalle</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cards por Tipo de Producto -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h5 class="mb-3">Ventas por Tipo de Producto</h5>
                    </div>
                    <div id="cards-container" class="row w-100 mx-0">
                        <!-- Las cards se generarán dinámicamente aquí -->
                    </div>
                </div>

                <!-- Chart Diario -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h5>Ventas por Día (Línea)</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="chart-diario" style="height: 250px; max-height: 250px;"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h5>Ventas por Día (Barras)</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="chart-diario-bar" style="height: 250px; max-height: 250px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h5>Detalle por Tipo</h5>
                            </div>
                            <div class="card-body">
                                <table id="tabla-ventas" class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Total Ventas</th>
                                            <th>Transacciones</th>
                                            <th>Promedio</th>
                                            <th>% del Total</th>
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
@endsection

<!-- Modal para Agencias -->
<div class="modal fade" id="modalAgencias" tabindex="-1" aria-labelledby="modalAgenciasLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAgenciasLabel">Agencias con Ventas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table id="tabla-agencias" class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID Agencia</th>
                            <th>Total Ventas</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

    <script>
        let chartDiarioInstance = null;
        let chartDiarioBarInstance = null;
        let tableInstance = null;
        let agenciasData = [];
        let currentAgenciaId = null;

        function formatCurrency(value) {
            return new Intl.NumberFormat('es-DO', {
                style: 'currency',
                currency: 'DOP'
            }).format(value);
        }

        function loadData(fecha_inicio, fecha_fin, agencia_id = null) {
            currentAgenciaId = agencia_id;
            
            let url = `/ventas-lotonet-dashboard/data?fecha_inicio=${fecha_inicio}&fecha_fin=${fecha_fin}&plataforma=net`;
            if (agencia_id) {
                url += `&agencia_id=${agencia_id}`;
            }

            Swal.fire({
                title: agencia_id ? `Cargando datos de agencia ${agencia_id}...` : 'Cargando datos...',
                text: 'Por favor espera mientras se consultan las ventas.',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    agenciasData = data.agencias || [];

                    // Mostrar/ocultar badge de agencia
                    if (agencia_id) {
                        document.getElementById('badge-agencia').style.display = 'inline-block';
                        document.getElementById('agencia-id-badge').textContent = agencia_id;
                    } else {
                        document.getElementById('badge-agencia').style.display = 'none';
                    }

                    // KPIs
                    document.getElementById('kpi-total').textContent = formatCurrency(data.kpis.total);
                    document.getElementById('kpi-transacciones').textContent = data.kpis.transacciones;
                    document.getElementById('kpi-ticket').textContent = formatCurrency(data.kpis.ticket_promedio);
                    if (!agencia_id) {
                        document.getElementById('kpi-agencias').textContent = data.kpis.total_agencias;
                    }

                    // Cards por tipo
                    const coloresCards = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'];
                    const containerCards = document.getElementById('cards-container');
                    containerCards.innerHTML = '';
                    
                    data.tabla.forEach((item, index) => {
                        const color = coloresCards[index % coloresCards.length];
                        const card = document.createElement('div');
                        card.className = 'col-md-6 col-lg-4 mb-3';
                        card.innerHTML = `
                            <div class="card shadow-sm" style="border-left: 5px solid ${color};">
                                <div class="card-body">
                                    <h5 class="card-title" style="color: ${color};">${item.tipo}</h5>
                                    <div class="mb-2">
                                        <p class="mb-1"><strong>Total:</strong></p>
                                        <h4 class="text-primary">${formatCurrency(item.total)}</h4>
                                    </div>
                                    <div class="row">
                                        <div class="col-6">
                                            <p class="mb-1"><small class="text-muted">Transacciones</small></p>
                                            <p class="h6">${item.transacciones}</p>
                                        </div>
                                        <div class="col-6">
                                            <p class="mb-1"><small class="text-muted">% Total</small></p>
                                            <p class="h6">${item.porcentaje.toFixed(2)}%</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        containerCards.appendChild(card);
                    });

                    // Chart Diario (Línea - Totales)
                    if (chartDiarioInstance) {
                        chartDiarioInstance.destroy();
                        chartDiarioInstance = null;
                    }
                    const ctxDiario = document.getElementById('chart-diario').getContext('2d');
                    
                    // Crear datasets base
                    let datasetsLinea = [{
                        data: data.chart_diario.values,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2,
                        fill: true,
                        pointBackgroundColor: data.chart_diario.values.map(v => v > data.chart_diario.promedio_mes_anterior ? 'red' : 'blue'),
                        pointBorderColor: data.chart_diario.values.map(v => v > data.chart_diario.promedio_mes_anterior ? 'red' : 'blue'),
                        label: 'Ventas Diarias'
                    }, {
                        data: Array(data.chart_diario.labels.length).fill(data.chart_diario.promedio_mes_anterior),
                        borderColor: 'orange',
                        borderWidth: 3,
                        fill: false,
                        pointRadius: 0,
                        label: `Promedio Diario Mes Anterior: ${formatCurrency(data.chart_diario.promedio_mes_anterior)}`
                    }];
                    
                    chartDiarioInstance = new Chart(ctxDiario, {
                        type: 'line',
                        data: {
                            labels: data.chart_diario.labels,
                            datasets: datasetsLinea
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return formatCurrency(context.parsed.y);
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    type: 'category'
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return formatCurrency(value);
                                        }
                                    }
                                }
                            }
                        }
                    });

                    // Chart Diario Barras (Separado por Tipo)
                    if (chartDiarioBarInstance) {
                        chartDiarioBarInstance.destroy();
                        chartDiarioBarInstance = null;
                    }
                    const ctxDiarioBar = document.getElementById('chart-diario-bar').getContext('2d');
                    chartDiarioBarInstance = new Chart(ctxDiarioBar, {
                        type: 'bar',
                        data: {
                            labels: data.chart_diario_tipos.labels,
                            datasets: data.chart_diario_tipos.datasets
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.dataset.label + ': ' + formatCurrency(context.parsed.y);
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    type: 'category',
                                    stacked: false
                                },
                                y: {
                                    beginAtZero: true,
                                    stacked: false,
                                    ticks: {
                                        callback: function(value) {
                                            return formatCurrency(value);
                                        }
                                    }
                                }
                            }
                        }
                    });

                    // Tabla
                    if (tableInstance) {
                        tableInstance.clear().rows.add(data.tabla.map(row => [
                            row.tipo,
                            formatCurrency(row.total),
                            row.transacciones,
                            formatCurrency(row.promedio),
                            row.porcentaje.toFixed(2) + '%'
                        ])).draw();
                    } else {
                        tableInstance = $('#tabla-ventas').DataTable({
                            data: data.tabla.map(row => [
                                row.tipo,
                                formatCurrency(row.total),
                                row.transacciones,
                                formatCurrency(row.promedio),
                                row.porcentaje.toFixed(2) + '%'
                            ]),
                            columns: [{
                                    title: 'Tipo'
                                },
                                {
                                    title: 'Total Ventas'
                                },
                                {
                                    title: 'Transacciones'
                                },
                                {
                                    title: 'Promedio'
                                },
                                {
                                    title: '% del Total'
                                }
                            ],
                            paging: true,
                            searching: true,
                            ordering: true
                        });
                    }
                })
                .catch(error => {
                    Swal.close();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Hubo un problema al cargar los datos. Inténtalo de nuevo.',
                    });
                    console.error('Error:', error);
                });
        }

        document.getElementById('filtrar-btn').addEventListener('click', function() {
            const fecha_inicio = document.getElementById('fecha_inicio').value;
            const fecha_fin = document.getElementById('fecha_fin').value;
            currentAgenciaId = null;
            loadData(fecha_inicio, fecha_fin);
        });

        // Evento para ver detalle de agencias
        document.getElementById('btn-ver-agencias').addEventListener('click', function() {
            if (agenciasData.length === 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'Sin datos',
                    text: 'No hay agencias con ventas en el rango de fechas seleccionado.',
                });
                return;
            }

            // Limpiar y repoblar tabla de agencias
            $('#tabla-agencias tbody').html('');
            agenciasData.forEach(agencia => {
                const row = `<tr>
                    <td>${agencia.agencia_id}</td>
                    <td>${formatCurrency(agencia.total)}</td>
                    <td>
                        <button class="btn btn-sm btn-primary btn-filtrar-agencia" data-agencia-id="${agencia.agencia_id}">
                            Ver Gráficos
                        </button>
                    </td>
                </tr>`;
                $('#tabla-agencias tbody').append(row);
            });

            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('modalAgencias'));
            modal.show();
        });

        // Evento para filtrar por agencia (delegado)
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-filtrar-agencia')) {
                const agenciaId = e.target.getAttribute('data-agencia-id');
                const fecha_inicio = document.getElementById('fecha_inicio').value;
                const fecha_fin = document.getElementById('fecha_fin').value;

                // Cerrar modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('modalAgencias'));
                modal.hide();

                // Cargar datos de la agencia
                loadData(fecha_inicio, fecha_fin, agenciaId);
            }
        });

        // Cargar datos iniciales
        const fecha_inicio = document.getElementById('fecha_inicio').value;
        const fecha_fin = document.getElementById('fecha_fin').value;
        // loadData(fecha_inicio, fecha_fin);
    </script>
@endsection
