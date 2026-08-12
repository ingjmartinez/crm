<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MonitoreoAgenteVentaExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles
{
    /** @param array<int, array<string, mixed>> $registros */
    public function __construct(private readonly array $registros) {}

    public function collection(): Collection
    {
        return collect($this->registros)->map(fn (array $registro): array => [
            $registro['fecha'],
            $registro['sistema'],
            $this->textoSeguro($registro['cedula'] ?: 'Sin cédula'),
            $this->textoSeguro($registro['agente']),
            $registro['entrada'] ?: 'Sin entrada',
            $registro['salida'] ?: 'Sin salida',
            $registro['marca_validar'] ?? '',
            $registro['ultima_venta'] ?? '',
            $this->textoSeguro($registro['terminal']),
            $this->textoSeguro($registro['agencia']),
            $this->textoSeguro($registro['empresa']),
            $this->textoSeguro($registro['coordinador']),
            $registro['estado'],
            $this->textoSeguro($registro['observacion'] ?? ''),
        ]);
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['Fecha', 'Sistema', 'Cédula', 'Agente de venta', 'Entrada', 'Salida', 'Marca a validar', 'Última venta', 'Terminal', 'Agencia', 'Empresa', 'Coordinador', 'Estado', 'Observación'];
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '405189']],
            ],
        ];
    }

    private function textoSeguro(mixed $valor): string
    {
        $texto = (string) $valor;

        return preg_match('/^[=+\-@]/', $texto) === 1 ? "'".$texto : $texto;
    }
}
