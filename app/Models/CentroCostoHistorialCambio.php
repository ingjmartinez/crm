<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentroCostoHistorialCambio extends Model
{
    /** @use HasFactory<\Database\Factories\CentroCostoHistorialCambioFactory> */
    use HasFactory;

    protected $fillable = [
        'centro_costo_id',
        'carga_id',
        'detalle_id',
        'terminal',
        'terminal_normalizada',
        'company_id',
        'accion',
        'campo',
        'valor_anterior',
        'valor_nuevo',
        'archivo_origen',
        'observacion',
        'usuario_id',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
