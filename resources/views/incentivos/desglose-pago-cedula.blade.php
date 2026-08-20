<div>
    <!-- If you do not have a consistent goal in life, you can not live it in a consistent way. - Marcus Aurelius -->
</div>
@extends('app')

@section('content')
    @php
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
    @endphp

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Desglose de Pago por Cédula</h4>
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('incentivos.index') }}">Incentivos</a></li>
                                <li class="breadcrumb-item active">Desglose de pago</li>
                            </ol>
                        </div>
                    </div>
                </div>

                @if ($periodos->isEmpty())
                    <div class="alert alert-warning">
                        No hay períodos V6 guardados. Guarda primero el período desde el reporte de Incentivos V6.
                    </div>
                @else
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-1">Consultar cálculo individual</h5>
                            <p class="text-muted mb-0">El período más reciente queda seleccionado automáticamente.</p>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="{{ route('incentivos.desglose-pago-cedula.index') }}">
                                <input type="hidden" name="consultar" value="1">
                                <div class="row g-3 align-items-end">
                                    <div class="col-lg-4">
                                        <label for="periodo_id" class="form-label">Período guardado</label>
                                        <select id="periodo_id" name="periodo_id" class="form-select" required>
                                            @foreach ($periodos as $itemPeriodo)
                                                <option value="{{ $itemPeriodo->id }}" @selected($periodo?->id === $itemPeriodo->id)>
                                                    {{ $meses[$itemPeriodo->mes] }} {{ $itemPeriodo->anio }} · Revisión {{ $itemPeriodo->revision }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-lg-5">
                                        <label for="cedula" class="form-label">Cédula</label>
                                        <input id="cedula" name="cedula" class="form-control {{ isset($errors) && $errors->has('cedula') ? 'is-invalid' : '' }}"
                                            value="{{ old('cedula', $cedula) }}" inputmode="numeric" maxlength="13"
                                            placeholder="Ej. 40238509620" required>
                                        @if (isset($errors) && $errors->has('cedula'))
                                            <div class="invalid-feedback">{{ $errors->first('cedula') }}</div>
                                        @endif
                                    </div>
                                    <div class="col-lg-3">
                                        <button class="btn btn-primary w-100" type="submit">
                                            <i class="ri-file-search-line me-1"></i> Generar desglose
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    @if (! $consultado)
                        <div class="card border-info">
                            <div class="card-body py-5 text-center">
                                <i class="ri-file-user-line display-5 text-info"></i>
                                <h5 class="mt-3">Consulta una cédula para visualizar su cálculo</h5>
                                <p class="text-muted mb-0">El reporte utilizará exclusivamente los datos conservados en el período V6.</p>
                            </div>
                        </div>
                    @elseif ($reportes->isEmpty())
                        <div class="alert alert-warning">No se encontró esa cédula en el período seleccionado.</div>
                    @else
                        @foreach ($reportes as $reporte)
                            @php($detalle = $reporte['detalle'])
                            <div class="card border-primary">
                                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <div>
                                        <h5 class="card-title mb-1">{{ $detalle->nombre }}</h5>
                                        <p class="text-muted mb-0">
                                            Cédula {{ $detalle->cedula }} · ID {{ $detalle->empleadoid ?: 'N/D' }} · {{ $detalle->empresa }}
                                        </p>
                                    </div>
                                    <a class="btn btn-danger" href="{{ route('incentivos.desglose-pago-cedula.pdf', $detalle) }}">
                                        <i class="ri-file-pdf-2-line me-1"></i> Descargar PDF
                                    </a>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-3"><div class="border rounded p-3 h-100"><small class="text-muted">Ventas último mes</small><h5 class="mb-0">RD$ {{ number_format($detalle->ventas_ultimo_mes) }}</h5></div></div>
                                        <div class="col-md-3"><div class="border rounded p-3 h-100"><small class="text-muted">Ventas mes actual</small><h5 class="mb-0">RD$ {{ number_format($detalle->ventas_mes_actual) }}</h5></div></div>
                                        <div class="col-md-3"><div class="border rounded p-3 h-100"><small class="text-muted">Días con ventas</small><h5 class="mb-0">{{ $detalle->dias_ventas }}</h5></div></div>
                                        <div class="col-md-3"><div class="border border-success rounded p-3 h-100"><small class="text-muted">Incentivo generado</small><h5 class="mb-0 text-success">RD$ {{ number_format($detalle->incentivo_generado) }}</h5></div></div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-bordered align-middle">
                                            <thead class="table-light">
                                                <tr><th>Tipo</th><th class="text-end">Ventas del tipo</th><th class="text-end">Participación</th><th class="text-center">Días</th><th class="text-end">Premio completo</th><th class="text-end">Incentivo proporcional</th></tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($reporte['tiposPago'] as $tipo)
                                                    <tr>
                                                        <td><span class="badge {{ $tipo['etiqueta'] === '60' ? 'bg-success' : ($tipo['etiqueta'] === '70' ? 'bg-info' : 'bg-warning') }}">Pago {{ $tipo['etiqueta'] }}</span></td>
                                                        <td class="text-end">RD$ {{ number_format($tipo['ventas']) }}</td>
                                                        <td class="text-end fw-semibold">{{ number_format($tipo['porcentaje'], 2) }}%</td>
                                                        <td class="text-center">{{ $tipo['dias'] }}</td>
                                                        <td class="text-end">RD$ {{ number_format($tipo['premio_escala']) }}</td>
                                                        <td class="text-end text-success fw-semibold">RD$ {{ number_format($tipo['incentivo']) }}</td>
                                                    </tr>
                                                    <tr class="table-light">
                                                        <td colspan="6" class="small">
                                                            Fórmula: RD$ {{ number_format($tipo['premio_escala']) }} × {{ number_format($tipo['porcentaje'], 2) }}%
                                                            = RD$ {{ number_format($tipo['incentivo']) }} después del redondeo guardado.
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="6" class="text-center text-muted">Este período no conserva el detalle por tipo de pago.</td></tr>
                                                @endforelse
                                            </tbody>
                                            <tfoot>
                                                <tr class="table-primary fw-semibold">
                                                    <td>Total</td>
                                                    <td class="text-end">RD$ {{ number_format($reporte['resumen']['ventas_desglosadas']) }}</td>
                                                    <td class="text-end">{{ number_format($reporte['resumen']['porcentaje_total'], 2) }}%</td>
                                                    <td></td><td></td>
                                                    <td class="text-end">RD$ {{ number_format($reporte['resumen']['incentivo_desglosado']) }}</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                @endif
            </div>
        </div>
    </div>
@endsection
