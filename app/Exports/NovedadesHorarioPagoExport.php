<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class NovedadesHorarioPagoExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithColumnFormatting
{
    public function __construct(
        private Collection $rows
    ) {
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Nombre',
            'Cedula',
            'Terminal',
            'Nombre de Agencia',
            'Ruta',
            'Total de Horas',
            'Monto Total',
        ];
    }

    public function map($row): array
    {
        return [
            (string) ($row['nombre'] ?? ''),
            (string) ($row['cedula'] ?? ''),
            (string) ($row['terminal'] ?? ''),
            (string) ($row['nombre_agencia'] ?? ''),
            (string) ($row['ruta'] ?? ''),
            (string) ($row['total_horas'] ?? ''),
            number_format((float) ($row['monto_total'] ?? 0), 2, '.', ''),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_NUMBER_00,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
