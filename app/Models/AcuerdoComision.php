<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcuerdoComision extends Model
{
    use HasFactory;

    protected $table = 'comision_acuerdos';

    protected $fillable = [
        'nombre',
        'apellido',
        'correo',
        'cedula',
        'telefono',
        'porcentaje',
        'activo',
    ];

    protected $casts = [
        'porcentaje' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function getCedulaAttribute($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $cedula = preg_replace('/\D/', '', (string) $value);

        return str_pad($cedula, 11, '0', STR_PAD_LEFT);
    }

    public function agencias()
    {
        return $this->belongsToMany(
            Agencia::class,
            'comision_acuerdo_agencia',
            'comision_acuerdo_id',
            'agencia_id'
        )->withTimestamps();
    }
}
