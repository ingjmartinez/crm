<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ClasificacionCuenta extends Model
{
    /** @use HasFactory<\Database\Factories\ClasificacionCuentaFactory> */
    use HasFactory;

    protected $fillable = ['cuenta', 'clasificacion', 'criterio', 'observacion', 'activo'];

    /**
     * @return Collection<int, string>
     */
    public static function cuentasActivas(): Collection
    {
        return self::clasificacionesActivas()->keys()
            ->map(fn (string|int $cuenta): string => (string) $cuenta)
            ->values();
    }

    /**
     * @return Collection<string, string>
     */
    public static function clasificacionesActivas(): Collection
    {
        $configuradas = config('clasificacion_cuentas.cuentas', []);
        $guardadas = self::query()->get()->keyBy('cuenta');

        return collect(array_unique(array_merge(array_keys($configuradas), $guardadas->keys()->all())))
            ->filter(fn (string|int $cuenta): bool => $guardadas->get((string) $cuenta)?->activo ?? isset($configuradas[$cuenta]))
            ->mapWithKeys(fn (string|int $cuenta): array => [
                (string) $cuenta => (string) ($guardadas->get((string) $cuenta)?->clasificacion ?? $configuradas[$cuenta] ?? ''),
            ]);
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
