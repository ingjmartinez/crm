<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncentivoAdministrativoAuditoria extends Model
{
    protected $fillable = [
        'accion',
        'usuario_id',
        'usuario_nombre',
        'usuario_email',
        'registro_id',
        'empleado_nombre',
        'cedula',
        'empresa',
        'datos',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'datos' => 'array',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
