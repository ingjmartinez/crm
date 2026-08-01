<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValidadorAgenciaDetalle extends Model
{
    /** @use HasFactory<\Database\Factories\ValidadorAgenciaDetalleFactory> */
    use HasFactory;

    protected $fillable = [
        'carga_id',
        'centro_costo_id',
        'terminal',
        'terminal_normalizada',
        'nombre_agencia',
        'ruta',
        'sociedad',
        'company_id',
        'nombre_centro_costo',
        'ruta_centro_costo',
        'sociedad_centro_costo',
        'estado',
        'observacion',
        'aplicado_en',
        'aplicado_por',
    ];

    protected function casts(): array
    {
        return [
            'aplicado_en' => 'datetime',
        ];
    }

    public function carga(): BelongsTo
    {
        return $this->belongsTo(ValidadorAgenciaCarga::class, 'carga_id');
    }

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroDeCosto::class, 'centro_costo_id');
    }
}
