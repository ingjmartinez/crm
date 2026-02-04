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
            'agencia'        => isset($row['agencia']) ? (string) $row['agencia'] : (isset($row['Agencia']) ? (string) $row['Agencia'] : null),
            'terminal'       => isset($row['terminal']) ? (string) $row['terminal'] : (isset($row['Terminal']) ? (string) $row['Terminal'] : null),
            'nombre_agencia' => isset($row['nombre_agencia']) ? (string) $row['nombre_agencia'] : (isset($row['Nombre Agencia']) ? (string) $row['Nombre Agencia'] : (isset($row['nombre agencia']) ? (string) $row['nombre agencia'] : null)),
            'sistema'        => isset($row['sistema']) ? (string) $row['sistema'] : (isset($row['Sistema']) ? (string) $row['Sistema'] : null),
            'ciudad'         => isset($row['ciudad']) ? (string) $row['ciudad'] : (isset($row['Ciudad']) ? (string) $row['Ciudad'] : null),
            'ruta'           => isset($row['ruta']) ? (string) $row['ruta'] : (isset($row['Ruta']) ? (string) $row['Ruta'] : null),
            'operador'       => isset($row['operador']) ? (string) $row['operador'] : (isset($row['Operador']) ? (string) $row['Operador'] : null),
            'coordinador'    => isset($row['coordinador']) ? (string) $row['coordinador'] : (isset($row['Coordinador']) ? (string) $row['Coordinador'] : null),
        ]);
    }

    public function rules(): array
    {
        return [
            'agencia' => 'required',
            'terminal' => 'nullable',
            'nombre_agencia' => 'nullable',
            'sistema' => 'nullable',
            'ciudad' => 'nullable',
            'ruta' => 'nullable',
            'operador' => 'nullable',
            'coordinador' => 'nullable',
        ];
    }
}
