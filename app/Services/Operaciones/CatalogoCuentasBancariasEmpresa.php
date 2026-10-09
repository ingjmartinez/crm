<?php

namespace App\Services\Operaciones;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CatalogoCuentasBancariasEmpresa
{
    /** @return array<int, array{cuenta: string, descripcion: string}> */
    public function listar(string $empresaId): array
    {
        if (! in_array($empresaId, ['168', '169'], true)) {
            return [];
        }

        return Cache::remember('cuentas_bancarias_empresa:'.$empresaId, now()->addHours(6), function () use ($empresaId): array {
            $response = Http::withOptions(['verify' => false])->timeout(30)->post(
                'https://apisj.azurewebsites.net/ApiSJ/CatalagoCta/Listar?'.http_build_query([
                    'strToken' => config('services.apisj.token'),
                    'intIdEmpresa' => $empresaId,
                    'strFiltros' => '[["AceptaMov", "1"]]',
                ]),
            );

            if (! $response->successful()) {
                throw new RuntimeException('No se pudo consultar el catálogo contable de la empresa.');
            }

            $payload = $response->json();
            $items = $payload['Result']['Det'] ?? $payload['RESULT']['DET'] ?? $payload['result']['det'] ?? $payload;

            if (! is_array($items)) {
                throw new RuntimeException('El catálogo contable devolvió un formato inválido.');
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
                ->filter(fn (array $item): bool => str_starts_with($item['cuenta'], '10021'))
                ->sortBy('cuenta')
                ->values()
                ->all();
        });
    }

    /** @return array{cuenta: string, descripcion: string}|null */
    public function buscar(string $empresaId, string $cuenta): ?array
    {
        return collect($this->listar($empresaId))->firstWhere('cuenta', $cuenta);
    }
}
