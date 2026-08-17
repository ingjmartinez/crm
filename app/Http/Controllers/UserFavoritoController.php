<?php

namespace App\Http\Controllers;

use App\Http\Requests\AlternarFavoritoRequest;
use App\Models\User;
use App\Services\FavoritoCatalogoService;
use Illuminate\Http\JsonResponse;

class UserFavoritoController extends Controller
{
    public function __construct(private readonly FavoritoCatalogoService $catalogoService) {}

    public function toggle(AlternarFavoritoRequest $request): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $resultado = $this->catalogoService->alternar($usuario, $request->validated('favorito_key'));

        return response()->json([
            'message' => $resultado['activo'] ? 'Reporte agregado a favoritos.' : 'Reporte eliminado de favoritos.',
            ...$resultado,
        ]);
    }
}
