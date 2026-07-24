<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SeguimientoAgenciaExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $filas) {}

    public function collection(): Collection
    {
        return $this->filas;
    }

    public function headings(): array
    {
        return ['Empresa', 'Ciudad', 'Coordinador', 'Ruta', 'Agencia', 'Terminal', 'Sistema', 'Producto', 'Meta diaria', 'Meta acumulada', 'Venta', 'Cumplimiento %', 'Brecha', 'Promedio diario', 'Proyección mensual', 'Días cumple', 'Días no cumple', 'Estado'];
    }

    /** @param array<string, mixed> $fila */
    public function map($fila): array
    {
        return [
            $fila['empresa'], $fila['ciudad'], $fila['coordinador'], $fila['ruta'], $fila['agencia'],
            $fila['terminal'], $fila['sistema'], $fila['producto'], $fila['meta_diaria'],
            $fila['meta_acumulada'], $fila['venta'], round($fila['cumplimiento'], 2), $fila['brecha'],
            $fila['promedio_diario'], $fila['proyeccion'], $fila['dias_cumplidos'],
            $fila['dias_no_cumplidos'], $fila['estado'],
        ];
    }
}
