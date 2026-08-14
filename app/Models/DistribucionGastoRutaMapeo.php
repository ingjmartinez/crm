<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistribucionGastoRutaMapeo extends Model
{
    protected $fillable = [
        'ruta_key', 'ruta_nombre', 'company_id', 'id_grupo', 'nombre_grupo',
        'id_sub_grupo', 'nombre_socio', 'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
