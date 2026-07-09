<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncentivoAdministrativo extends Model
{
    use HasFactory;

    public const EMPRESAS_VALIDAS = [
        'Consorcio Joselito',
        'Negosur',
    ];

    protected $fillable = [
        'grupo',
        'nombre',
        'cedula',
        'empresa',
        'pct_total',
    ];

    protected $casts = [
        'pct_total' => 'float',
    ];
}
