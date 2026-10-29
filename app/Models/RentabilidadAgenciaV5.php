<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RentabilidadAgenciaV5 extends Model
{
    protected $table = 'agencias';

    protected function casts(): array
    {
        return [
            'estatus' => 'integer',
        ];
    }

    #[Scope]
    protected function activas(Builder $query): void
    {
        $query->where('estatus', 1);
    }
}
