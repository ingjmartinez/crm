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

class CoordinadorOperadorExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles
{
    /**
     * @param  Collection<int, CoordinadorOperador>  $coordinadores
     */
    public function __construct(private readonly Collection $coordinadores) {}

    /**
     * @return Collection<int, array<int, int|string|null>>
     */
    public function collection()
    {
        return $this->coordinadores
            ->flatMap(function (CoordinadorOperador $coordinador): Collection {
                if ($coordinador->agencias->isEmpty()) {
                    return collect([$this->mapRow($coordinador)]);
                }

                return $coordinador->agencias->map(
                    fn ($agencia): array => $this->mapRow($coordinador, $agencia)
                );
            })
            ->values();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'ID Coordinador',
            'ID Empleado',
            'Nombre',
            'Apellido',
            'Correo',
            'Cédula',
            'Teléfono',
            'Puesto',
            'Terminal',
            'Agencia',
            'Nombre Agencia',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        $sheet->freezePane('A2');

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'F' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_TEXT,
            'I' => NumberFormat::FORMAT_TEXT,
        ];
    }

    /**
     * @return array<int, int|string|null>
     */
    private function mapRow(CoordinadorOperador $coordinador, mixed $agencia = null): array
    {
        return [
            $coordinador->id,
            $coordinador->empleado?->empleadoid,
            $coordinador->nombre,
            $coordinador->apellido,
            $coordinador->correo,
            $coordinador->cedula,
            $coordinador->telefono,
            $coordinador->puesto,
            $agencia?->terminal,
            $agencia?->agencia,
            $agencia?->nombre_agencia,
        ];
    }
}
