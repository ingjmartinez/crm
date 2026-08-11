<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalObligacion extends Model
{
    use HasFactory;

    public const TIPOS = [
        'local' => 'Local',
        'luz' => 'Luz',
        'internet' => 'Internet',
        'mantenimiento' => 'Mantenimiento',
        'otro' => 'Otro',
    ];

    public const FRECUENCIAS = [
        'mensual' => 'Mensual',
        'trimestral' => 'Trimestral',
        'semestral' => 'Semestral',
        'anual' => 'Anual',
        'unico' => 'Pago único',
    ];

    protected $table = 'legal_obligaciones';

    protected $fillable = [
        'legal_contrato_id', 'tipo', 'descripcion', 'monto', 'frecuencia',
        'fecha_primer_pago', 'fecha_fin', 'activa', 'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_primer_pago' => 'date',
            'fecha_fin' => 'date',
            'activa' => 'boolean',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(LegalContrato::class, 'legal_contrato_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function pagosProgramados(): HasMany
    {
        return $this->hasMany(LegalPagoProgramado::class);
    }
}
