<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Canvas;
use Dompdf\FontMetrics;
use Illuminate\Support\Collection;

class RentabilidadAgenciaV11Pdf
{
    public const ANCHO_AGENCIA = 0.13;

    public const MARGEN_PAGINA = 12;

    public const PESOS_COLUMNAS = ['ventas' => 1.25, 'ingresos_calculados' => 1.25, 'premios_sacados' => 1.15, 'utilidad' => 1.15, 'gastos' => 1.15, 'balance_general' => 1.15];

    public const MARGEN_CELDA = 2.5;

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
        $this->ancho = $this->canvas->get_width() - self::MARGEN_PAGINA * 2;
        $this->altoFila = max(102, ceil($this->tamano * 4.5 + 12));
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
                        $this->fila($fila, $columnas, $y);
                        $y += $this->altoFila;
                    }
                    if ($indice === $paginas->count() - 1) {
                        $this->fila($this->totales($filas), $columnas, $y, true);
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
        $izquierda = self::MARGEN_PAGINA;
        $this->texto('Rentabilidad mensual de agencias V11', $izquierda, 18, 18, $this->ancho);
        $this->texto('Empresa: '.($datos['filtros']['empresa'] ?: 'Todas').' | Año: '.$datos['filtros']['anio'].' | Hoja: '.($datos['tamanoHoja'] === 'legal' ? 'Legal (8.5 x 14)' : 'Carta (8.5 x 11)'), $izquierda, 46, 9, $this->ancho);
        $this->texto('Importes en RD$, redondeados. Premios = ventas tradicionales por el porcentaje global. Utilidad = ventas tradicionales - premios. Neto = Utilidad - Gastos.', $izquierda, 63, 7.5, $this->ancho);
        $this->canvas->filled_rectangle($izquierda, 80, $this->ancho, 19, [0.13, 0.22, 0.32]);
        $this->texto($ruta, $izquierda + 5, 83, 11, $this->ancho - 10, [1, 1, 1]);
        $anchoAgencia = $this->ancho * self::ANCHO_AGENCIA;
        $this->caja($izquierda, 104, $anchoAgencia, 42, self::FONDO);
        $this->lineas($datos['filtros']['agrupacion'] === 'ruta' ? 'Ruta / Empresa' : 'Terminal / Agencia', $izquierda + 3, 112, 9, $anchoAgencia - 6, 2);
        $campos = ['ventas' => 'Ventas', 'ingresos_calculados' => 'Ingresos', 'premios_sacados' => 'Premios', 'utilidad' => 'Utilidad', 'gastos' => 'Gastos', 'balance_general' => 'Neto'];
        $anchoMes = $this->ancho * (1 - self::ANCHO_AGENCIA) / count($bloque);
        $columnas = [];
        $x = $izquierda + $anchoAgencia;
        foreach ($bloque as $indice => $mes) {
            $this->caja($x, 104, $anchoMes, 18, self::FONDO);
            $this->texto($mes['nombre'], $x + 3, 106, 10, $anchoMes - 6, centrado: true);
            foreach ($campos as $campo => $etiqueta) {
                $ancho = $anchoMes * self::PESOS_COLUMNAS[$campo] / array_sum(self::PESOS_COLUMNAS);
                $this->caja($x, 122, $ancho, 24, self::FONDO);
                $this->lineas($etiqueta, $x + 2, 125, min(11, max(8, $this->tamano)), $ancho - 4, 1, true);
                $columnas[] = ['campo' => $campo, 'indice' => $indice, 'mes' => $mes['codigo'], 'x' => $x, 'ancho' => $ancho];
                $x += $ancho;
            }
        }

        return $columnas;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  list<array{campo: string, indice: int, mes: string, x: float, ancho: float}>  $columnas
     */
    private function fila(array $fila, array $columnas, float $y, bool $total = false): void
    {
        $tamanoImportes = min(24, $this->tamano * 1.4, ($this->altoFila / 3 - 4) / 1.2);
        $anchoAgencia = $this->ancho * self::ANCHO_AGENCIA;
        $fondo = $total ? self::FONDO : [1, 1, 1];
        $izquierda = self::MARGEN_PAGINA;
        $this->caja($izquierda, $y, $anchoAgencia, $this->altoFila, $fondo);
        $lineasNombre = $this->lineas($fila['nombre'], $izquierda + 4, $y + 7, 9.5, $anchoAgencia - 8, 3);
        $this->texto($fila['terminal'] === '' ? ($fila['empresa'] ?? '') : 'Terminal: '.$fila['terminal'], $izquierda + 4, $y + 10 + $lineasNombre * 11.5, 7, $anchoAgencia - 8, [0.40, 0.45, 0.51], negrita: false);
        if (isset($fila['clasificacion'])) {
            $clasificacion = $fila['clasificacion'];
            $posicion = $y + 23 + $lineasNombre * 11.5;
            $fondoEtiqueta = array_map(fn (int $canal): float => $canal / 255, sscanf($clasificacion['fondo'], '#%02x%02x%02x'));
            $colorEtiqueta = array_map(fn (int $canal): float => $canal / 255, sscanf($clasificacion['color'], '#%02x%02x%02x'));
            $this->canvas->filled_rectangle($izquierda + 4, $posicion, $anchoAgencia - 8, 11, $fondoEtiqueta);
            $this->texto($clasificacion['etiqueta'], $izquierda + 6, $posicion + 1, 7.5, $anchoAgencia - 12, $colorEtiqueta);
            $margen = $clasificacion['margen'] === null ? '—' : number_format($clasificacion['margen'], 2).'%';
            $this->texto('Margen estimado: '.$margen.' | '.$clasificacion['meses_ventas'].' meses', $izquierda + 4, $posicion + 14, 6.5, $anchoAgencia - 8);
            $posicion += 24;
            if ($clasificacion['provisional']) {
                $this->texto('Evaluación provisional', $izquierda + 4, $posicion, 6.5, $anchoAgencia - 8);
                $posicion += 8;
            }
            if ($clasificacion['deterioro']) {
                $this->texto('En deterioro', $izquierda + 4, $posicion, 6.5, $anchoAgencia - 8, [0.52, 0.13, 0.16]);
            }
        }
        foreach ($columnas as $columna) {
            ['campo' => $campo, 'indice' => $indice, 'mes' => $mes, 'x' => $x, 'ancho' => $ancho] = $columna;
            $periodo = $fila['periodos'][$indice];
            $this->caja($x, $y, $ancho, $this->altoFila, $fondo);
            $margen = $this->margen;
            if (in_array($campo, ['ventas', 'ingresos_calculados'], true)) {
                $prefijo = $campo === 'ventas' ? 'ventas_' : 'ingresos_';
                foreach (['tradicionales', 'no_tradicionales', 'recargas'] as $linea => $producto) {
                    $valor = $periodo[$prefijo.$producto];
                    $this->texto($valor === null ? '—' : number_format($valor, 0), $x + $margen, $y + ($linea + 0.5) * $this->altoFila / 3 - $tamanoImportes / 2, $tamanoImportes, $ancho - $margen * 2, self::TEXTO, true);
                }
            } elseif ($campo === 'premios_sacados') {
                $porcentaje = $periodo['porcentaje_dev_g'];
                $posicionY = $y + $this->altoFila / 2 - $tamanoImportes - 3;
                $textoPorcentaje = $porcentaje === null ? '—' : number_format($porcentaje, 2).'%';
                $this->texto($periodo['premios_sacados'] === null ? '—' : number_format($periodo['premios_sacados'], 0), $x + $margen, $posicionY, $tamanoImportes, $ancho - $margen * 2, self::TEXTO, true);
                $anchoPorcentaje = min($ancho - $margen * 2, $this->metricas->getTextWidth($textoPorcentaje, $this->negrita, $tamanoImportes * 0.8) + 4);
                $posicionPorcentajeX = $x + $ancho - $margen - $anchoPorcentaje;
                $posicionPorcentajeY = $posicionY + $tamanoImportes + 4;
                $this->canvas->filled_rectangle($posicionPorcentajeX, $posicionPorcentajeY - 1, $anchoPorcentaje, max(9, $tamanoImportes), [1, 0.95, 0.8]);
                $this->texto($textoPorcentaje, $posicionPorcentajeX + 2, $posicionPorcentajeY, $tamanoImportes * 0.8, $anchoPorcentaje - 4, centrado: true);
            } else {
                $valor = $periodo[$campo];
                $color = $campo === 'utilidad' ? self::AZUL : ($campo === 'balance_general' && $valor != 0 ? ($valor > 0 ? [0.09, 0.44, 0.28] : [0.70, 0.15, 0.21]) : self::TEXTO);
                $this->texto($valor === null ? '—' : number_format($valor, 0), $x + $margen, $y + ($this->altoFila - $tamanoImportes) / 2, $tamanoImportes, $ancho - $margen * 2, $color, true);
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
            foreach (['ventas_tradicionales', 'ventas_no_tradicionales', 'ventas_recargas', 'ingresos_tradicionales', 'ingresos_no_tradicionales', 'ingresos_recargas', 'premios_sacados', 'utilidad', 'gastos', 'balance_general'] as $campo) {
                $total[$campo] = $filas->contains(fn (array $fila): bool => $fila['periodos'][$indice][$campo] === null)
                    ? null : round($filas->sum(fn (array $fila): float => (float) ($fila['periodos'][$indice][$campo] ?? 0)), 2);
            }
            $total['porcentaje_dev_g'] = $total['premios_sacados'] === null || $total['ventas_tradicionales'] == 0 ? null : $total['premios_sacados'] / $total['ventas_tradicionales'] * 100;
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
