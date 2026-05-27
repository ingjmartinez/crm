<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgenciaHorario extends Model
{
    use HasFactory;

    protected $table = 'agencia_horarios';

    protected $fillable = [
        'agencia_id',
        'dia_semana',
        'horario_am',
        'horario_pm',
    ];

    protected $casts = [
        'agencia_id' => 'integer',
        'dia_semana' => 'integer',
    ];

    public function agencia()
    {
        return $this->belongsTo(Agencia::class);
    }
}
