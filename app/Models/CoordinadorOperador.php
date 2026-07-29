<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CoordinadorOperador extends Model
{
    use HasFactory;

    protected $table = 'coordinador_operador';

    protected $fillable = [
        'empleado_id',
        'nombre',
        'apellido',
        'correo',
        'cedula',
        'telefono',
        'puesto',
    ];

    public function getCedulaAttribute($value): string
    {
        $cedula = preg_replace('/\D/', '', (string) $value);

        if ($cedula === '') {
            return '';
        }

        return str_pad($cedula, 11, '0', STR_PAD_LEFT);
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function agencias(): BelongsToMany
    {
        return $this->belongsToMany(
            Agencia::class,
            'coordinador_operador_agencia',
            'coordinador_operador_id',
            'agencia_id'
        )->withTimestamps();
    }
}
