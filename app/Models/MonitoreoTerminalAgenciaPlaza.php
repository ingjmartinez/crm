<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoreoTerminalAgenciaPlaza extends Model
{
    /** @use HasFactory<\Database\Factories\MonitoreoTerminalAgenciaPlazaFactory> */
    use HasFactory;

    protected $fillable = [
        'agencia_id',
        'usuario_id',
    ];

    public function agencia(): BelongsTo
    {
        return $this->belongsTo(Agencia::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
