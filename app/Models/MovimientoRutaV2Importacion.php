<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MovimientoRutaV2Importacion extends Model
{
    protected $table = 'movimientos_rutas_v2_importaciones';

    protected $fillable = [
        'nombre_archivo', 'fecha_desde', 'fecha_hasta', 'fechas_reemplazadas',
        'filas_aceptadas', 'filas_descartadas', 'user_id',
    ];

    protected function casts(): array
    {
        return ['fecha_desde' => 'date', 'fecha_hasta' => 'date'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function transacciones(): HasMany
    {
        return $this->hasMany(MovimientoRutaV2Transaccion::class, 'importacion_id');
    }
}
