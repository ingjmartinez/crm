<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudTerminalCodigo extends Model
{
    /** @use HasFactory<\Database\Factories\SolicitudTerminalCodigoFactory> */
    use HasFactory;

    protected $table = 'solicitud_terminal_codigos';

    protected $fillable = [
        'codigo',
        'estado',
        'nombre_banca',
        'region',
        'provincia',
        'municipio',
        'ciudad',
        'sector',
        'calle',
        'direccion_local',
        'latitud',
        'longitud',
        'rja',
        'aprobado_por',
        'aprobado_at',
    ];

    protected function casts(): array
    {
        return [
            'aprobado_at' => 'datetime',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudTerminal::class, 'solicitud_terminal_id');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }
}
