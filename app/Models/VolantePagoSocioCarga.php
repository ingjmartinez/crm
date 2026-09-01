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

    public const BANCO_SANTA_CRUZ = 'santa_cruz';

    public const BANCO_BANRESERVAS = 'banreservas';

    public const BANCOS = [
        self::BANCO_SANTA_CRUZ => 'Banco Santa Cruz',
        self::BANCO_BANRESERVAS => 'Banreservas',
    ];

    protected $fillable = [
        'nombre_archivo', 'hash_archivo', 'banco', 'empresa_origen', 'rnc_origen', 'cuenta_origen',
        'tipo_transaccion', 'estado', 'monto_total', 'fecha_transaccion',
        'fecha_correspondiente', 'cantidad_transacciones', 'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'monto_total' => 'decimal:2',
            'fecha_transaccion' => 'datetime',
            'fecha_correspondiente' => 'date',
        ];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(VolantePagoSocioDetalle::class, 'carga_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function nombreBanco(): string
    {
        return self::BANCOS[$this->banco] ?? self::BANCOS[self::BANCO_SANTA_CRUZ];
    }
}
