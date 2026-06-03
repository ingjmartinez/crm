<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicioGeneralMantenimientoEquipo extends Model
{
    protected $table = 'servicio_general_mantenimiento_equipos';

    protected $fillable = [
        'terminal_codigo',
        'agencia_id',
        'nombre_agencia',
        'equipo_tipo',
        'equipo_codigo',
        'descripcion',
        'fecha_mantenimiento',
        'estado',
        'realizado_at',
        'creado_por',
        'realizado_por',
        'observacion',
    ];

    protected $casts = [
        'fecha_mantenimiento' => 'date',
        'realizado_at' => 'datetime',
    ];

    public function agencia(): BelongsTo
    {
        return $this->belongsTo(Agencia::class, 'agencia_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function realizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'realizado_por');
    }

    public function getEstadoCalculadoAttribute(): string
    {
        if ($this->estado === 'realizado') {
            return 'realizado';
        }

        $fecha = $this->fecha_mantenimiento instanceof Carbon
            ? $this->fecha_mantenimiento->copy()->startOfDay()
            : Carbon::parse($this->fecha_mantenimiento)->startOfDay();

        $hoy = now()->startOfDay();

        if ($fecha->lt($hoy)) {
            return 'vencido';
        }

        if ($fecha->diffInDays($hoy) <= 30) {
            return 'por_vencer';
        }

        return 'vigente';
    }

    public function getDiasRestantesAttribute(): ?int
    {
        if (!$this->fecha_mantenimiento || $this->estado === 'realizado') {
            return null;
        }

        return now()->startOfDay()->diffInDays($this->fecha_mantenimiento->copy()->startOfDay(), false);
    }
}
