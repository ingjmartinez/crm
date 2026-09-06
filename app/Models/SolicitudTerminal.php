<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SolicitudTerminal extends Model
{
    /** @use HasFactory<\Database\Factories\SolicitudTerminalFactory> */
    use HasFactory;

    protected $table = 'solicitudes_terminales';

    protected $fillable = [
        'solicitado_por',
        'prefijo_empresa',
        'prefijo_seleccionado',
        'cantidad',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
        ];
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    public function codigos(): HasMany
    {
        return $this->hasMany(SolicitudTerminalCodigo::class);
    }

    public function getNumeroAttribute(): string
    {
        return 'GJ-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
