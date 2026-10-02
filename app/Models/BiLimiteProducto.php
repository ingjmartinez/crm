<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiLimiteProducto extends Model
{
    public const GRUPOS = [
        'grupo:tradicional' => 'Total Tradicionales',
        'grupo:no_tradicional' => 'Total No tradicionales',
    ];

    /** @use HasFactory<\Database\Factories\BiLimiteProductoFactory> */
    use HasFactory;

    protected $fillable = ['producto_id', 'terminal', 'monto', 'activo'];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2', 'activo' => 'boolean'];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(CatalogoJuego::class, 'producto_id', 'producto_id');
    }
}
