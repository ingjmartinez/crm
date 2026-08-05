<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoRutaV2Deposito extends Model
{
    protected $table = 'movimientos_rutas_v2_depositos';

    protected $fillable = [
        'fecha', 'ruta_key', 'ruta', 'monto', 'banco', 'referencia',
        'comprobante_path', 'observacion', 'estado', 'user_id',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'monto' => 'decimal:2'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
