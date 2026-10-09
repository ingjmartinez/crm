<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class BancoOperacion extends Model
{
    use HasFactory;

    protected $table = 'bancos_operaciones';

    protected $fillable = [
        'nombre',
        'empresa_id',
        'cuenta_codigo',
        'cuenta_descripcion',
        'cuenta_contable_id',
    ];

    public function cuentaContable(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class);
    }

    /** @return Collection<int, string> */
    public static function nombresDisponibles(): Collection
    {
        return self::query()
            ->orderBy('nombre')
            ->pluck('nombre')
            ->map(fn (mixed $nombre): string => trim((string) $nombre))
            ->filter()
            ->unique(fn (string $nombre): string => mb_strtolower($nombre))
            ->values();
    }
}
