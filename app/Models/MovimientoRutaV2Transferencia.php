<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoRutaV2Transferencia extends Model
{
    protected $fillable = [
        'fecha', 'ruta_origen_key', 'ruta_origen', 'ruta_destino_key', 'ruta_destino',
        'monto', 'recibo_path', 'user_id',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'monto' => 'decimal:2'];
    }
}
