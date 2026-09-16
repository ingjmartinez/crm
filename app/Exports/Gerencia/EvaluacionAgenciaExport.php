<?php

namespace App\Exports\Gerencia;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class EvaluacionAgenciaExport implements WithMultipleSheets
{
    public function __construct(
        private readonly Collection $meses,
        private readonly Collection $filas,
        private readonly float $meta,
        private readonly string $empresa,
        private readonly array $productos,
    ) {}

    public function sheets(): array
    {
        $resumen = [
            ['Concepto', 'Valor'],
            ['Empresa', $this->empresa !== '' ? $this->empresa : 'Todas'],
            ['Productos', collect($this->productos)->map(fn (string $producto): string => match ($producto) {
                'tradicional' => 'Tradicional',
                'no_tradicional' => 'No tradicional',
                'recargas' => 'Recargas',
            })->implode(', ')],
            ['Meta mensual', $this->meta],
            ['Meses evaluados', $this->meses->count()],
            ['Terminales evaluadas', $this->filas->count()],
            ['Terminales que cumplen', $this->filas->where('cumple', true)->count()],
            ['Terminales que no cumplen', $this->filas->where('cumple', false)->count()],
        ];

        $encabezados = ['Terminal', 'Agencia'];
        foreach ($this->meses as $mes) {
            $encabezados[] = $mes['etiqueta'].' - Venta';
            $encabezados[] = $mes['etiqueta'].' - Cumple';
        }
        $encabezados = [...$encabezados, 'Meses que cumple', 'Meses que no cumple', 'Estado'];

        $detalle = [$encabezados];
        foreach ($this->filas as $fila) {
            $registro = [$fila['terminal'], $fila['agencia']];
            foreach ($this->meses as $mes) {
                $venta = $fila['ventas'][$mes['clave']];
                $registro[] = $venta['venta'];
                $registro[] = $venta['cumple'] ? 'Sí' : 'No';
            }
            $detalle[] = [
                ...$registro,
                $fila['meses_cumple'],
                $fila['meses_no_cumple'],
                $fila['cumple'] ? 'Cumple' : 'No cumple',
            ];
        }

        return [
            new class($resumen) implements FromArray, ShouldAutoSize, WithTitle
            {
                public function __construct(private readonly array $filas) {}

                public function array(): array
                {
                    return $this->filas;
                }

                public function title(): string
                {
                    return 'Resumen';
                }
            },
            new class($detalle) implements FromArray, ShouldAutoSize, WithTitle
            {
                public function __construct(private readonly array $filas) {}

                public function array(): array
                {
                    return $this->filas;
                }

                public function title(): string
                {
                    return 'Detalle';
                }
            },
        ];
    }
}
