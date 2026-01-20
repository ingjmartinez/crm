<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VtUsuarioNet extends Model
{
    protected $table = 'vt_usuarios_net';
    public $timestamps = false;
    protected $primaryKey = 'vt_usuario_id';
    protected $fillable = ['consorcio_id', 'agencia_id', 'cedula', 'tipo', 'producto_id', 'descripcion', 'monto'];
}
