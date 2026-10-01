<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BiPremioDia extends Model
{
    /** @use HasFactory<\Database\Factories\BiPremioDiaFactory> */
    use HasFactory;

    protected $fillable = [
        'fecha',
        'premios',
        'registros',
        'origen',
        'resumido_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'premios' => 'decimal:2',
            'registros' => 'integer',
            'resumido_en' => 'datetime',
        ];
    }
}
