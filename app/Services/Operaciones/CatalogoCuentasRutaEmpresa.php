<?php

namespace App\Services\Operaciones;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CatalogoCuentasRutaEmpresa
{
    /** @return array<int, array{cuenta: string, descripcion: string}> */
    public function listar(string $empresaId): array
    {
        if (! in_array($empresaId, ['168', '169'], true)) {
            return [];
        }

        return Cache::remember('cuentas_ruta_empresa:'.$empresaId, now()->addHours(6), function () use ($empresaId): array {
            $response = Http::withOptions(['verify' => false])->timeout(30)->post(
                'https://apisj.azurewebsites.net/ApiSJ/CatalagoCta/Listar?'.http_build_query([
                    'strToken' => config('services.apisj.token'),
                    'intIdEmpresa' => $empresaId,
                    'strFiltros' => '[["AceptaMov", "1"]]',
                ]),
            );

            if (! $response->successful()) {
                throw new RuntimeException('No se pudo consultar el catalogo contable de la empresa.');
            }

            $payload = $response->json();
            $items = $payload['Result']['Det'] ?? $payload['RESULT']['DET'] ?? $payload['result']['det'] ?? $payload;

            if (! is_array($items)) {
                throw new RuntimeException('El catalogo contable devolvio un formato invalido.');
            }

            return collect($items)
                ->filter(fn (mixed $item): bool => is_array($item))
                ->map(function (array $item): array {
                    $item = array_change_key_case($item, CASE_UPPER);

                    return [
                        'cuenta' => trim((string) ($item['CUENTA'] ?? '')),
                        'descripcion' => trim((string) ($item['DESCRIPCION'] ?? '')),
                    ];
                })
                ->filter(fn (array $item): bool => str_starts_with($item['cuenta'], '10013'))
                ->sortBy('cuenta')
                ->values()
                ->all();
        });
    }

    /** @return array{cuenta: string, descripcion: string}|null */
    public function resolver(string $empresaId, string $nombreGrupo): ?array
    {
        $tokensGrupo = $this->tokens($nombreGrupo);
        if ($tokensGrupo === []) {
            return null;
        }

        $coincidencias = collect($this->listar($empresaId))
            ->map(function (array $cuenta) use ($tokensGrupo): array {
                $tokensCuenta = $this->tokens($cuenta['descripcion']);
                $exacta = $tokensCuenta !== [] && $tokensCuenta === $tokensGrupo;
                $contenida = count($tokensCuenta) >= 2 && count(array_diff($tokensCuenta, $tokensGrupo)) === 0;

                return ['cuenta' => $cuenta, 'puntaje' => $exacta ? 100 + count($tokensCuenta) : ($contenida ? count($tokensCuenta) : 0)];
            })
            ->filter(fn (array $coincidencia): bool => $coincidencia['puntaje'] > 0);

        $mayorPuntaje = $coincidencias->max('puntaje');
        $mejores = $coincidencias->where('puntaje', $mayorPuntaje);

        return $mejores->count() === 1 ? $mejores->first()['cuenta'] : null;
    }

    /** @return array<int, string> */
    private function tokens(string $nombre): array
    {
        $nombre = preg_replace('/^\s*\d+\s*-\s*/u', '', $nombre) ?? $nombre;
        preg_match_all('/[a-z]+|\d+/', Str::ascii(mb_strtolower($nombre)), $coincidencias);

        $tokens = collect($coincidencias[0])
            ->reject(fn (string $token): bool => in_array($token, ['ruta', 'grupo', 'gj', 'ng', 'de', 'del', 'la', 'el'], true))
            ->map(fn (string $token): string => ctype_digit($token) ? (string) ((int) $token) : $token)
            ->unique()->values()->all();
        sort($tokens);

        return $tokens;
    }
}
