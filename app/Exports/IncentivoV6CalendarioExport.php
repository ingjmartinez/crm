<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IncentivoV6CalendarioExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  Collection<int, string>  $dates
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly Collection $dates
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            'Sistema',
            'Terminal',
            'Agencia',
            'Empresa',
            ...$this->dates->map(fn (string $date): string => date('d/m/Y', strtotime($date)))->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $this->safeText($row['sistema']),
            $this->safeText($row['terminal']),
            $this->safeText($row['agencia']),
            $this->safeText($row['empresa']),
            ...$this->dates->map(
                fn (string $date): string => $this->paymentTypeLabel($row['tipos_por_fecha'][$date] ?? null)
            )->all(),
        ];
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('E2');
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

    private function paymentTypeLabel(?string $paymentType): string
    {
        return match ($paymentType) {
            'tramos_60' => 'Pago 60',
            'tramos_70' => 'Pago 70',
            'tramos_80' => 'Pago 80',
            default => 'General',
        };
    }

    private function safeText(mixed $value): string
    {
        $text = (string) $value;

        return preg_match('/^[=+\-@]/', $text) === 1 ? "'{$text}" : $text;
    }
}
