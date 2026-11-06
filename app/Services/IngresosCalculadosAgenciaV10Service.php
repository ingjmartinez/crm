<?php

namespace App\Services;

use App\Models\EntradaDiario;
use Carbon\Carbon;

class IngresosCalculadosAgenciaV10Service
{
    public const CUENTA_VENTAS = '200111001';

    public const CUENTA_INGRESOS = '400120001';

    /** @var array<string, array{porcentaje: ?float, base: float}> */
    private array $consultas = [];

    public static function clave(string $empresa, string $mes): string
    {
        return 'rentabilidad-v10:porcentajes-ingresos:global-v2:'.$empresa.':'.$mes;
    }

    /** @return array{porcentaje: ?float, estado: string, base: float} */
    public function porcentaje(string $empresa, string $mes): array
    {
        $empresaId = match ($empresa) {
            'Grupo Joselito' => '168', 'Negosur' => '169', default => null,
        };
        if ($empresaId === null) {
            return ['porcentaje' => null, 'estado' => 'sin_datos', 'base' => 0.0];
        }

        $clave = $empresaId.'|'.$mes;
        if (! array_key_exists($clave, $this->consultas)) {
            $this->consultas[$clave] = $this->consultarMes($empresaId, $mes);
        }
        $consulta = $this->consultas[$clave];

        return ['porcentaje' => $consulta['porcentaje'], 'estado' => $consulta['porcentaje'] === null ? 'sin_datos' : 'listo', 'base' => $consulta['base']];
    }

    /** @return array{porcentaje: ?float, base: float} */
    public function consultarMes(string $empresa, string $mes): array
    {
        $desde = Carbon::createFromFormat('!Y-m', $mes);
        $creditos = EntradaDiario::query()
            ->where('company_id', $empresa)
            ->where('fecha', '>=', $desde->toDateString())
            ->where('fecha', '<', $desde->copy()->addMonth()->toDateString())
            ->whereIn('cuenta', [self::CUENTA_VENTAS, self::CUENTA_INGRESOS])
            ->select('cuenta')
            ->selectRaw('SUM(COALESCE(credito, 0)) AS credito')
            ->groupBy('cuenta')
            ->pluck('credito', 'cuenta');
        $ventas = (float) $creditos->get(self::CUENTA_VENTAS, 0);

        return [
            'porcentaje' => $ventas > 0 && $creditos->has(self::CUENTA_INGRESOS)
                ? (float) $creditos->get(self::CUENTA_INGRESOS) / $ventas : null,
            'base' => $ventas,
        ];
    }
}
