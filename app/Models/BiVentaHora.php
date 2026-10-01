<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BiVentaHora extends Model
{
    /** @use HasFactory<\Database\Factories\BiVentaHoraFactory> */
    use HasFactory;

    protected $fillable = [
        'fecha',
        'hora',
        'tradicional_acumulado',
        'no_tradicional_acumulado',
        'otros_acumulado',
        'externas_acumulado',
        'recargas_acumulado',
        'registros',
        'capturado_en',
        'rutas',
        'terminales_evaluadas',
        'terminales_con_venta',
        'terminales_categoria',
        'quiniela_loteka_acumulado',
        'mega_chance_acumulado',
        'productos',
        'productos_terminales',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'hora' => 'integer',
            'tradicional_acumulado' => 'decimal:2',
            'no_tradicional_acumulado' => 'decimal:2',
            'otros_acumulado' => 'decimal:2',
            'externas_acumulado' => 'decimal:2',
            'recargas_acumulado' => 'decimal:2',
            'registros' => 'integer',
            'capturado_en' => 'datetime',
            'rutas' => 'array',
            'terminales_evaluadas' => 'integer',
            'terminales_con_venta' => 'integer',
            'terminales_categoria' => 'array',
            'quiniela_loteka_acumulado' => 'decimal:2',
            'mega_chance_acumulado' => 'decimal:2',
            'productos' => 'array',
            'productos_terminales' => 'array',
        ];
    }
}
