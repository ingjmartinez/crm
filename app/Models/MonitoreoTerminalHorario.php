<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonitoreoTerminalHorario extends Model
{
    /** @use HasFactory<\Database\Factories\MonitoreoTerminalHorarioFactory> */
    use HasFactory;

    protected $fillable = [
        'hora',
        'tipo_horario',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
