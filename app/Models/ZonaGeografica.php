<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZonaGeografica extends Model
{
    protected $table = 'zonas_geograficas';

    protected $fillable = [
        'region',
        'provincia',
        'municipio',
        'ciudad_seccion',
        'sector_barrio_paraje',
    ];

    protected static function booted(): void
    {
        static::saving(function (ZonaGeografica $zonaGeografica): void {
            $zonaGeografica->jerarquia_hash = hash('sha256', implode("\x1F", [
                $zonaGeografica->region,
                $zonaGeografica->provincia,
                $zonaGeografica->municipio,
                $zonaGeografica->ciudad_seccion,
                $zonaGeografica->sector_barrio_paraje,
            ]));
        });
    }
}
