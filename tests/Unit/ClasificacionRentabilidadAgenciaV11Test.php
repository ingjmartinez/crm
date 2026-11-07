<?php

namespace Tests\Unit;

use App\Services\ClasificacionRentabilidadAgenciaV11;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ClasificacionRentabilidadAgenciaV11Test extends TestCase
{
    #[DataProvider('rangos')]
    public function test_clasifica_los_limites_del_margen(float $neto, string $etiqueta, string $color): void
    {
        $resultado = (new ClasificacionRentabilidadAgenciaV11)->clasificar([
            ['mes' => '2026-07', 'ventas' => 100, 'ventas_tradicionales' => 100, 'balance_general' => $neto],
        ]);
        $this->assertSame($etiqueta, $resultado['etiqueta']);
        $this->assertSame($color, $resultado['color']);
        $this->assertSame($neto, $resultado['margen']);
        $this->assertTrue($resultado['provisional']);
        $this->assertFalse($resultado['deterioro']);
    }

    public static function rangos(): array
    {
        return [
            'rentable' => [15.0, 'Rentables', '#0f5132'],
            'buena' => [5.0, 'Buenas', '#084298'],
            'antes de rentable' => [14.99, 'Buenas', '#084298'],
            'regular' => [0.0, 'Para trabajar', '#984c0c'],
            'antes de buena' => [4.99, 'Para trabajar', '#984c0c'],
            'rescatar' => [-20.0, 'Para trabajar', '#984c0c'],
            'perdida moderada' => [-15.0, 'Para trabajar', '#984c0c'],
            'anterior limite' => [-10.01, 'Para trabajar', '#984c0c'],
            'antes de regular' => [-0.01, 'Para trabajar', '#984c0c'],
            'anterior categoria negativa' => [-20.01, 'Para trabajar', '#984c0c'],
            'nuevo limite' => [-35.0, 'Para trabajar', '#984c0c'],
            'atencion prioritaria' => [-35.01, 'Atención prioritaria', '#842029'],
        ];
    }

    public function test_usa_el_margen_acumulado_y_avisa_los_dos_ultimos_meses_negativos(): void
    {
        $resultado = (new ClasificacionRentabilidadAgenciaV11)->clasificar([
            ['mes' => '2026-09', 'ventas' => 100, 'ventas_tradicionales' => 100, 'balance_general' => -50],
            ['mes' => '2026-07', 'ventas' => 1000, 'ventas_tradicionales' => 1000, 'balance_general' => 200],
            ['mes' => '2026-08', 'ventas' => 100, 'ventas_tradicionales' => 100, 'balance_general' => -50],
        ]);
        $this->assertSame(8.33, $resultado['margen']);
        $this->assertSame('Buenas', $resultado['etiqueta']);
        $this->assertSame(3, $resultado['meses_ventas']);
        $this->assertFalse($resultado['provisional']);
        $this->assertTrue($resultado['deterioro']);
    }

    public function test_un_mes_sin_ventas_mantiene_sus_gastos_y_hace_provisional_la_evaluacion(): void
    {
        $resultado = (new ClasificacionRentabilidadAgenciaV11)->clasificar([
            ['mes' => '2026-07', 'ventas' => 100, 'ventas_tradicionales' => 100, 'balance_general' => 50],
            ['mes' => '2026-08', 'ventas' => 0, 'ventas_tradicionales' => 0, 'balance_general' => -10],
            ['mes' => '2026-09', 'ventas' => 100, 'ventas_tradicionales' => 100, 'balance_general' => -10],
        ]);
        $this->assertSame(15.0, $resultado['margen']);
        $this->assertSame(2, $resultado['meses_ventas']);
        $this->assertTrue($resultado['provisional']);
        $this->assertTrue($resultado['deterioro']);
    }

    public function test_resumen_incluye_categorias_en_cero_y_cuenta_sin_datos(): void
    {
        $servicio = new ClasificacionRentabilidadAgenciaV11;
        $resumen = collect($servicio->resumir([
            ['clasificacion' => ['etiqueta' => 'Rentables']],
            ['clasificacion' => ['etiqueta' => 'Rentables']],
            ['clasificacion' => ['etiqueta' => 'Sin datos']],
            ['clasificacion' => ['etiqueta' => 'Atención prioritaria']],
        ]))->keyBy('etiqueta');
        $this->assertCount(5, $resumen);
        $this->assertSame(4, $resumen->sum('cantidad'));
        $this->assertSame(2, $resumen['Rentables']['cantidad']);
        $this->assertSame(1, $resumen['Sin datos']['cantidad']);
        $this->assertSame(1, $resumen['Atención prioritaria']['cantidad']);
        $this->assertSame(0, $resumen['Para trabajar']['cantidad']);
        $this->assertSame('#d1e7dd', $resumen['Rentables']['fondo']);
        $this->assertSame(0, collect($servicio->resumir([]))->sum('cantidad'));
    }

    public function test_no_clasifica_si_faltan_netos_o_la_base_tradicional_no_es_positiva(): void
    {
        foreach ([[], [['mes' => '2026-07', 'ventas' => 100, 'ventas_tradicionales' => 100, 'balance_general' => null]], [['mes' => '2026-07', 'ventas' => 100, 'ventas_tradicionales' => 0, 'balance_general' => -50]], [['mes' => '2026-07', 'ventas' => -100, 'ventas_tradicionales' => -100, 'balance_general' => -50]]] as $periodos) {
            $resultado = (new ClasificacionRentabilidadAgenciaV11)->clasificar($periodos);
            $this->assertSame('Sin datos', $resultado['etiqueta']);
            $this->assertSame('#495057', $resultado['color']);
            $this->assertNull($resultado['margen']);
            $this->assertFalse($resultado['deterioro']);
        }
    }
}
