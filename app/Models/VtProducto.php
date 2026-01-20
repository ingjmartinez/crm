<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VtProducto extends Model
{
    protected $table = 'ventas_producto_bet';
    public $timestamps = false;
    protected $primaryKey = 'venta_id';
    protected $fillable = [
        'consorcio_id',
        'agencia_id',
        'producto_id',
        'tipo',
        'descripcion',
        'monto',
        'fecha',
        'comision',
        'comision_supervisor',
        'numero_sorteo',
        'fecha_sorteo'
    ];
}
