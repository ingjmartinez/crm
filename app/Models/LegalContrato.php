<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalContrato extends Model
{
    use HasFactory;

    protected $table = 'legal_contratos';

    protected $fillable = [
        'agencia_id', 'titulo', 'numero_contrato', 'contraparte', 'fecha_inicio',
        'fecha_fin', 'estado', 'renovacion_automatica', 'documento_path',
        'documento_nombre_original', 'observaciones', 'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'renovacion_automatica' => 'boolean',
        ];
    }

    public function agencia(): BelongsTo
    {
        return $this->belongsTo(Agencia::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function obligaciones(): HasMany
    {
        return $this->hasMany(LegalObligacion::class);
    }
}
