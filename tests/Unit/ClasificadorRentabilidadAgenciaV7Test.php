<?php

namespace Tests\Unit;

use App\Services\ClasificadorRentabilidadAgenciaV7;
use App\Services\ClasificadorRentabilidadMensual;
use Illuminate\Config\Repository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ClasificadorRentabilidadAgenciaV7Test extends TestCase
{
    private function clasificador(array $opciones = []): ClasificadorRentabilidadAgenciaV7
    {
        return new ClasificadorRentabilidadAgenciaV7(new Repository([
            'rentabilidad' => array_replace(require dirname(__DIR__, 2).'/config/rentabilidad.php', $opciones),
        ]));
    }

    private function meses(array $balances): array
    {
        $meses = [];
        foreach ($balances as $indice => $balance) {
            $meses[] = ['mes' => sprintf('2026-%02d', $indice + 1), 'ventas' => 100, 'balance_general' => $balance];
        }

        return $meses;
    }

    public function test_pradera_con_tres_meses_positivos_y_balance_total_positivo_se_rescata(): void
    {
        $balances = [91008.82, 146739.58, -5717.22, -9371.26, -51935.45, -30244.7, 28026.59, -16841.61, -34839.28];
        $meses = array_map(fn (float $balance): array => ['ventas' => 100, 'balance_general' => $balance], $balances);
        $resultado = $this->clasificador()->clasificar($meses);
        $this->assertSame(116825.47, round(array_sum($balances), 2));
        $this->assertSame('Para rescatar', $resultado['nombre']);
        $this->assertSame('warning', $resultado['color']);
        $this->assertSame(3, $resultado['meses_positivos']);
        $this->assertSame(9, $resultado['meses_con_ventas']);
        $this->assertEqualsWithDelta(33.3333, $resultado['porcentaje'], 0.001);
        $this->assertSame('No rentable', (new ClasificadorRentabilidadMensual)->clasificar($meses, 'balance_general')['nombre']);
    }

    public static function balancesAcumulados(): array
    {
        return [[0.01, 'Para rescatar'], [0.0, 'No rentable'], [-0.01, 'No rentable']];
    }

    #[DataProvider('balancesAcumulados')]
    public function test_el_ajuste_requiere_un_balance_acumulado_estrictamente_positivo(float $balanceTotal, string $categoria): void
    {
        $meses = array_merge(
            array_fill(0, 3, ['ventas' => 100, 'balance_general' => 2]),
            array_fill(0, 5, ['ventas' => 100, 'balance_general' => -1]),
            [['ventas' => 100, 'balance_general' => $balanceTotal - 1]],
        );
        $resultado = $this->clasificador()->clasificar($meses);
        $this->assertSame($categoria, $resultado['nombre']);
    }

    public function test_incluye_los_gastos_de_meses_sin_ventas_en_el_balance_acumulado(): void
    {
        $meses = array_merge(
            [['ventas' => 100, 'balance_general' => 100]],
            array_fill(0, 2, ['ventas' => 100, 'balance_general' => -1]),
            [['ventas' => 100, 'balance_general' => -1]],
            [['ventas' => 0, 'balance_general' => -200]],
        );
        $servicio = $this->clasificador();
        $resultado = $servicio->clasificar($meses);
        $this->assertSame('No rentable', $resultado['nombre']);
        $this->assertSame(4, $resultado['meses_con_ventas']);
        $this->assertSame('Sin ventas', $servicio->clasificar([['ventas' => 0, 'balance_general' => 100]])['nombre']);
    }

    public function test_higuey_se_rescata_por_recuperacion_reciente_y_respeta_el_orden(): void
    {
        $balances = [-52993.06, -76624.06, -7392.16, -5378.87, 15745.22, -61262.07, 59625.64, 33836.37, 10580.58];
        $meses = $this->meses($balances);
        $resultado = $this->clasificador()->clasificar(array_reverse($meses));
        $this->assertSame('Para rescatar', $resultado['nombre']);
        $this->assertSame('recuperacion_reciente', $resultado['motivo_codigo']);
        $this->assertSame(4, $resultado['meses_positivos']);
        $this->assertSame(9, $resultado['meses_con_ventas']);
        $this->assertSame(-83862.41, $resultado['balance_acumulado']);
        $this->assertSame(104042.59, $resultado['balance_reciente']);
        $this->assertSame(3, $resultado['positivos_recientes']);
        $this->assertFalse($resultado['en_deterioro']);
        $inverso = $this->clasificador()->clasificar($this->meses(array_reverse($balances)));
        $this->assertSame('No rentable', $inverso['nombre']);
        $this->assertSame($resultado['balance_acumulado'], $inverso['balance_acumulado']);
        $this->assertSame($balances[0], $meses[0]['balance_general']);
    }

    public function test_agencia_que_empezo_bien_y_cayo_no_se_rescata(): void
    {
        $resultado = $this->clasificador()->clasificar($this->meses([40000, 30000, 10000, -5000, -20000, -35000, -40000]));
        $this->assertSame('No rentable', $resultado['nombre']);
        $this->assertSame(-20000.0, $resultado['balance_acumulado']);
        $this->assertFalse($resultado['en_deterioro']);
    }

    public function test_acumulado_negativo_limita_rentable_y_buena(): void
    {
        foreach ([[10, 10, 10, 10], [10, 10, 10, 10, 10, 10, 10, 10, -1]] as $balances) {
            $meses = $this->meses($balances);
            $meses[] = ['mes' => '2026-12', 'ventas' => 0, 'balance_general' => -1000];
            $resultado = $this->clasificador()->clasificar($meses);
            $this->assertSame('Para rescatar', $resultado['nombre']);
            $this->assertSame('acumulado_negativo', $resultado['motivo_codigo']);
            $this->assertFalse($resultado['en_deterioro']);
        }
    }

    public function test_buena_con_ventana_no_positiva_muestra_alerta_sin_cambiar_categoria(): void
    {
        foreach ([-250, -200] as $ultimoBalance) {
            $resultado = $this->clasificador()->clasificar($this->meses([...array_fill(0, 8, 100), $ultimoBalance]));
            $this->assertSame('Buena', $resultado['nombre']);
            $this->assertSame(8, $resultado['meses_positivos']);
            $this->assertTrue($resultado['en_deterioro']);
        }
    }

    public function test_dos_meses_son_en_evaluacion_y_un_cero_no_es_positivo(): void
    {
        $servicio = $this->clasificador();
        $corto = $servicio->clasificar($this->meses([100, 100]));
        $this->assertSame('En evaluación', $corto['nombre']);
        $this->assertSame('en_evaluacion', $corto['codigo']);
        $this->assertSame('secondary', $corto['color']);
        $this->assertFalse($corto['en_deterioro']);
        $conCero = $servicio->clasificar($this->meses([100, 0, -1]));
        $this->assertSame(1, $conCero['meses_positivos']);
        $this->assertSame(3, $conCero['meses_con_ventas']);
        $sinVentas = $servicio->clasificar([['ventas' => 0, 'balance_general' => -10]]);
        $this->assertSame('Sin ventas', $sinVentas['nombre']);
        $this->assertNull($sinVentas['porcentaje']);
        $this->assertFalse($sinVentas['en_deterioro']);
    }

    public function test_la_recuperacion_exige_cantidad_de_positivos_y_limite_de_perdida(): void
    {
        $servicio = $this->clasificador();
        $limite = [...array_fill(0, 6, -9.5), 10, 10, -1];
        $this->assertSame('Para rescatar', $servicio->clasificar($this->meses($limite))['nombre']);
        $limite[0] = -9.51;
        $this->assertSame('No rentable', $servicio->clasificar($this->meses($limite))['nombre']);
        $pocosPositivos = [...array_fill(0, 6, -10), 50, -1, -1];
        $this->assertSame('No rentable', $servicio->clasificar($this->meses($pocosPositivos))['nombre']);
    }

    public function test_los_parametros_configurados_controlan_ventas_historial_ventana_y_umbrales(): void
    {
        $meses = $this->meses([1000, 10, 10, 10, 10]);
        $meses[0]['ventas'] = 99;
        $conMinimo = $this->clasificador(['venta_minima_mes' => 100])->clasificar($meses);
        $this->assertSame(4, $conMinimo['meses_con_ventas']);
        $this->assertSame(1040.0, $conMinimo['balance_acumulado']);
        $this->assertSame('Rentable', $conMinimo['nombre']);
        $this->assertSame('En evaluación', $this->clasificador(['min_meses' => 4])->clasificar($this->meses([10, 10, 10]))['nombre']);
        $this->assertSame('Buena', $this->clasificador(['umbral_buena' => 60])->clasificar($this->meses([...array_fill(0, 6, 10), -1, -1, -1]))['nombre']);
        $balances = [...array_fill(0, 7, -3), 10, -1];
        $this->assertSame('No rentable', $this->clasificador()->clasificar($this->meses($balances))['nombre']);
        $this->assertSame('Para rescatar', $this->clasificador(['ventana_reciente' => 2])->clasificar($this->meses($balances))['nombre']);
        $this->assertNotSame($this->clasificador()->firmaConfiguracion(), $this->clasificador(['min_meses' => 5])->firmaConfiguracion());
    }

    public function test_hasta_tres_meses_con_ventas_permanecen_en_evaluacion(): void
    {
        foreach ([1, 2, 3] as $cantidad) {
            $resultado = $this->clasificador()->clasificar($this->meses(array_fill(0, $cantidad, 100)));
            $this->assertSame('En evaluación', $resultado['nombre']);
            $this->assertSame($cantidad, $resultado['meses_con_ventas']);
        }
        $this->assertSame('Rentable', $this->clasificador()->clasificar($this->meses([100, 100, 100, 100]))['nombre']);
        $this->assertSame('Sin ventas', $this->clasificador()->clasificar([])['nombre']);
    }
}
