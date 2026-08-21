<?php

namespace App\Http\Controllers;

use App\Services\FavoritoCatalogoService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecursosHumanosController extends Controller
{
    public function index(Request $request, FavoritoCatalogoService $favoritoCatalogo): View
    {
        $favoritos = $request->user()
            ? $favoritoCatalogo->favoritos($request->user())->pluck('key')->flip()
            : collect();

        $modulos = collect(config('recursos_humanos', []))
            ->filter(fn ($modulo) => (bool) ($modulo['activo'] ?? true))
            ->map(function ($modulo) use ($favoritos) {
                $path = ltrim((string) parse_url((string) $modulo['url'], PHP_URL_PATH), '/');
                $modulo['url'] = url($modulo['url']);
                $modulo['tags'] = $modulo['tags'] ?? [];
                $modulo['favorito_key'] = $modulo['key'] ?? 'recursos-humanos:'.$path;
                $modulo['es_favorito'] = $favoritos->has($modulo['favorito_key']);

                return $modulo;
            })
            ->sortBy('nombre')
            ->values();

        $categorias = $modulos
            ->pluck('categoria')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return view('recursos_humanos.index', compact('modulos', 'categorias'));
    }
}
