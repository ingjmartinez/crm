<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelectorAgenciaSeleccion extends Model
{
    /** @use HasFactory<\Database\Factories\SelectorAgenciaSeleccionFactory> */
    use HasFactory;

    protected $table = 'selector_agencia_selecciones';

    protected $fillable = ['user_id', 'terminales'];

    protected function casts(): array
    {
        return ['terminales' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
