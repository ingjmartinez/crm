<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ValidadorAgenciaCarga extends Model
{
    /** @use HasFactory<\Database\Factories\ValidadorAgenciaCargaFactory> */
    use HasFactory;

    protected $fillable = [
        'nombre_archivo',
        'hash_archivo',
        'filas_leidas',
        'filas_validas',
        'correctas',
        'nuevas',
        'nombres_diferentes',
        'rutas_diferentes',
        'sociedades_diferentes',
        'conflictos',
        'usuario_id',
    ];

    public function detalles(): HasMany
    {
        return $this->hasMany(ValidadorAgenciaDetalle::class, 'carga_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
