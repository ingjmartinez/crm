<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FavoritoCatalogoService;

class ModuleHubController extends Controller
{
    public function __construct(private readonly FavoritoCatalogoService $favoritoCatalogo) {}

    public function dashboard()
    {
        return $this->show('dashboard');
    }

    public function comercial()
    {
        return $this->show('comercial');
    }

    public function contabilidad()
    {
        return $this->show('contabilidad');
    }

    public function operaciones()
    {
        return $this->show('operaciones');
    }

    public function legal()
    {
        return $this->show('legal');
    }

    public function mantenimiento()
    {
        return $this->show('mantenimiento');
    }

    public function tecnologia()
    {
        return $this->show('tecnologia');
    }

    public function incentivos()
    {
        return $this->show('incentivos');
    }

    public function extra(string $module)
    {
        abort_unless(array_key_exists($module, config('view_access.extra_modules', [])), 404);

        return $this->show($module);
    }

    public function procesos()
    {
        return $this->show('procesos');
    }

    public function gerencia()
    {
        return $this->show('gerencia');
    }

    public function serviciosGenerales()
    {
        return $this->show('servicios_generales');
    }

    public function show(string $module)
    {
        $hub = app(\App\Services\AccesoVistaService::class)->modules()[$module] ?? null;

        abort_unless(is_array($hub), 404);

        /** @var User|null $user */
        $user = auth()->user();
        $favoritos = $user ? $this->favoritoCatalogo->favoritos($user)->pluck('key')->flip() : collect();

        $items = collect($hub['items'] ?? [])
            ->filter(function ($item) use ($user) {
                if (! app(\App\Services\AccesoVistaService::class)->canView($user, $item['url'])) {
                    return false;
                }
                if (! (bool) ($item['activo'] ?? true)) {
                    return false;
                }

                if (! empty($item['permission']) && (! $user || ! $user->can($item['permission']))) {
                    return false;
                }

                if (! empty($item['role']) && (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole($item['role']))) {
                    return false;
                }

                return true;
            })
            ->map(function ($item) use ($module, $favoritos) {
                $path = ltrim((string) parse_url((string) $item['url'], PHP_URL_PATH), '/');
                $item['url'] = url($item['url']);
                $item['tags'] = $item['tags'] ?? [];
                $item['favorito_key'] = $item['key'] ?? $module.':'.$path;
                $item['es_favorito'] = $favoritos->has($item['favorito_key']);

                return $item;
            })
            ->sortBy('nombre')
            ->values();

        $categorias = $items
            ->pluck('categoria')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $extra = [];

        if ($module === 'operaciones') {
            $extra['depositosRutaDataUrl'] = route('operaciones.deposito-ruta.data');
        }

        return view('module-hub.index', array_merge([
            'module' => $module,
            'titulo' => $hub['titulo'] ?? ucfirst($module),
            'breadcrumb' => $hub['breadcrumb'] ?? ($hub['titulo'] ?? ucfirst($module)),
            'items' => $items,
            'categorias' => $categorias,
        ], $extra));
    }
}
