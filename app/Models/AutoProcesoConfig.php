<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoProcesoConfig extends Model
{
    protected $table = 'auto_proceso_configs';

    protected $fillable = [
        'sistema',
        'enabled',
        'hora',
        'correo',
        'process_day_offset',
        'last_run_at',
        'last_status',
        'last_summary',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'process_day_offset' => 'integer',
        'last_run_at' => 'datetime',
        'last_summary' => 'array',
    ];
}
