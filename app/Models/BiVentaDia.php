<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BiVentaDia extends Model
{
    /** @use HasFactory<\Database\Factories\BiVentaDiaFactory> */
    use HasFactory;

    protected $fillable = [
        'fecha',
        'tradicional',
        'no_tradicional',
        'externas',
        'recargas',
        'otros',
        'registros',
        'origen',
        'resumido_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'tradicional' => 'decimal:2',
            'no_tradicional' => 'decimal:2',
            'externas' => 'decimal:2',
            'recargas' => 'decimal:2',
            'otros' => 'decimal:2',
            'registros' => 'integer',
            'resumido_en' => 'datetime',
        ];
    }
}
