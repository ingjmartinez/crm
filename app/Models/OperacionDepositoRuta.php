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
        'comprobante_url',
        'comprobante_message_id',
        'estado',
    ];
}
