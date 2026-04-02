<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogoJuego extends Model
{
    protected $table = 'catalogo_juegos';

    public $timestamps = false;

    protected $fillable = [
        'producto_id',
        'tipo',
        'descripcion',
    ];
}

