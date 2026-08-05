<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoRutaV2Gasto extends Model
{
    protected $table = 'movimientos_rutas_v2_gastos';

    protected $fillable = [
        'fecha', 'ruta_key', 'ruta', 'monto', 'concepto', 'comprobante_path',
        'observacion', 'estado', 'user_id',
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
