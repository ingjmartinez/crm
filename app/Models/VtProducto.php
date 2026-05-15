<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VtProducto extends Model
{
    protected $table = 'ventas_producto_bet';
    public $timestamps = true;
    protected $primaryKey = 'id';
    protected $fillable = [
        'agencia_id',
        'producto_id',
        'monto',
        'fecha',
        'sorteo_id',
        'source_hash',
    ];
}
