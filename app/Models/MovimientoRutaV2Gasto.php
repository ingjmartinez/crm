<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoRutaV2Gasto extends Model
{
    public const CUENTA_COMBUSTIBLE = '600120005';

    public const CUENTAS_DISTRIBUCION = [
        '100120003', '101120002', '103120001', '200150001', '600110017', '600110019',
        '600110023', '600120001', '600120002', '600120003', '600120005', '600120008',
        '600120012', '600120016', '600120021', '600120022', '6001225', '6001226',
        '600130009', '600140002', '600140003', '600220001', '600240003',
    ];

    protected $table = 'movimientos_rutas_v2_gastos';

    protected $fillable = [
        'fecha', 'ruta_key', 'ruta', 'monto', 'concepto', 'cuenta_codigo', 'cuenta_descripcion',
        'distribucion_tipo', 'centro_costo_id', 'terminal_destino', 'agencia_destino',
        'socio_codigo', 'socio_nombre', 'comprobante_path', 'observacion', 'estado',
        'user_id', 'clasificado_por_id', 'clasificado_at',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'monto' => 'decimal:2', 'clasificado_at' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
