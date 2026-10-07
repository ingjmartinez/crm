<?php

namespace App\Exports;

use App\Models\CoordinadorOperador;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AgenciasAsignadasCoordinadorExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles
{
    public function __construct(private readonly CoordinadorOperador $coordinador) {}

    /** @return Collection<int, array<int, string>> */
    public function collection(): Collection
    {
        return $this->coordinador->agencias
            ->sortBy('terminal', SORT_NATURAL)
            ->map(fn ($agencia): array => [
                (string) ($agencia->terminal ?? ''),
                (string) ($agencia->nombre_agencia ?: $agencia->agencia ?: 'Sin nombre'),
            ])->values();
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['Terminal', 'Agencia'];
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return ['A' => NumberFormat::FORMAT_TEXT];
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        $sheet->freezePane('A2');

        return [1 => ['font' => ['bold' => true]]];
    }
}
