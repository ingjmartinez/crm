<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BiHorario extends Model
{
    /** @use HasFactory<\Database\Factories\BiHorarioFactory> */
    use HasFactory;

    protected $fillable = ['clave', 'tipo', 'cedula', 'terminal', 'entrada', 'salida'];
}
