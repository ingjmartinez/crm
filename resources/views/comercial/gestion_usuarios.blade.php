@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Gestion de Usuarios</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('comercial.index') }}">Comercial</a></li>
                                    <li class="breadcrumb-item active">Gestion de Usuarios</li>
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

                @foreach ($errores ?? [] as $error)
                    <div class="alert alert-warning">{{ $error }}</div>
                @endforeach

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-1">Carga de archivo CSV</h5>
                                <p class="text-muted mb-0">Carga el reporte para resumir ventas por cedula.</p>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('comercial.gestion-usuarios.analizar') }}" enctype="multipart/form-data" class="row g-3 align-items-end">
                                    @csrf
                                    <div class="col-lg-8">
                                        <label for="archivo" class="form-label">Documento CSV</label>
                                        <input type="file" class="form-control" id="archivo" name="archivo" accept=".csv,.txt,text/csv,text/plain" required>
                                    </div>
                                    <div class="col-lg-4">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="ri-upload-cloud-2-line align-bottom me-1"></i>
                                            Cargar CSV
                                        </button>
                                    </div>
                                </form>

                                @if (!empty($archivoNombre))
                                    <div class="alert alert-info mt-3 mb-0">
                                        Archivo procesado: <strong>{{ $archivoNombre }}</strong>
                                        @if (!empty($periodoReporte))
                                            <br>
                                            Periodo detectado: <strong>{{ $periodoReporte }}</strong>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Datos procesados</h5>
                            </div>
                            <div class="card-body">
                                @if (($totalFilas ?? 0) > 0)
                                    <div class="alert alert-info py-2">
                                        CSV procesado: {{ number_format($totalFilas) }} registros.
                                        Cedulas resumidas: {{ number_format($totalCedulas ?? count($filas ?? [])) }}.
                                        Mostrando {{ number_format(count($filas ?? [])) }}.
                                    </div>
                                @endif

                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle w-100" id="table-gestion-usuarios">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Numero</th>
                                                <th>Banca</th>
                                                <th>Grupo</th>
                                                <th>Region</th>
                                                <th>Ruta</th>
                                                <th>Cedula</th>
                                                <th>Descripcion</th>
                                                <th>Producto</th>
                                                <th class="text-end">Tickets</th>
                                                <th class="text-end">Venta Trad.</th>
                                                <th class="text-end">Venta No Trad.</th>
                                                <th class="text-end">Recarga</th>
                                                <th class="text-end">Externa</th>
                                                <th class="text-end">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse (($filas ?? []) as $fila)
                                                <tr>
                                                    <td>{{ $fila['numero_externo'] }}</td>
                                                    <td>{{ $fila['banca'] }}</td>
                                                    <td>{{ $fila['grupo'] }}</td>
                                                    <td>{{ $fila['region'] }}</td>
                                                    <td>{{ $fila['ruta'] }}</td>
                                                    <td>{{ $fila['cedula'] }}</td>
                                                    <td>{{ $fila['descripcion'] }}</td>
                                                    <td>{{ $fila['producto'] }}</td>
                                                    <td class="text-end">{{ number_format($fila['tickets_tradicional']) }}</td>
                                                    <td class="text-end">{{ number_format($fila['venta_tradicional_total'], 2) }}</td>
                                                    <td class="text-end">{{ number_format($fila['venta_no_tradicional_producto'], 2) }}</td>
                                                    <td class="text-end">{{ number_format($fila['venta_recarga'], 2) }}</td>
                                                    <td class="text-end">{{ number_format($fila['venta_externa_total'], 2) }}</td>
                                                    <td class="text-end">{{ number_format($fila['total_general'], 2) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="14" class="text-center text-muted">Carga un CSV para ver el resumen por cedula.</td>
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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const table = $('#table-gestion-usuarios');

            if (!table.length || table.find('tbody tr td[colspan]').length) {
                return;
            }

            table.DataTable({
                responsive: false,
                scrollX: true,
                pageLength: 25,
                order: [[1, 'asc'], [7, 'asc']],
                language: {
                    url: '/json/es-DO.json',
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
        });
    </script>
@endsection
