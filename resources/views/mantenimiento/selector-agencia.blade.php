@extends('app')

@section('content')
    <style>
        #tablaSupervisionSelectorAgencia th, #tablaSupervisionSelectorAgencia td { white-space: nowrap; }
        #tablaSupervisionSelectorAgencia .venta-baja { color: #b32636; }
        #tablaSupervisionSelectorAgencia .venta-alta { color: #0d6efd; }
        #tablaSupervisionSelectorAgencia .venta-millon { color: #167047; }
        #tablaSupervisionSelectorAgencia strong { font-weight: 800; }
    </style>
    <div class="main-content"><div class="page-content"><div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div><h4 class="mb-1">Selector de agencia</h4><p class="text-muted mb-0">Lista de supervisión con ventas y premios reales tradicionales.</p></div>
            <a href="{{ route('mantenimiento.index') }}" class="btn btn-outline-secondary">Volver a Mantenimiento</a>
        </div>
        <div class="card"><div class="card-body">
            <form method="GET" id="formSelectorAgencia" action="{{ route('mantenimiento.selector-agencia') }}" class="row g-3 align-items-end">
                <div class="col-md-2"><label for="anioSelectorAgencia" class="form-label">Año</label><input type="number" id="anioSelectorAgencia" name="anio" min="1900" max="9998" value="{{ $filtros['anio'] }}" class="form-control" required></div>
                @foreach (['mes_inicio' => 'Mes inicial', 'mes_fin' => 'Mes final'] as $campo => $etiqueta)
                    <div class="col-md-2"><label for="{{ $campo }}SelectorAgencia" class="form-label">{{ $etiqueta }}</label><select id="{{ $campo }}SelectorAgencia" name="{{ $campo }}" class="form-select" required>
                        @foreach (['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'] as $indice => $nombre)
                            <option value="{{ $indice + 1 }}" @selected((int) $filtros[$campo] === $indice + 1)>{{ $nombre }}</option>
                        @endforeach
                    </select></div>
                @endforeach
                <div class="col-md-3"><label for="empresaSelectorAgencia" class="form-label">Empresa</label><select id="empresaSelectorAgencia" name="empresa" class="form-select"><option value="">Todas las empresas</option>@foreach ($empresas as $empresa)<option value="{{ $empresa }}" @selected($filtros['empresa'] === $empresa)>{{ $empresa }}</option>@endforeach</select></div>
                <div class="col-md-3"><label for="rutaSelectorAgencia" class="form-label">Ruta</label><select id="rutaSelectorAgencia" name="ruta" class="form-select"><option value="">Todas las rutas</option>@foreach ($rutas as $ruta)<option value="{{ $ruta }}" @selected($filtros['ruta'] === $ruta)>{{ $ruta }}</option>@endforeach</select></div>
                <div class="col-auto"><button type="submit" class="btn btn-primary">Consultar</button></div>
            </form>
            @if ($errors->any())<div class="alert alert-danger mt-3" role="alert">{{ $errors->first() }}</div>@endif
        </div></div>
        <div class="card"><div class="card-body">
            @if ($resultado === null)
                <div class="alert alert-info mb-0">Elige el período y pulsa Consultar para seleccionar agencias.</div>
            @elseif ($datos === [])
                <div class="alert alert-info mb-0">No se encontraron terminales con ventas tradicionales en los meses seleccionados.</div>
            @else
                <p class="text-muted small">Período: {{ $resultado['desde']->format('Y-m') }} a {{ $resultado['hasta']->format('Y-m') }} · {{ count($datos) }} terminales disponibles.</p>
                <section id="supervisionSelectorAgencia" class="border rounded p-3 mb-4" aria-label="Lista de supervisión">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div><h5 class="mb-1">Lista de supervisión</h5><p class="small text-muted mb-0">La selección se guarda automáticamente en el sistema para tu usuario. El Excel usa los datos del período consultado.</p></div>
                        <button type="button" id="vaciarSupervisionSelectorAgencia" class="btn btn-outline-secondary btn-sm">Vaciar lista</button>
                    </div>
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-2"><label for="tipoBusquedaSupervisionSelectorAgencia" class="form-label">Buscar por</label><select id="tipoBusquedaSupervisionSelectorAgencia" class="form-select"><option value="ambos">Nombre o terminal</option><option value="nombre">Nombre</option><option value="terminal">Terminal</option></select></div>
                        <div class="col-md-5"><label for="buscarSupervisionSelectorAgencia" class="form-label">Nombre o número de terminal</label><input type="search" id="buscarSupervisionSelectorAgencia" class="form-control" placeholder="Ej.: Yulissa o 05378" autocomplete="off"></div>
                        <div class="col-md-2"><label for="cantidadSupervisionSelectorAgencia" class="form-label">Cantidad a agregar</label><input type="number" id="cantidadSupervisionSelectorAgencia" min="1" step="1" value="10" class="form-control"></div>
                        <div class="col-md-3"><button type="button" id="agregarSupervisionSelectorAgencia" class="btn btn-primary w-100">Agregar primeras coincidencias</button></div>
                    </div>
                    <div id="resultadosSupervisionSelectorAgencia" class="border rounded p-2 mb-2" style="max-height:220px;overflow-y:auto" aria-label="Terminales disponibles"></div>
                    <p id="coincidenciasSupervisionSelectorAgencia" class="small text-muted mb-2"></p>
                    <p id="estadoSupervisionSelectorAgencia" class="small mb-2" role="status" aria-live="polite"></p>
                    <button type="button" id="reintentarGuardadoSelectorAgencia" class="btn btn-sm btn-outline-danger mb-2" hidden>Reintentar guardado</button>
                    <div id="ausentesSupervisionSelectorAgencia" class="small mb-3"></div>
                    <div class="table-responsive"><table id="tablaSupervisionSelectorAgencia" class="table table-bordered table-striped align-middle w-100"></table></div>
                </section>
            @endif
        </div></div>
    </div></div></div>
@endsection

@section('script')
    <script>
        window.selectorAgencia = {
            datos: {{ Illuminate\Support\Js::from($datos) }},
            meses: {{ Illuminate\Support\Js::from($meses) }},
            rutasDisponibles: {{ Illuminate\Support\Js::from($rutasDisponibles) }},
            claveSupervision: {{ Illuminate\Support\Js::from('rentabilidad-v11:supervision:'.auth()->id()) }},
            seleccionGuardada: {{ Illuminate\Support\Js::from($seleccionGuardada) }},
            urlGuardar: {{ Illuminate\Support\Js::from(route('mantenimiento.selector-agencia.seleccion')) }},
            csrfToken: {{ Illuminate\Support\Js::from(csrf_token()) }},
        };
    </script>
    <script src="{{ asset('js/pages/selector-agencia.js') }}?v={{ hash_file('sha256', public_path('js/pages/selector-agencia.js')) }}"></script>
@endsection
