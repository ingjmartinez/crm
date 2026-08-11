<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalPagoProgramado extends Model
{
    use HasFactory;

    protected $table = 'legal_pagos_programados';

    protected $fillable = [
        'legal_obligacion_id', 'periodo', 'fecha_vencimiento', 'monto', 'estado',
        'fecha_pago', 'referencia_pago', 'comprobante_path', 'pagado_por',
    ];

    protected function casts(): array
    {
        return [
            'periodo' => 'date',
            'fecha_vencimiento' => 'date',
            'monto' => 'decimal:2',
            'fecha_pago' => 'date',
        ];
    }

    public function obligacion(): BelongsTo
    {
        return $this->belongsTo(LegalObligacion::class, 'legal_obligacion_id');
    }

    public function pagadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pagado_por');
    }
}
