<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MonitoreoTerminalEstadoExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles
{
    /** @param array<int, array<string, mixed>> $registros */
    public function __construct(private readonly array $registros) {}

    public function collection(): Collection
    {
        return collect($this->registros)->map(fn (array $registro): array => [
            $this->textoSeguro($registro['terminal'] ?? ''),
            $this->textoSeguro($registro['agencia'] ?? ''),
            $this->textoSeguro($registro['coordinador'] ?? ''),
            $registro['fecha'] ?? '',
            $registro['hora_apertura'] ?? '',
            ($registro['hora_ponche'] ?? null) ?: 'Sin ponche',
            $registro['minutos_tardanza'] ?? '',
            $registro['estado'] ?? '',
        ]);
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            'Terminal',
            'Agencia',
            'Coordinador',
            'Fecha',
            'Hora de apertura',
            'Hora de ponche',
            'Minutos de tardanza',
            'Estado',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => 'solid',
                    'startColor' => ['rgb' => '405189'],
                ],
            ],
        ];
    }

    private function textoSeguro(mixed $valor): string
    {
        $texto = (string) $valor;

        return preg_match('/^[=+\-@]/', $texto) === 1 ? "'".$texto : $texto;
    }
}
