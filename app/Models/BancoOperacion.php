<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class BancoOperacion extends Model
{
    use HasFactory;

    protected $table = 'bancos_operaciones';

    protected $fillable = [
        'nombre',
    ];

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
