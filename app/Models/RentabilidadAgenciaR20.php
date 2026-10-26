<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentabilidadAgenciaR20 extends Model
{
    protected $table = 'rentabilidad_agencia_r20';

    protected $fillable = ['terminal', 'mes', 'monto', 'usuario_id'];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2'];
    }
}
