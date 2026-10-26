<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClasificacionCuenta extends Model
{
    /** @use HasFactory<\Database\Factories\ClasificacionCuentaFactory> */
    use HasFactory;

    protected $fillable = ['cuenta', 'clasificacion', 'criterio', 'observacion', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
