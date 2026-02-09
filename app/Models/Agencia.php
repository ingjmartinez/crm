<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agencia extends Model
{
    use HasFactory;

    protected $table = 'agencias';

    protected $primaryKey = 'id';

    protected $fillable = [
        'agencia',
        'nombre_agencia',
        'terminal',
        'sistema',
        'ciudad',
        'ruta',
        'operador',
        'coordinador',
        'aplica_incentivo',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'aplica_incentivo' => 'boolean',
    ];
}
