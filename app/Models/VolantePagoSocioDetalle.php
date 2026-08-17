<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolantePagoSocioDetalle extends Model
{
    /** @use HasFactory<\Database\Factories\VolantePagoSocioDetalleFactory> */
    use HasFactory;

    protected $fillable = [
        'carga_id', 'numero_linea', 'nombre', 'tipo_identificacion', 'identificacion',
        'cuenta', 'tipo_cuenta', 'monto', 'estado',
    ];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2'];
    }

    public function carga(): BelongsTo
    {
        return $this->belongsTo(VolantePagoSocioCarga::class, 'carga_id');
    }
}
