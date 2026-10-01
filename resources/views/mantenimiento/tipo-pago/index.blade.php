@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Tipo Pago</h4>
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('mantenimiento.index') }}">Mantenimiento</a></li>
                        <li class="breadcrumb-item active">Tipo Pago</li>
                    </ol>
                </div>

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-1">Cargar tipos de pago por día</h5>
                        <p class="text-muted mb-0">Se leen la terminal de la columna B y el tipo de pago de la columna W. La fecha corresponde al día de operación, no al momento de cargar el archivo.</p>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('mantenimiento.tipo-pago.store') }}" enctype="multipart/form-data" class="row g-3 align-items-end">
                            @csrf
                            <div class="col-md-3">
                                <label for="fechaCarga" class="form-label">Fecha de operación</label>
                                <input id="fechaCarga" name="fecha" type="date" class="form-control" value="{{ old('fecha', today()->toDateString()) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="archivoTipoPago" class="form-label">Archivo LTKBancas.csv</label>
                                <input id="archivoTipoPago" name="archivo" type="file" accept=".csv,text/csv" class="form-control" required>
                            </div>
                            <div class="col-md-3 d-grid">
                                <button type="submit" class="btn btn-primary"><i class="ri-upload-2-line me-1"></i>Cargar archivo</button>
                            </div>
                        </form>
                        <p class="small text-muted mt-3 mb-0">Si vuelve a cargar la misma fecha, se actualiza cada terminal de ese día. Los días anteriores permanecen guardados.</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex flex-wrap align-items-end justify-content-between gap-3">
                        <div>
                            <h5 class="card-title mb-1">Historial por terminal</h5>
                            <span class="text-muted">Última fecha cargada: {{ $ultimaFecha ?: 'Ninguna' }}</span>
                        </div>
                        <div class="d-flex flex-wrap align-items-end gap-2">
                            <div>
                                <label for="modoConsulta" class="form-label mb-1">Ver</label>
                                <select id="modoConsulta" class="form-select">
                                    <option value="mes" @selected(!$fechaConsulta)>Mes completo</option>
                                    <option value="dia" @selected($fechaConsulta)>Día específico</option>
                                </select>
                            </div>
                            <div id="filtroMes" @class(['d-none' => $fechaConsulta])>
                                <label for="mesConsulta" class="form-label mb-1">Mes</label>
                                <input id="mesConsulta" type="month" class="form-control" value="{{ $mesConsulta }}">
                            </div>
                            <div id="filtroDia" @class(['d-none' => !$fechaConsulta])>
                                <label for="fechaConsulta" class="form-label mb-1">Día</label>
                                <input id="fechaConsulta" type="date" class="form-control" value="{{ $fechaConsulta ?: ($ultimaFecha ?: today()->toDateString()) }}">
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="tablaTipoPago" class="table table-bordered table-striped align-middle w-100">
                                <thead class="table-light">
                                    <tr>
                                        <th>Terminal</th>
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
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modoConsulta = document.getElementById('modoConsulta');
            const mesConsulta = document.getElementById('mesConsulta');
            const fechaConsulta = document.getElementById('fechaConsulta');
            const dataUrl = @json(route('mantenimiento.tipo-pago.data'));
            let tabla = null;
            let consultaActual = 0;

            function filtros() {
                const params = new URLSearchParams();
                if (modoConsulta.value === 'dia') {
                    params.set('fecha', fechaConsulta.value);
                } else {
                    params.set('mes', mesConsulta.value);
                }
                return params;
            }

            async function cargarTabla() {
                const numeroConsulta = ++consultaActual;
                const params = filtros();
                params.set('length', '1');
                const response = await fetch(`${dataUrl}?${params.toString()}`, { headers: { Accept: 'application/json' } });
                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                if (numeroConsulta !== consultaActual) {
                    return;
                }

                if (tabla) {
                    tabla.destroy();
                    $('#tablaTipoPago tbody').empty();
                }

                const fechas = Array.isArray(payload.fechas) ? payload.fechas : [];
                const encabezado = document.querySelector('#tablaTipoPago thead tr');
                encabezado.replaceChildren();
                ['Terminal', ...fechas.map(fecha => `Pago a · ${fecha}`)].forEach(titulo => {
                    const th = document.createElement('th');
                    th.textContent = titulo;
                    encabezado.appendChild(th);
                });

                tabla = $('#tablaTipoPago').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: dataUrl,
                        data: function (data) {
                            if (modoConsulta.value === 'dia') {
                                data.fecha = fechaConsulta.value;
                            } else {
                                data.mes = mesConsulta.value;
                            }
                        },
                    },
                    columns: [
                        { data: 'terminal', render: $.fn.dataTable.render.text() },
                        ...fechas.map(fecha => ({
                            data: null,
                            orderable: false,
                            render: function (data, type, row) {
                                const pago = row.pagos?.[fecha];
                                return pago == null ? '—' : `A ${Number(pago)}`;
                            },
                        })),
                    ],
                    pageLength: 25,
                    order: [],
                    scrollX: fechas.length > 7,
                    language: {
                        processing: 'Cargando...',
                        search: 'Buscar terminal:',
                        lengthMenu: 'Mostrar _MENU_ terminales',
                        info: 'Mostrando _START_ a _END_ de _TOTAL_ terminales',
                        infoEmpty: 'No hay terminales para este período',
                        zeroRecords: 'No hay terminales para esta búsqueda',
                        paginate: { next: 'Siguiente', previous: 'Anterior' },
                    },
                });
            }

            modoConsulta.addEventListener('change', function () {
                document.getElementById('filtroMes').classList.toggle('d-none', this.value !== 'mes');
                document.getElementById('filtroDia').classList.toggle('d-none', this.value !== 'dia');
                cargarTabla();
            });
            mesConsulta.addEventListener('change', cargarTabla);
            fechaConsulta.addEventListener('change', cargarTabla);
            cargarTabla();
        });
    </script>
@endsection
