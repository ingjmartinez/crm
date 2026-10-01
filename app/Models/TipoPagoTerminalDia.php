<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoPagoTerminalDia extends Model
{
    /** @use HasFactory<\Database\Factories\TipoPagoTerminalDiaFactory> */
    use HasFactory;

    protected $fillable = [
        'fecha',
        'terminal',
        'tipo_pago',
        'tipo_pago_original',
        'archivo_origen',
        'cargado_por_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'tipo_pago' => 'integer',
        ];
    }
}
