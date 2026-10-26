<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NominaDomingoAuditoria extends Model
{
    protected $fillable = ['accion', 'categoria', 'terminal', 'usuario_id', 'usuario_nombre', 'usuario_email', 'antes', 'despues', 'ip'];

    protected function casts(): array
    {
        return ['antes' => 'array', 'despues' => 'array'];
    }
}
