<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class BiVentaSnapshot extends Model
{
    protected $fillable = ['fecha', 'hora', 'capturado_en', 'rutas', 'productos_terminales'];

    protected function fecha(): Attribute
    {
        return Attribute::make(
            set: fn (string|\DateTimeInterface $fecha): string => Carbon::parse($fecha)->toDateString(),
        );
    }

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'hora' => 'integer',
            'capturado_en' => 'datetime',
            'rutas' => 'array',
            'productos_terminales' => 'array',
        ];
    }
}
