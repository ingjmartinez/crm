<?php

namespace App\Services;

use Illuminate\Contracts\Config\Repository;

class ClasificadorRentabilidadAgenciaV7
{
    private const CATEGORIAS = [
        'rentable' => ['nombre' => 'Rentable', 'color' => 'success'],
        'buena' => ['nombre' => 'Buena', 'color' => 'primary'],
        'para_rescatar' => ['nombre' => 'Para rescatar', 'color' => 'warning'],
        'no_rentable' => ['nombre' => 'No rentable', 'color' => 'danger'],
        'en_evaluacion' => ['nombre' => 'En evaluación', 'color' => 'secondary'],
        'sin_ventas' => ['nombre' => 'Sin ventas', 'color' => 'secondary'],
    ];

    public function __construct(private readonly Repository $config) {}

    /** @return list<array{codigo: string, nombre: string, color: string}> */
    public static function categorias(): array
    {
        $categorias = [];
        foreach (self::CATEGORIAS as $codigo => $categoria) {
            $categorias[] = ['codigo' => $codigo, ...$categoria];
        }

        return $categorias;
    }

    public function firmaConfiguracion(): string
    {
        return hash('sha256', json_encode($this->config->get('rentabilidad'), JSON_THROW_ON_ERROR));
    }

    /**
     * @param  list<array<string, mixed>>  $meses
     * @return array<string, mixed>
     */
    public function clasificar(array $meses): array
    {
        $opciones = $this->config->get('rentabilidad');
        $minMeses = max(1, (int) $opciones['min_meses']);
        $ventana = max(1, (int) $opciones['ventana_reciente']);
        $ventaMinima = max(0, (float) $opciones['venta_minima_mes']);
        $factor = max(0, (float) $opciones['factor_recuperacion']);
        $perdidaMaximaRescate = max(0, (float) $opciones['perdida_maxima_rescate']);
        $balanceAcumulado = round(array_sum(array_column($meses, 'balance_general')), 2);
        $mesesConVentas = array_values(array_filter($meses, fn (array $mes): bool => (float) $mes['ventas'] > 0 && (float) $mes['ventas'] >= $ventaMinima));
        usort($mesesConVentas, fn (array $a, array $b): int => strcmp((string) ($a['mes'] ?? ''), (string) ($b['mes'] ?? '')));
        $conVentas = count($mesesConVentas);
        $positivos = count(array_filter($mesesConVentas, fn (array $mes): bool => (float) $mes['balance_general'] > 0));
        $recientes = array_slice($mesesConVentas, -$ventana);
        $balanceReciente = round(array_sum(array_column($recientes, 'balance_general')), 2);
        $positivosRecientes = count(array_filter($recientes, fn (array $mes): bool => (float) $mes['balance_general'] > 0));
        $resultado = [
            'meses_positivos' => $positivos, 'meses_con_ventas' => $conVentas,
            'porcentaje' => $conVentas > 0 ? $positivos / $conVentas * 100 : null,
            'balance_acumulado' => $balanceAcumulado, 'balance_reciente' => $balanceReciente,
            'ventana_reciente' => $ventana, 'meses_recientes' => count($recientes),
            'positivos_recientes' => $positivosRecientes, 'en_deterioro' => false,
        ];

        if ($conVentas === 0) {
            return $this->resultado($resultado, 'sin_ventas', 'sin_ventas', 'Sin meses con ventas que alcancen el mínimo configurado.');
        }
        if ($conVentas < $minMeses) {
            return $this->resultado($resultado, 'en_evaluacion', 'historial_insuficiente', 'Historial insuficiente: se requieren '.$minMeses.' meses con ventas.');
        }

        $codigo = 'no_rentable';
        foreach (['rentable' => 'umbral_rentable', 'buena' => 'umbral_buena', 'para_rescatar' => 'umbral_rescatar'] as $categoria => $umbral) {
            if ($positivos * 100 >= $conVentas * (float) $opciones[$umbral]) {
                $codigo = $categoria;
                break;
            }
        }
        $motivoCodigo = 'porcentaje_meses_positivos';
        $motivo = 'Clasificada por el porcentaje de meses positivos.';

        if (in_array($codigo, ['rentable', 'buena'], true) && $balanceAcumulado < 0) {
            $codigo = 'para_rescatar';
            $motivoCodigo = 'acumulado_negativo';
            $motivo = 'Limitada a Para rescatar por balance acumulado negativo.';
        }

        if ($codigo === 'no_rentable') {
            if ($balanceAcumulado > 0) {
                $codigo = 'para_rescatar';
                $motivoCodigo = 'rescate_acumulado_positivo';
                $motivo = 'Rescatada por balance acumulado positivo.';
            } elseif ($balanceAcumulado < 0 && abs($balanceAcumulado) < $perdidaMaximaRescate) {
                $codigo = 'para_rescatar';
                $motivoCodigo = 'perdida_acumulada_limitada';
                $motivo = 'Rescatada por pérdida acumulada menor de RD$ '.number_format($perdidaMaximaRescate, 2).'.';
            } elseif ($conVentas >= $ventana && $balanceReciente > 0 && $positivosRecientes >= $ventana - 1 && abs($balanceAcumulado) <= round($balanceReciente * $factor, 2)) {
                $codigo = 'para_rescatar';
                $motivoCodigo = 'recuperacion_reciente';
                $motivo = 'Rescatada por recuperación reciente.';
            }
        }

        if (in_array($codigo, ['rentable', 'buena'], true) && $conVentas >= $ventana && $balanceReciente <= 0) {
            $resultado['en_deterioro'] = true;
            $motivo .= ' La suma de los últimos '.$ventana.' meses con ventas es no positiva.';
        }

        return $this->resultado($resultado, $codigo, $motivoCodigo, $motivo);
    }

    /** @param array<string, mixed> $datos @return array<string, mixed> */
    private function resultado(array $datos, string $codigo, string $motivoCodigo, string $motivo): array
    {
        return [...$datos, 'codigo' => $codigo, ...self::CATEGORIAS[$codigo], 'motivo_codigo' => $motivoCodigo, 'motivo' => $motivo];
    }
}
