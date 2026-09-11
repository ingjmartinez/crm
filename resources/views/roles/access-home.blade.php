@extends('app')

@section('content')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <h4>Bienvenido, {{ auth()->user()->name }}</h4>
                <p>Selecciona uno de tus módulos disponibles.</p>
                <div class="row">
                    @forelse(app(\App\Services\AccesoVistaService::class)->modules() as $key => $module)
                        @if(app(\App\Services\AccesoVistaService::class)->canModule(auth()->user(), $key))
                            <div class="col-md-4 mb-3">
                                <a href="{{ url($module['url']) }}" class="card card-body">{{ $module['titulo'] }}</a>
                            </div>
                        @endif
                    @empty
                        <p>No hay módulos disponibles.</p>
                    @endforelse
                </div>
                <p class="text-muted">Si necesitas otro acceso, solicita al administrador que revise tus roles.</p>
            </div>
        </div>
    </div>
@endsection
