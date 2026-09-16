@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-1">Evaluación de agencias</h4>
                        <p class="text-muted mb-0">Compara las ventas mensuales de cada terminal con una meta única.</p>
                    </div>
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('gerencia.index') }}">Gerencia</a></li>
                        <li class="breadcrumb-item active">Evaluación de agencias</li>
                    </ol>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="{{ route('gerencia.evaluacion-agencia') }}" class="row g-3 align-items-end" id="formEvaluacionAgencia">
                            <input type="hidden" name="analizar" value="1">
                            <input type="hidden" name="seleccion_productos" value="1">
                            <div class="col-md-3">
                                <label class="form-label" for="meses">Cantidad de meses a evaluar</label>
                                <input class="form-control" type="number" id="meses" name="meses" min="1" max="24" value="{{ $cantidadMeses }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="meta">Parámetro de venta mensual</label>
                                <div class="input-group">
                                    <span class="input-group-text">RD$</span>
                                    <input class="form-control" type="number" id="meta" name="meta" min="0" step="0.01" value="{{ $meta }}" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="empresa">Empresa</label>
                                <select class="form-select" id="empresa" name="empresa">
                                    <option value="">Todas las empresas</option>
                                    @foreach ($empresas as $empresa)
                                        <option value="{{ $empresa }}" @selected($empresaSeleccionada === $empresa)>{{ $empresa }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label d-block">Tipo de producto</label>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="productos[]" value="tradicional" id="productoTradicional" @checked(in_array('tradicional', $productosSeleccionados, true))>
                                        <label class="form-check-label" for="productoTradicional">Tradicional</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="productos[]" value="no_tradicional" id="productoNoTradicional" @checked(in_array('no_tradicional', $productosSeleccionados, true))>
                                        <label class="form-check-label" for="productoNoTradicional">No tradicional</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="productos[]" value="recargas" id="productoRecargas" @checked(in_array('recargas', $productosSeleccionados, true))>
                                        <label class="form-check-label" for="productoRecargas">Recargas</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button class="btn btn-primary" type="submit"><i class="ri-search-eye-line me-1"></i>Analizar</button>
                                <a class="btn btn-light" href="{{ route('gerencia.evaluacion-agencia') }}">Limpiar</a>
                            </div>
                        </form>
                    </div>
                </div>

                @if ($analizado)
                    <div class="card">
                        <div class="card-header d-flex flex-wrap justify-content-between gap-2">
                            <div>
                                <h5 class="card-title mb-1">Resultado por terminal</h5>
                                <p class="text-muted mb-0">Meta mensual: RD$ {{ number_format($meta, 2) }}</p>
                            </div>
                            <div class="d-flex gap-2 align-items-center">
                                <span class="badge bg-success">Terminales que cumplen: {{ $terminalesCumplen }}</span>
                                <span class="badge bg-danger">Terminales que no cumplen: {{ $terminalesNoCumplen }}</span>
                                <a class="btn btn-success btn-sm" href="{{ route('gerencia.evaluacion-agencia.export.excel', request()->query()) }}">
                                    <i class="ri-file-excel-2-line me-1"></i>Excel
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle w-100" id="tablaEvaluacionAgencia">
                                    <thead>
                                        <tr>
                                            <th>Terminal</th>
                                            <th>Agencia</th>
                                            @foreach ($meses as $mes)
                                                <th>{{ $mes['etiqueta'] }}</th>
                                            @endforeach
                                            <th>Meses que cumple</th>
                                            <th>Meses que no cumple</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($filas as $fila)
                                            <tr>
                                                <td>{{ $fila['terminal'] }}</td>
                                                <td>{{ $fila['agencia'] }}</td>
                                                @foreach ($meses as $mes)
                                                    @php($resultadoMes = $fila['ventas'][$mes['clave']])
                                                    <td class="{{ $resultadoMes['cumple'] ? 'table-success text-success' : 'table-danger text-danger' }} fw-semibold" data-order="{{ $resultadoMes['venta'] }}">
                                                        RD$ {{ number_format($resultadoMes['venta'], 2) }}
                                                    </td>
                                                @endforeach
                                                <td>{{ $fila['meses_cumple'] }} / {{ $meses->count() }}</td>
                                                <td>{{ $fila['meses_no_cumple'] }} / {{ $meses->count() }}</td>
                                                <td data-order="{{ $fila['cumple'] ? 1 : 0 }}">
                                                    <span class="badge {{ $fila['cumple'] ? 'bg-success' : 'bg-danger' }}">
                                                        {{ $fila['cumple'] ? 'Cumple' : 'No cumple' }}
                                                    </span>
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
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('formEvaluacionAgencia');

            form?.addEventListener('submit', function () {
                Swal.fire({
                    title: 'Analizando agencias...',
                    text: 'Estamos consolidando las ventas mensuales por terminal.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading(),
                });
            });

            if (window.jQuery && $.fn.DataTable && document.getElementById('tablaEvaluacionAgencia')) {
                $('#tablaEvaluacionAgencia').DataTable({
                    pageLength: 25,
                    scrollX: true,
                    order: [[1, 'asc']],
                    language: { url: '{{ asset('assets/json/es-ES.json') }}' },
                });
            }
        });
    </script>
@endsection
