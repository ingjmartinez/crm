<?php

namespace Tests\Unit;

use App\Services\ClasificadorRentabilidadMensual;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ClasificadorRentabilidadMensualTest extends TestCase
{
    public static function casos(): array
    {
        return [[9, 'Rentable'], [7, 'Buena'], [6, 'Para rescatar'], [5, 'Para rescatar'], [4, 'No rentable'], [2, 'No rentable'], [0, 'No rentable']];
    }

    #[DataProvider('casos')]
    public function test_clasifica_meses_con_ventas(int $positivos, string $nombre): void
    {
        $meses = array_map(fn (int $mes): array => ['ventas' => 100, 'balance_propio' => $mes <= $positivos ? 1 : 0], range(1, 9));
        $resultado = (new ClasificadorRentabilidadMensual)->clasificar($meses, 'balance_propio');
        $this->assertSame($nombre, $resultado['nombre']);
        $this->assertSame(9, $resultado['meses_con_ventas']);
        $this->assertSame($positivos, $resultado['meses_positivos']);
    }

    public function test_ignora_meses_sin_ventas_y_balance_cero_no_es_positivo(): void
    {
        $servicio = new ClasificadorRentabilidadMensual;
        $meses = [['ventas' => 100, 'balance_propio' => 10, 'balance_neto' => -1], ['ventas' => 0, 'balance_propio' => -999, 'balance_neto' => 1000], ['ventas' => -10, 'balance_propio' => 1, 'balance_neto' => 1]];
        $this->assertSame('Rentable', $servicio->clasificar($meses, 'balance_propio')['nombre']);
        $this->assertSame('No rentable', $servicio->clasificar($meses, 'balance_neto')['nombre']);
        $this->assertSame('Sin ventas', $servicio->clasificar([['ventas' => 0, 'balance_propio' => 50]], 'balance_propio')['nombre']);
        $this->assertNull($servicio->clasificar([], 'balance_propio')['porcentaje']);
    }

    public function test_umbrales_exactos_y_colores(): void
    {
        $servicio = new ClasificadorRentabilidadMensual;
        foreach ([[7, 10, 'Buena', 'primary'], [9, 20, 'Para rescatar', 'warning'], [8, 20, 'No rentable', 'danger'], [1, 1, 'Rentable', 'success']] as [$positivos, $total, $nombre, $color]) {
            $meses = array_map(fn (int $mes): array => ['ventas' => 1, 'balance_neto' => $mes <= $positivos ? 1 : -1], range(1, $total));
            $clasificacion = $servicio->clasificar($meses, 'balance_neto');
            $this->assertSame($nombre, $clasificacion['nombre']);
            $this->assertSame($color, $clasificacion['color']);
        }
    }
}
