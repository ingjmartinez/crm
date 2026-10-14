<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoRutaV2DepositoCentral extends Model
{
    protected $fillable = ['fecha', 'ruta_key', 'ruta', 'id_trans', 'monto', 'recibo_path', 'user_id'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'monto' => 'decimal:2'];
    }
}
