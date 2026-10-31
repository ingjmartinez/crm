<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentroCostoCuentaRuta extends Model
{
    /** @use HasFactory<\Database\Factories\CentroCostoCuentaRutaFactory> */
    use HasFactory;

    protected $fillable = ['company_id', 'ruta_key', 'cuenta_ruta_id'];

    public function cuentaRuta(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_ruta_id');
    }

    public static function claveRuta(?string $ruta): string
    {
        $ruta = trim((string) $ruta);
        if (preg_match('/^(\d+)\s*-/', $ruta, $coincidencias)) {
            return $coincidencias[1];
        }

        return mb_strtoupper($ruta, 'UTF-8');
    }
}
