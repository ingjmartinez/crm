<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperacionDepositoRuta extends Model
{
    protected $table = 'operaciones_deposito_rutas';

    protected $fillable = [
        'account',
        'whatsapp_phone',
        'banco',
        'ruta_nombre',
        'monto_depositado',
        'monto_ocr',
        'ocr_estado',
        'ocr_confianza',
        'ocr_observacion',
        'ocr_texto',
        'ocr_procesado_at',
        'comprobante_url',
        'comprobante_message_id',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'monto_depositado' => 'decimal:2',
            'monto_ocr' => 'decimal:2',
            'ocr_procesado_at' => 'datetime',
        ];
    }
}
