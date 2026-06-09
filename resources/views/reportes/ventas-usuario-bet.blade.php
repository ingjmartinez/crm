@extends('app')

@section('content')
    <div class="main-content">

        <div class="page-content">
            <div class="container-fluid">

                <!-- start page title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Ventas por Usuario</h4>

                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('reportes.index') }}">Reportes</a></li>
                                    <li class="breadcrumb-item active">Ventas por Usuario</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end page title -->

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h5 class="card-title mb-0">Ventas por Usuario</h5>

                                <div class="d-flex gap-3 align-items-center justify-content-between flex-wrap">
                                    <div>
                                        <label class="mb-0" for="empresa">Empresa</label>
                                        <select id="empresa" class="form-control">
                                            <option value="todos">Todas</option>
                                            <option value="grupo_joselito">Grupo Joselito</option>
                                            <option value="negosur">Negosur</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-0" for="fecha_inicio">Desde</label>
                                        <input type="date" class="form-control" id="fecha_inicio" value="{{ date('Y-m-01') }}">
                                    </div>
                                    <div>
                                        <label class="mb-0" for="fecha_fin">Hasta</label>
                                        <input type="date" class="form-control" id="fecha_fin" value="{{ date('Y-m-d') }}">
                                    </div>

                                    <button id="btnFiltrar" class="btn btn-primary">
                                        Filtrar
                                    </button>

                                    <button id="btnExportarExcel" class="btn btn-success">
                                        Exportar Excel
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive"style="width:100%; height:525px; max-height:525px; overflow-y:scroll;">
                                    <table id="tableEmpleados"
                                        class="table table-bordered dt-responsive nowrap table-striped align-middle"
                                        style="width:100%; height:525px; max-height:525px; overflow-y:scroll;">
                                        <thead>
                                            <tr>
                                                <th>Cedula</th>
                                                <th>Nombre</th>
                                                <th>Tradicional</th>
                                                <th>No tradicional</th>
                                                <th>Recargas</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                                <div class="d-none flex-sm-fill d-sm-flex align-items-sm-center justify-content-sm-between">
                                    <div>
                                        <p class="small text-muted">
                                            Mostrando
                                            <span id="fromPage" class="fw-semibold">0</span>
                                            de
                                            <span id="toPage" class="fw-semibold">0</span>
                                            entradas. Total
                                            <span id="totalPages" class="fw-semibold">0</span>
                                            entradas.
                                        </p>
                                    </div>

                                    <div>
                                        <ul id="pagination" class="pagination"></ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!--end row-->
            </div>
            <!-- container-fluid -->
        </div>
        <!-- End Page-content -->

        <footer class="footer">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <script>
                            document.write(new Date().getFullYear())
                        </script> © Velzon.
                    </div>
                    <div class="col-sm-6">
                        <div class="text-sm-end d-none d-sm-block">
                            Design & Develop by Themesbrand
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </div>
@endsection

@section('script')
    <script>
        document.getElementById('btnFiltrar').addEventListener('click', function(event) {
            listVentasUsuarioBet(1, event);
        });

        function listVentasUsuarioBet(page, event) {
            if (event) {
                event.preventDefault();
            }
            const params = filtrosReporte(page);
            const url = `/reportes-ventas-usuario-bet/list?${params.toString()}`;

            Swal.fire({
                title: "Cargando data ...",
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                timerProgressBar: true,
                didOpen: () => Swal.showLoading()
            });

            const tableBody = document.querySelector('#tableEmpleados tbody');
            tableBody.innerHTML = '';

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    data.data.forEach(row => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${escapeHtml(row.cedula)}</td>
                            <td>${escapeHtml(row.nombre)}</td>
                            <td class="text-end">${formatMoney(row.tradicional)}</td>
                            <td class="text-end">${formatMoney(row.no_tradicional)}</td>
                            <td class="text-end">${formatMoney(row.recargas)}</td>
                            <td class="text-end fw-semibold">${formatMoney(row.total)}</td>
                        `;
                        tableBody.appendChild(tr);
                    });

                    document.getElementById('pagination').innerHTML = '';

                    document.getElementById('fromPage').innerText = data.from;
                    document.getElementById('toPage').innerText = data.to;
                    document.getElementById('totalPages').innerText = data.total;

                    data.links.forEach(link => {
                        const li = document.createElement('li');
                        li.classList.add('page-item');
                        if (link.active) {
                            li.classList.add('active');
                        }
                        if (!link.url) {
                            li.classList.add('disabled');
                        }

                        const a = document.createElement(link.url ? 'a' : 'span');
                        a.classList.add('page-link');
                        a.innerHTML = link.label;
                        if (link.url) {
                            a.href = link.url;
                            a.setAttribute('onclick', `listVentasUsuarioBet(${link.page}, event)`);
                        }

                        li.appendChild(a);
                        document.getElementById('pagination').appendChild(li);

                        Swal.close();
                    });
                })
                .catch(error => {
                    Swal.close();
                    console.error('Error fetching data:', error);
                });
        }

        document.getElementById('btnExportarExcel').addEventListener('click', function() {
            const params = filtrosReporte();
            params.delete('page');
            const url = `/reportes-ventas-usuario-bet/excel?${params.toString()}`;
            window.open(url, '_blank');
        });

        function filtrosReporte(page = null) {
            const params = new URLSearchParams({
                empresa: document.getElementById('empresa').value,
                fecha_inicio: document.getElementById('fecha_inicio').value,
                fecha_fin: document.getElementById('fecha_fin').value
            });

            if (page !== null) {
                params.set('page', page);
            }

            return params;
        }

        function formatMoney(value) {
            return Number(value || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, character => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[character]));
        }
    </script>
@endsection
