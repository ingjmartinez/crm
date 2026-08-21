@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Ingresos Loteka</h4>
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('contabilidad.index') }}">Contabilidad</a></li>
                                <li class="breadcrumb-item active">Ingresos Loteka</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-1">Ventas de juegos no tradicionales</h5>
                        <p class="text-muted mb-0">Ventas agrupadas por terminal, empresa y centro de costo.</p>
                    </div>
                    <div class="card-body">
                        <form id="formIngresosLoteka" method="GET" action="{{ route('contabilidad.reportes.ingresos-loteka') }}">
                            <input type="hidden" name="consultar" value="1">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-6 col-lg-2">
                                    <label for="fecha_inicio" class="form-label">Desde</label>
                                    <input id="fecha_inicio" name="fecha_inicio" type="date"
                                        class="form-control {{ isset($errors) && $errors->has('fecha_inicio') ? 'is-invalid' : '' }}"
                                        value="{{ old('fecha_inicio', $fechaInicio) }}" required>
                                    @if (isset($errors) && $errors->has('fecha_inicio'))
                                        <div class="invalid-feedback">{{ $errors->first('fecha_inicio') }}</div>
                                    @endif
                                </div>
                                <div class="col-md-6 col-lg-2">
                                    <label for="fecha_fin" class="form-label">Hasta</label>
                                    <input id="fecha_fin" name="fecha_fin" type="date"
                                        class="form-control {{ isset($errors) && $errors->has('fecha_fin') ? 'is-invalid' : '' }}"
                                        value="{{ old('fecha_fin', $fechaFin) }}" required>
                                    @if (isset($errors) && $errors->has('fecha_fin'))
                                        <div class="invalid-feedback">{{ $errors->first('fecha_fin') }}</div>
                                    @endif
                                </div>
                                <div class="col-md-6 col-lg-2">
                                    <label for="empresa" class="form-label">Empresa</label>
                                    <select id="empresa" name="empresa"
                                        class="form-select {{ isset($errors) && $errors->has('empresa') ? 'is-invalid' : '' }}">
                                        <option value="todas" @selected(old('empresa', $empresa) === 'todas')>Todas</option>
                                        <option value="168" @selected(old('empresa', $empresa) === '168')>168 - Grupo Joselito</option>
                                        <option value="169" @selected(old('empresa', $empresa) === '169')>169 - Negosur</option>
                                    </select>
                                    @if (isset($errors) && $errors->has('empresa'))
                                        <div class="invalid-feedback">{{ $errors->first('empresa') }}</div>
                                    @endif
                                </div>
                                <div class="col-md-6 col-lg-3">
                                    <label for="monto_loteka" class="form-label">Monto Loteka</label>
                                    @php($montoLotekaMostrado = (float) old('monto_loteka', $montoLoteka) > 0 ? number_format((float) old('monto_loteka', $montoLoteka), 2, '.', ',') : '')
                                    <div class="input-group">
                                        <span class="input-group-text">RD$</span>
                                        <input id="monto_loteka" name="monto_loteka" type="text" inputmode="decimal"
                                            maxlength="18" autocomplete="off"
                                            class="form-control {{ isset($errors) && $errors->has('monto_loteka') ? 'is-invalid' : '' }}"
                                            value="{{ $montoLotekaMostrado }}"
                                            placeholder="Ej. 10,000,000.00">
                                        @if (isset($errors) && $errors->has('monto_loteka'))
                                            <div class="invalid-feedback">{{ $errors->first('monto_loteka') }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-12 col-lg-3 d-grid">
                                    <button class="btn btn-primary" id="generarIngresosLoteka" type="submit">
                                        <i class="ri-file-chart-line me-1"></i> Generar reporte
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                @if (! $consultado)
                    <div class="card border-info">
                        <div class="card-body py-5 text-center">
                            <i class="ri-calendar-check-line display-5 text-info"></i>
                            <h5 class="mt-3">Selecciona el período que deseas consultar</h5>
                            <p class="text-muted mb-0">Solo se incluirán terminales con ventas no tradicionales mayores que cero.</p>
                        </div>
                    </div>
                @else
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-xl-3">
                            <div class="card mb-0"><div class="card-body">
                                <p class="text-muted mb-1">Terminales con ventas</p>
                                <h4 class="mb-0">{{ number_format($registros->count()) }}</h4>
                            </div></div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="card mb-0 border-success"><div class="card-body">
                                <p class="text-muted mb-1">Monto total</p>
                                <h4 class="mb-0 text-success">RD$ {{ number_format($totalMonto, 2) }}</h4>
                            </div></div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="card mb-0 border-primary"><div class="card-body">
                                <p class="text-muted mb-1">Monto Loteka distribuido</p>
                                <h4 class="mb-0 text-primary">RD$ {{ number_format($totalDistribuido, 2) }}</h4>
                            </div></div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="card mb-0 {{ $sinCentroCosto > 0 ? 'border-warning' : '' }}"><div class="card-body">
                                <p class="text-muted mb-1">Sin centro de costo</p>
                                <h4 class="mb-0 {{ $sinCentroCosto > 0 ? 'text-warning' : '' }}">{{ number_format($sinCentroCosto) }}</h4>
                            </div></div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        @foreach ($resumenEmpresas as $resumenEmpresa)
                            <div class="col-lg-6">
                                <div class="card h-100 mb-0 border-primary">
                                    <div class="card-body">
                                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                                            <div>
                                                <span class="badge bg-primary-subtle text-primary mb-2">Empresa {{ $resumenEmpresa['empresa_id'] }}</span>
                                                <h4 class="mb-0">{{ $resumenEmpresa['nombre'] }}</h4>
                                            </div>
                                            <div class="text-end">
                                                <p class="text-muted mb-1">Participación</p>
                                                <h3 class="text-primary mb-0">{{ number_format($resumenEmpresa['participacion'], 2) }}%</h3>
                                            </div>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-sm-4">
                                                <p class="text-muted mb-1">Ventas base</p>
                                                <h5 class="mb-0">RD$ {{ number_format($resumenEmpresa['ventas'], 2) }}</h5>
                                            </div>
                                            <div class="col-sm-5">
                                                <p class="text-muted mb-1">Participación Loteka</p>
                                                <h5 class="text-success mb-0">RD$ {{ number_format($resumenEmpresa['monto_loteka'], 2) }}</h5>
                                            </div>
                                            <div class="col-sm-3">
                                                <p class="text-muted mb-1">Terminales</p>
                                                <h5 class="mb-0">{{ number_format($resumenEmpresa['terminales']) }}</h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="card">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <h5 class="card-title mb-1">Detalle de ingresos</h5>
                                <p class="text-muted mb-0">Del {{ \Illuminate\Support\Carbon::parse($fechaInicio)->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($fechaFin)->format('d/m/Y') }}</p>
                            </div>
                        </div>
                        <div class="card-body">
                            @if ($registros->isEmpty())
                                <div class="alert alert-warning mb-0">No se encontraron ventas no tradicionales en el período seleccionado.</div>
                            @else
                                @foreach ($registrosPorEmpresa as $empresaId => $registrosEmpresa)
                                    <div class="border rounded p-3 {{ $loop->last ? '' : 'mb-4' }}">
                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                            <div>
                                                <h5 class="mb-1">{{ $registrosEmpresa->first()['empresa'] }}</h5>
                                                <span class="badge bg-primary-subtle text-primary">Empresa {{ $empresaId }}</span>
                                                <span class="text-muted ms-2">{{ number_format($registrosEmpresa->count()) }} terminales</span>
                                            </div>
                                            <button type="button" class="btn btn-success descargar-ingresos-excel"
                                                data-table="tablaIngresosLoteka{{ $loop->index }}">
                                                <i class="ri-file-excel-2-line me-1"></i> Descargar Excel
                                            </button>
                                        </div>
                                        <div class="table-responsive">
                                            <table id="tablaIngresosLoteka{{ $loop->index }}"
                                                class="table table-bordered table-striped align-middle w-100 tabla-ingresos-loteka"
                                                data-empresa="{{ $registrosEmpresa->first()['empresa'] }}">
                                                <thead class="table-light">
                                                    <tr><th>Terminales</th><th>Centro de costo</th><th class="text-end">Monto</th><th class="text-end">Participación</th><th class="text-end">Participación Loteka</th></tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($registrosEmpresa as $registro)
                                                        <tr>
                                                            <td class="fw-semibold">{{ $registro['terminal'] }}</td>
                                                            <td>
                                                                <span class="{{ $registro['centro_costo_encontrado'] ? 'fw-semibold' : 'text-warning' }}">
                                                                    {{ $registro['centro_costo'] }}
                                                                </span>
                                                                @if ($registro['centro_costo_descripcion'] !== '')
                                                                    <small class="d-block text-muted">{{ $registro['centro_costo_descripcion'] }}</small>
                                                                @endif
                                                            </td>
                                                            <td class="text-end" data-order="{{ $registro['monto'] }}">RD$ {{ number_format($registro['monto'], 2) }}</td>
                                                            <td class="text-end" data-order="{{ $registro['participacion'] }}">{{ number_format($registro['participacion'], 2) }}%</td>
                                                            <td class="text-end fw-semibold" data-order="{{ $registro['monto_distribuido'] }}">RD$ {{ number_format($registro['monto_distribuido'], 2) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot class="table-primary fw-semibold">
                                                    <tr>
                                                        <td colspan="2">Subtotal {{ $registrosEmpresa->first()['empresa'] }}</td>
                                                        <td class="text-end">RD$ {{ number_format($registrosEmpresa->sum('monto'), 2) }}</td>
                                                        <td class="text-end">{{ number_format($totalMonto > 0 ? ($registrosEmpresa->sum('monto') / $totalMonto) * 100 : 0, 2) }}%</td>
                                                        <td class="text-end">RD$ {{ number_format($registrosEmpresa->sum('monto_distribuido'), 2) }}</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
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
            const form = document.getElementById('formIngresosLoteka');
            const button = document.getElementById('generarIngresosLoteka');
            const montoLoteka = document.getElementById('monto_loteka');

            const formatMontoLoteka = function () {
                if (!montoLoteka) {
                    return;
                }

                let rawValue = montoLoteka.value.replace(/,/g, '').replace(/[^\d.]/g, '');
                const decimalPosition = rawValue.indexOf('.');

                if (decimalPosition >= 0) {
                    rawValue = rawValue.slice(0, decimalPosition + 1)
                        + rawValue.slice(decimalPosition + 1).replace(/\./g, '');
                }

                const parts = rawValue.split('.');
                const integerPart = parts[0].replace(/^0+(?=\d)/, '');
                const formattedInteger = integerPart === ''
                    ? ''
                    : Number(integerPart).toLocaleString('en-US');

                montoLoteka.value = parts.length > 1
                    ? `${formattedInteger || '0'}.${parts[1].slice(0, 2)}`
                    : formattedInteger;
            };

            montoLoteka?.addEventListener('input', formatMontoLoteka);
            montoLoteka?.addEventListener('blur', function () {
                const numericValue = Number(montoLoteka.value.replace(/,/g, ''));

                if (montoLoteka.value !== '' && Number.isFinite(numericValue)) {
                    montoLoteka.value = numericValue.toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            });

            form?.addEventListener('submit', function () {
                if (button) {
                    button.disabled = true;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Generando...';
                }

                Swal.fire({
                    title: 'Generando datos',
                    icon: 'info',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });
            });
        });
    </script>

    @if ($consultado && $registros->isNotEmpty())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const tables = {};

                $('.tabla-ingresos-loteka').each(function () {
                    const element = this;
                    tables[element.id] = $(element).DataTable({
                        pageLength: 25,
                        order: [[0, 'asc']],
                        language: { url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json' },
                        dom: 'Bfrtip',
                        buttons: [{
                            extend: 'excelHtml5',
                            title: `Ingresos Loteka ${element.dataset.empresa} {{ $fechaInicio }} a {{ $fechaFin }}`,
                            footer: true,
                            exportOptions: { columns: [0, 1, 2, 3, 4] }
                        }]
                    });
                });

                document.querySelectorAll('.descargar-ingresos-excel').forEach(function (button) {
                    button.addEventListener('click', function () {
                        tables[button.dataset.table]?.button('.buttons-excel').trigger();
                    });
                });
            });
        </script>
    @endif
@endsection
