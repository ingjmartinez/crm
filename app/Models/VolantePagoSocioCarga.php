<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VolantePagoSocioCarga extends Model
{
    /** @use HasFactory<\Database\Factories\VolantePagoSocioCargaFactory> */
    use HasFactory;

    protected $fillable = [
        'nombre_archivo', 'hash_archivo', 'empresa_origen', 'rnc_origen', 'cuenta_origen',
        'tipo_transaccion', 'estado', 'monto_total', 'fecha_transaccion',
        'cantidad_transacciones', 'usuario_id',
    ];

    protected function casts(): array
    {
        return ['monto_total' => 'decimal:2', 'fecha_transaccion' => 'datetime'];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(VolantePagoSocioDetalle::class, 'carga_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
