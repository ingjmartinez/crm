<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserFavorito;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FavoritoCatalogoService
{
    /** @return Collection<int, array<string, mixed>> */
    public function catalogo(?User $usuario): Collection
    {
        $modulos = collect(config('module_hubs', []))
            ->flatMap(function (mixed $hub, string $modulo) use ($usuario): Collection {
                if (! is_array($hub)) {
                    return collect();
                }

                return collect($hub['items'] ?? [])
                    ->filter(fn (array $item): bool => $this->puedeAcceder($item, $usuario))
                    ->map(function (array $item) use ($modulo, $hub): array {
                        $path = '/'.ltrim((string) parse_url((string) $item['url'], PHP_URL_PATH), '/');

                        return [
                            'key' => $item['key'] ?? $modulo.':'.ltrim($path, '/'),
                            'nombre' => $item['nombre'],
                            'descripcion' => $item['descripcion'] ?? '',
                            'url' => url($path),
                            'path' => $path,
                            'icono' => $item['icono'] ?? 'ri-file-list-3-line',
                            'categoria' => $item['categoria'] ?? 'General',
                            'modulo' => $hub['titulo'] ?? ucfirst($modulo),
                        ];
                    });
            });

        $reportes = collect(config('reportes', []))
            ->filter(fn (mixed $item): bool => is_array($item) && (bool) ($item['activo'] ?? true))
            ->map(function (array $item): array {
                $path = '/'.ltrim((string) parse_url((string) $item['url'], PHP_URL_PATH), '/');

                return [
                    'key' => $item['key'] ?? 'reportes:'.ltrim($path, '/'),
                    'nombre' => $item['nombre'],
                    'descripcion' => $item['descripcion'] ?? '',
                    'url' => url($path),
                    'path' => $path,
                    'icono' => $item['icono'] ?? 'ri-file-list-3-line',
                    'categoria' => $item['categoria'] ?? 'General',
                    'modulo' => 'Reportes',
                ];
            });

        $recursosHumanos = $this->puedeAccederRecursosHumanos($usuario)
            ? collect(config('recursos_humanos', []))
                ->filter(fn (mixed $item): bool => is_array($item) && (bool) ($item['activo'] ?? true))
                ->map(function (array $item): array {
                    $path = '/'.ltrim((string) parse_url((string) $item['url'], PHP_URL_PATH), '/');

                    return [
                        'key' => $item['key'] ?? 'recursos-humanos:'.ltrim($path, '/'),
                        'nombre' => $item['nombre'],
                        'descripcion' => $item['descripcion'] ?? '',
                        'url' => url($path),
                        'path' => $path,
                        'icono' => $item['icono'] ?? 'ri-user-settings-line',
                        'categoria' => $item['categoria'] ?? 'General',
                        'modulo' => 'Recursos Humanos',
                    ];
                })
            : collect();

        return $modulos
            ->concat($reportes)
            ->concat($recursosHumanos)
            ->unique('key')
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function favoritos(User $usuario): Collection
    {
        try {
            $keys = UserFavorito::query()
                ->where('user_id', $usuario->getAuthIdentifier())
                ->orderBy('orden')
                ->orderBy('id')
                ->pluck('favorito_key');
        } catch (QueryException) {
            return collect();
        }

        $catalogo = $this->catalogo($usuario)->keyBy('key');

        return $keys
            ->map(fn (string $key): ?array => $catalogo->get($key))
            ->filter()
            ->values();
    }

    /** @return array<string, mixed>|null */
    public function actual(?User $usuario, Request $request): ?array
    {
        $pathActual = '/'.trim($request->path(), '/');

        return $this->catalogo($usuario)
            ->first(fn (array $item): bool => rtrim($item['path'], '/') === rtrim($pathActual, '/'));
    }

    /** @return array{activo: bool, favorito: array<string, mixed>, favoritos: Collection<int, array<string, mixed>>} */
    public function alternar(User $usuario, string $key): array
    {
        $item = $this->catalogo($usuario)->firstWhere('key', $key);

        abort_if($item === null, 404, 'La página no existe o no está disponible para este usuario.');

        $favorito = UserFavorito::query()
            ->where('user_id', $usuario->getAuthIdentifier())
            ->where('favorito_key', $key)
            ->first();

        if ($favorito) {
            $favorito->delete();
            $activo = false;
        } else {
            abort_if(
                UserFavorito::query()->where('user_id', $usuario->getAuthIdentifier())->count() >= 12,
                422,
                'Puedes guardar un máximo de 12 favoritos.'
            );

            UserFavorito::query()->create([
                'user_id' => $usuario->getAuthIdentifier(),
                'favorito_key' => $key,
                'orden' => ((int) UserFavorito::query()
                    ->where('user_id', $usuario->getAuthIdentifier())
                    ->max('orden')) + 1,
            ]);
            $activo = true;
        }

        return ['activo' => $activo, 'favorito' => $item, 'favoritos' => $this->favoritos($usuario)];
    }

    private function puedeAcceder(array $item, ?User $usuario): bool
    {
        if (! (bool) ($item['activo'] ?? true)) {
            return false;
        }

        try {
            if (! empty($item['permission']) && (! $usuario || ! $usuario->can($item['permission']))) {
                return false;
            }

            if (! empty($item['role']) && (! $usuario || ! $usuario->hasRole($item['role']))) {
                return false;
            }
        } catch (QueryException) {
            return false;
        }

        return true;
    }

    private function puedeAccederRecursosHumanos(?User $usuario): bool
    {
        if (! $usuario) {
            return false;
        }

        try {
            return $usuario->hasAnyRole(['superadmin', 'admin', 'rh'])
                || $usuario->can('recursos_humanos.view');
        } catch (QueryException) {
            return false;
        }
    }
}
