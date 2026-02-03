<?php

namespace App\Imports;

use App\Models\Agencia;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class AgenciasImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return new Agencia([
            'agencia'        => $row['agencia'] ?? $row['Agencia'] ?? null,
            'terminal'       => $row['terminal'] ?? $row['Terminal'] ?? null,
            'nombre_agencia' => $row['nombre_agencia'] ?? $row['Nombre Agencia'] ?? $row['nombre agencia'] ?? null,
            'sistema'        => $row['sistema'] ?? $row['Sistema'] ?? null,
            'ciudad'         => $row['ciudad'] ?? $row['Ciudad'] ?? null,
            'ruta'           => $row['ruta'] ?? $row['Ruta'] ?? null,
            'operador'       => $row['operador'] ?? $row['Operador'] ?? null,
            'coordinador'    => $row['coordinador'] ?? $row['Coordinador'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            'agencia' => 'required|string|max:255',
            'terminal' => 'nullable|string|max:255',
            'nombre_agencia' => 'nullable|string|max:255',
            'sistema' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:255',
            'ruta' => 'nullable|string|max:255',
            'operador' => 'nullable|string|max:255',
            'coordinador' => 'nullable|string|max:255',
        ];
    }
}
