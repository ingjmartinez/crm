<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaOnlinePromedioHistorico extends Model
{
    protected $table = 'ventas_online_promedios_historicos';

    protected $fillable = [
        'tipo_categoria',
        'monto_promedio',
        'meses_incluidos',
        'calculado_en',
        'calculado_por_id',
    ];

    protected function casts(): array
    {
        return [
            'monto_promedio' => 'decimal:2',
            'meses_incluidos' => 'array',
            'calculado_en' => 'datetime',
        ];
    }

    public function calculadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculado_por_id');
    }
}
