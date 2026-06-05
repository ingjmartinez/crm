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
        'comprobante_url',
        'comprobante_message_id',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'monto_depositado' => 'decimal:2',
        ];
    }
}
