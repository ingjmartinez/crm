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

class NovedadesHorarioExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithColumnFormatting
{
    public function __construct(
        private Collection $rows,
        private float $horasRequeridas,
        private float $valorHora
    ) {
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Terminal',
            'Nombre de Agencia',
            'Ruta',
            'Nombre de Empleado',
            'Cedula',
            'Fecha',
            'Primer Login',
            'Ultimo Login',
            'Horas Acumuladas',
            'Detalle',
            'Horas Faltantes',
            'Monto Falta',
        ];
    }

    public function map($row): array
    {
        $horasAcumuladas = round((float) ($row->horas_acumuladas ?? 0), 2);
        $horasFaltantes = round(max($this->horasRequeridas - $horasAcumuladas, 0), 2);
        $montoFalta = round($horasFaltantes * $this->valorHora, 2);

        return [
            (string) ($row->terminal ?? ''),
            (string) ($row->nombre_agencia ?? ''),
            (string) ($row->ruta ?? ''),
            (string) ($row->nombre_empleado ?? ''),
            (string) ($row->cedula ?? ''),
            (string) ($row->fecha ?? ''),
            (string) ($row->primer_login ?? ''),
            (string) ($row->ultimo_login ?? ''),
            number_format($horasAcumuladas, 2, '.', ''),
            $horasFaltantes > 0 ? 'Tiene falta' : 'Cumple',
            number_format($horasFaltantes, 2, '.', ''),
            number_format($montoFalta, 2, '.', ''),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
