<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoordinadorOperador extends Model
{
    use HasFactory;

    protected $table = 'coordinador_operador';

    protected $fillable = [
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

        return str_pad($cedula, 11, '0', STR_PAD_LEFT);
    }

    public function agencias()
    {
        return $this->belongsToMany(
            Agencia::class,
            'coordinador_operador_agencia',
            'coordinador_operador_id',
            'agencia_id'
        )->withTimestamps();
    }
}
