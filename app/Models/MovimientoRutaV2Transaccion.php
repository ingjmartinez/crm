<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoRutaV2Transaccion extends Model
{
    protected $table = 'movimientos_rutas_v2_transacciones';

    protected $fillable = [
        'importacion_id', 'fecha', 'ruta_key', 'ruta', 'id_trans', 'terminal',
        'nombre_agencia', 'tipo', 'tipo_etiqueta', 'monto', 'monto_original',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'monto' => 'decimal:2', 'monto_original' => 'decimal:2'];
    }

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(MovimientoRutaV2Importacion::class, 'importacion_id');
    }
}
