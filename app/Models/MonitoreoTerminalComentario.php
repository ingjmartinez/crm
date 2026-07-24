<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoreoTerminalComentario extends Model
{
    /** @use HasFactory<\Database\Factories\MonitoreoTerminalComentarioFactory> */
    use HasFactory;

    protected $fillable = [
        'agencia_id',
        'usuario_id',
        'comentario',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function agencia(): BelongsTo
    {
        return $this->belongsTo(Agencia::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
