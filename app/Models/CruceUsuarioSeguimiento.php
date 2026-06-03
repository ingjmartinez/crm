<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CruceUsuarioSeguimiento extends Model
{
    protected $table = 'cruce_usuario_seguimientos';

    protected $fillable = [
        'cedula',
        'empleado_id',
        'nombre_completo',
        'detalle',
        'estatus_origen',
        'ultima_fecha_venta',
        'reporte_fecha_inicio',
        'reporte_fecha_fin',
        'sistema',
        'empresa',
        'estado',
        'gestion_inicio_at',
        'finalizado_at',
        'creado_por',
        'gestion_iniciada_por',
        'finalizado_por',
        'observacion',
    ];

    protected $casts = [
        'ultima_fecha_venta' => 'date',
        'reporte_fecha_inicio' => 'date',
        'reporte_fecha_fin' => 'date',
        'gestion_inicio_at' => 'datetime',
        'finalizado_at' => 'datetime',
    ];

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function gestionIniciadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gestion_iniciada_por');
    }

    public function finalizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalizado_por');
    }

    public function getCodigoAttribute(): string
    {
        return 'CRU-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function getBadgeEstadoAttribute(): string
    {
        return match ($this->estado) {
            'pendiente' => 'warning',
            'en_gestion' => 'info',
            'finalizado' => 'success',
            default => 'secondary',
        };
    }
}
