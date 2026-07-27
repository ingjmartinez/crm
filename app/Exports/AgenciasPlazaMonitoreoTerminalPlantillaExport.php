<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AgenciasPlazaMonitoreoTerminalPlantillaExport implements FromArray, ShouldAutoSize, WithStyles
{
    /** @return array<int, array<int, string>> */
    public function array(): array
    {
        return [
            ['Terminal'],
        ];
    }

    /** @return array<int, array<string, array<string, bool>>> */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
