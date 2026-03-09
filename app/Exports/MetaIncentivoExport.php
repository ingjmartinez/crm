<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MetaIncentivoExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(private Collection $rows)
    {
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Agencia ID',
            'Nombre Agencia',
            'Coordinador',
            'Tipo',
            'Nivel',
            'Incremetal',
            'Meta Incremental',
            'BaseT',
            'BaseAjustada',
            'Total Venta Mes Posterior',
        ];
    }

    public function map($row): array
    {
        return [
            (string) ($row->agencia_id ?? ''),
            (string) ($row->nombre_agencia ?? ''),
            (string) ($row->coordinador ?? ''),
            (string) ($row->tipo ?? ''),
            (string) ($row->nivel ?? ''),
            (float) ($row->incremetal ?? 0),
            (float) ($row->meta_incremental ?? 0),
            (float) ($row->ventas_3_meses ?? 0),
            (float) ($row->promedio_3_meses ?? 0),
            (float) ($row->total_venta_mes_posterior ?? 0),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
