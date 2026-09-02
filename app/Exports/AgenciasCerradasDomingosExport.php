<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AgenciasCerradasDomingosExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithMapping, WithStyles
{
    /** @param Collection<int, array<string, mixed>> $filas */
    public function __construct(private readonly Collection $filas) {}

    public function collection(): Collection
    {
        return $this->filas;
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            'Fecha',
            'Terminal',
            'Nombre de la agencia',
            'Empresa',
            'Ciudad',
            'Ruta',
            'Coordinador',
            'Movimientos de venta',
            'Monto de ventas',
            'Ponches',
        ];
    }

    /** @param array<string, mixed> $fila */
    public function map($fila): array
    {
        return [
            Date::dateTimeToExcel(Carbon::parse($fila['fecha'])),
            $this->textoSeguro($fila['terminal']),
            $this->textoSeguro($fila['agencia']),
            $this->textoSeguro($fila['empresa']),
            $this->textoSeguro($fila['ciudad']),
            $this->textoSeguro($fila['ruta']),
            $this->textoSeguro($fila['coordinador']),
            (int) $fila['movimientos_venta'],
            (float) $fila['monto_ventas'],
            (int) $fila['ponches'],
        ];
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return [
            'A' => 'dd/mm/yyyy',
            'B' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_NUMBER,
            'I' => '#,##0.00',
            'J' => NumberFormat::FORMAT_NUMBER,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('C2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '405189'],
                ],
            ],
        ];
    }

    private function textoSeguro(mixed $valor): string
    {
        $texto = (string) $valor;

        return preg_match('/^[=+\-@]/', $texto) === 1 ? "'{$texto}" : $texto;
    }
}
