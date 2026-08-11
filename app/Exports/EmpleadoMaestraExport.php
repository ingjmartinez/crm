<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmpleadoMaestraExport implements FromQuery, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private readonly Builder $employeesQuery) {}

    public function query(): Builder
    {
        return (clone $this->employeesQuery)
            ->select([
                'companyid',
                'empleadoid',
                'nombres',
                'apellidos',
                'cedula',
                'ciudad',
                'salariomensual',
                'fechaingreso',
                'fechasalida',
            ])
            ->orderBy('companyid')
            ->orderBy('empleadoid');
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            'Empresa',
            'ID Empleado',
            'Nombres',
            'Apellidos',
            'Cédula',
            'Ciudad',
            'Salario Mensual',
            'Fecha Ingreso',
            'Fecha Salida',
        ];
    }

    /** @return array<int, float|string|null> */
    public function map($employee): array
    {
        return [
            match ((string) $employee->companyid) {
                '168' => 'Grupo Joselito',
                '169' => 'Negosur',
                default => 'Sin empresa',
            },
            (string) $employee->empleadoid,
            $employee->nombres,
            $employee->apellidos,
            (string) $employee->cedula,
            $employee->ciudad,
            (float) str_replace(',', '', (string) $employee->salariomensual),
            $employee->fechaingreso,
            $employee->fechasalida,
        ];
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'G' => '#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
