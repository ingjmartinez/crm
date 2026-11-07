<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Canvas;
use Dompdf\FontMetrics;
use Illuminate\Support\Collection;

class RentabilidadAgenciaV10Pdf
{
    public const ANCHO_AGENCIA = 0.13;

    public const PESOS_COLUMNAS = ['ventas' => 1.25, 'ingresos_calculados' => 1.25, 'premios_sacados' => 2.5, 'utilidad' => 1.15, 'gastos' => 1.15, 'balance_general' => 1.15];

    public const MARGEN_CELDA = 5;

    private const TEXTO = [0.14, 0.20, 0.28];

    private const AZUL = [0.05, 0.43, 0.99];

    private const BORDE = [0.84, 0.88, 0.92];

    private const FONDO = [0.93, 0.95, 0.97];

    private Canvas $canvas;

    private FontMetrics $metricas;

    private string $fuente;

    private string $negrita;

    private float $tamano;

    private float $ancho;

    private float $altoFila;

    private float $margen;

    public static function margenCelda(int $cantidadMeses): float
    {
        return self::MARGEN_CELDA * min(1, 3 / max(1, $cantidadMeses));
    }

    /** @param array<string, mixed> $datos */
    public function generar(array $datos): string
    {
        $pdf = Pdf::loadHTML('<html><body></body></html>')->setPaper($datos['tamanoHoja'], 'landscape');
        $pdf->render();
        $this->canvas = $pdf->getDomPDF()->getCanvas();
        $this->metricas = $pdf->getDomPDF()->getFontMetrics();
        $this->fuente = $this->metricas->getFont('DejaVu Sans');
        $this->negrita = $this->metricas->getFont('DejaVu Sans', 'bold');
        $this->tamano = $datos['tamanoFuenteImportes'];
        $this->margen = self::margenCelda(max(array_map('count', $datos['bloquesMeses'])));
        $this->ancho = $this->canvas->get_width() - 48;
        $this->altoFila = max(56, ceil($this->tamano * 6 + 6));
        $capacidad = max(1, (int) floor(($this->canvas->get_height() - 181) / $this->altoFila) - 1);
        $primera = true;
        foreach ($datos['grupos'] as $ruta => $filas) {
            foreach ($datos['bloquesMeses'] as $bloque) {
                $paginas = $filas->chunk($capacidad);
                foreach ($paginas as $indice => $pagina) {
                    if (! $primera) {
                        $this->canvas->new_page();
                    }
                    $primera = false;
                    $columnas = $this->encabezado($datos, (string) $ruta, $bloque);
                    $y = 146.0;
                    foreach ($pagina as $fila) {
                        $this->fila($fila, $columnas, $y, $datos['referenciasGlobales']);
                        $y += $this->altoFila;
                    }
                    if ($indice === $paginas->count() - 1) {
                        $this->fila($this->totales($filas), $columnas, $y, $datos['referenciasGlobales'], true);
                    }
                }
            }
        }
        $this->canvas->page_text($this->canvas->get_width() - 150, $this->canvas->get_height() - 24, 'Página {PAGE_NUM} de {PAGE_COUNT}', $this->fuente, 8, [0.4, 0.45, 0.5]);

        return $pdf->output();
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return list<array{campo: string, indice: int, mes: string, x: float, ancho: float}>
     */
    private function encabezado(array $datos, string $ruta, array $bloque): array
    {
        $this->texto('Rentabilidad mensual de agencias V10', 24, 26, 15, $this->ancho);
        $this->texto('Empresa: '.($datos['filtros']['empresa'] ?: 'Todas').' | Año: '.$datos['filtros']['anio'].' | Hoja: '.($datos['tamanoHoja'] === 'legal' ? 'Legal (8.5 x 14)' : 'Carta (8.5 x 11)'), 24, 50, 7, $this->ancho);
        $this->texto('Importes en RD$, redondeados. Utilidad = ventas tradicionales - premios tradicionales. Resultado Bruto = Utilidad - Gastos.', 24, 64, 6.5, $this->ancho);
        $this->canvas->filled_rectangle(24, 80, $this->ancho, 19, [0.13, 0.22, 0.32]);
        $this->texto($ruta, 29, 84, 9, $this->ancho - 10, [1, 1, 1]);
        $anchoAgencia = $this->ancho * self::ANCHO_AGENCIA;
        $this->caja(24, 104, $anchoAgencia, 42, self::FONDO);
        $this->texto($datos['filtros']['agrupacion'] === 'ruta' ? 'Ruta / Empresa' : 'Terminal / Agencia', 27, 117, 7, $anchoAgencia - 6);
        $campos = ['ventas' => 'Ventas', 'ingresos_calculados' => 'Ingresos calculado', 'premios_sacados' => 'Premios sacados', 'utilidad' => 'Utilidad', 'gastos' => 'Gastos', 'balance_general' => 'Resultado Bruto'];
        $anchoMes = $this->ancho * (1 - self::ANCHO_AGENCIA) / count($bloque);
        $columnas = [];
        $x = 24 + $anchoAgencia;
        foreach ($bloque as $indice => $mes) {
            $this->caja($x, 104, $anchoMes, 18, self::FONDO);
            $this->texto($mes['nombre'], $x + 3, 108, 7, $anchoMes - 6, centrado: true);
            foreach ($campos as $campo => $etiqueta) {
                $ancho = $anchoMes * self::PESOS_COLUMNAS[$campo] / array_sum(self::PESOS_COLUMNAS);
                $this->caja($x, 122, $ancho, 24, self::FONDO);
                $this->lineas($etiqueta, $x + 3, 125, min(7, $this->tamano), $ancho - 6, 2, true);
                $columnas[] = ['campo' => $campo, 'indice' => $indice, 'mes' => $mes['codigo'], 'x' => $x, 'ancho' => $ancho];
                $x += $ancho;
            }
        }

        return $columnas;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  list<array{campo: string, indice: int, mes: string, x: float, ancho: float}>  $columnas
     * @param  array<string, mixed>  $referencias
     */
    private function fila(array $fila, array $columnas, float $y, array $referencias, bool $total = false): void
    {
        $anchoAgencia = $this->ancho * self::ANCHO_AGENCIA;
        $fondo = $total ? self::FONDO : [1, 1, 1];
        $this->caja(24, $y, $anchoAgencia, $this->altoFila, $fondo);
        $lineasNombre = $this->lineas($fila['nombre'], 29, $y + 9, 7.5, $anchoAgencia - 10, 3);
        $this->texto($fila['terminal'] === '' ? ($fila['empresa'] ?? '') : 'Terminal: '.$fila['terminal'], 29, $y + 12 + $lineasNombre * 9.5, 5.5, $anchoAgencia - 10, [0.40, 0.45, 0.51], negrita: false);
        foreach ($columnas as $columna) {
            ['campo' => $campo, 'indice' => $indice, 'mes' => $mes, 'x' => $x, 'ancho' => $ancho] = $columna;
            $periodo = $fila['periodos'][$indice];
            $this->caja($x, $y, $ancho, $this->altoFila, $fondo);
            $margen = $this->margen;
            if (in_array($campo, ['ventas', 'ingresos_calculados'], true)) {
                $prefijo = $campo === 'ventas' ? 'ventas_' : 'ingresos_';
                foreach (['tradicionales' => 'Tra', 'no_tradicionales' => 'Notra', 'recargas' => 'Rec'] as $producto => $etiqueta) {
                    $linea = array_search($producto, ['tradicionales', 'no_tradicionales', 'recargas'], true);
                    $valor = $periodo[$prefijo.$producto];
                    $paso = ($this->altoFila - 6) / 3;
                    $this->texto($etiqueta, $x + $margen, $y + 4 + $linea * $paso, $this->tamano * 0.7, $ancho - $margen * 2, [0.40, 0.45, 0.51], negrita: false);
                    $this->texto($valor === null ? 'Sin porcentaje' : number_format($valor, 0), $x + $margen, $y + 11 + $linea * $paso, $this->tamano, $ancho - $margen * 2, self::TEXTO, true);
                }
            } elseif ($campo === 'premios_sacados') {
                $referencia = $total ? ($referencias['total']['meses'][$mes] ?? []) : ($referencias['empresas'][$fila['empresa']]['meses'][$mes] ?? []);
                $ventas = $periodo['ventas_tradicionales'];
                $porcentaje = $referencia['premios_sacados'] ?? null;
                $lineas = [
                    ['Dev T', $ventas == 0 ? null : $periodo['premios_sacados'] / $ventas * 100, $periodo['premios_sacados'], self::AZUL],
                    ['Dev G', $porcentaje, $porcentaje === null ? null : $ventas * $porcentaje / 100, self::TEXTO],
                    ['Dev Proy', 63, $periodo['premios_sacados_2'], [0, 0, 0]],
                ];
                foreach ($lineas as $numero => [$etiqueta, $porcentaje, $importe, $color]) {
                    $posicionY = $y + ($numero + 0.5) * ($this->altoFila / 3) - $this->tamano / 2;
                    $textoPorcentaje = $porcentaje === null ? '—' : number_format($porcentaje, $etiqueta === 'Dev Proy' ? 0 : 2).'%';
                    if ($numero > 0) {
                        $this->canvas->filled_rectangle($x + $margen, $posicionY - 1, $ancho * 0.5 - $margen - 2, max(9, $this->tamano), [1, 0.95, 0.8]);
                    }
                    $this->texto($etiqueta.' '.$textoPorcentaje, $x + $margen + 1, $posicionY, $this->tamano * 0.72, $ancho * 0.5 - $margen - 3, $numero === 0 ? self::AZUL : self::TEXTO);
                    $this->texto($importe === null ? '—' : number_format($importe, 0), $x + $ancho * 0.5, $posicionY, $this->tamano, $ancho * 0.5 - $margen, $color, true);
                }
            } else {
                $valor = $periodo[$campo];
                $color = $campo === 'utilidad' ? self::AZUL : ($campo === 'balance_general' && $valor != 0 ? ($valor > 0 ? [0.09, 0.44, 0.28] : [0.70, 0.15, 0.21]) : self::TEXTO);
                $this->texto(number_format($valor, 0), $x + $margen, $y + ($this->altoFila - $this->tamano) / 2, $this->tamano, $ancho - $margen * 2, $color, true);
            }
            if ($campo === 'ventas') {
                $this->canvas->line($x, $y, $x, $y + $this->altoFila, [0.59, 0.66, 0.74], 1);
            }
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    private function totales(Collection $filas): array
    {
        $periodos = [];
        foreach ($filas->first()['periodos'] as $indice => $periodo) {
            $total = ['mes' => $periodo['mes']];
            foreach (['ventas_tradicionales', 'ventas_no_tradicionales', 'ventas_recargas', 'ingresos_tradicionales', 'ingresos_no_tradicionales', 'ingresos_recargas', 'premios_sacados', 'premios_sacados_2', 'utilidad', 'gastos', 'balance_general'] as $campo) {
                $total[$campo] = str_starts_with($campo, 'ingresos_') && $filas->contains(fn (array $fila): bool => $fila['periodos'][$indice][$campo] === null)
                    ? null : round($filas->sum(fn (array $fila): float => (float) ($fila['periodos'][$indice][$campo] ?? 0)), 2);
            }
            $periodos[$indice] = $total;
        }

        return ['nombre' => 'Totales', 'terminal' => '', 'empresa' => '', 'periodos' => $periodos];
    }

    /** @param list<float> $color */
    private function caja(float $x, float $y, float $ancho, float $alto, array $color): void
    {
        $this->canvas->filled_rectangle($x, $y, $ancho, $alto, $color);
        $this->canvas->rectangle($x, $y, $ancho, $alto, self::BORDE, 0.5);
    }

    /** @param list<float> $color */
    private function texto(string $texto, float $x, float $y, float $tamano, float $ancho, array $color = self::TEXTO, bool $derecha = false, bool $centrado = false, bool $negrita = true): void
    {
        $fuente = $negrita ? $this->negrita : $this->fuente;
        $medida = $this->metricas->getTextWidth($texto, $fuente, $tamano);
        if ($medida > $ancho) {
            $tamano *= $ancho / $medida;
            $medida = $ancho;
        }
        $posicionX = $centrado ? $x + ($ancho - $medida) / 2 : ($derecha ? $x + $ancho - $medida : $x);
        $this->canvas->text($posicionX, $y, $texto, $fuente, $tamano, $color);
    }

    private function lineas(string $texto, float $x, float $y, float $tamano, float $ancho, int $limite, bool $centrado = false): int
    {
        $lineas = [''];
        foreach (preg_split('/\s+/u', trim($texto)) as $palabra) {
            $indice = count($lineas) - 1;
            $candidata = trim($lineas[$indice].' '.$palabra);
            if ($lineas[$indice] !== '' && $this->metricas->getTextWidth($candidata, $this->negrita, $tamano) > $ancho && count($lineas) < $limite) {
                $lineas[] = $palabra;
            } else {
                $lineas[$indice] = $candidata;
            }
        }
        foreach ($lineas as $indice => $linea) {
            $this->texto($linea, $x, $y + $indice * ($tamano + 2), $tamano, $ancho, centrado: $centrado);
        }

        return count($lineas);
    }
}
