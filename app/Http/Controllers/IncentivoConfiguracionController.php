<?php

namespace App\Http\Controllers;

use App\Models\IncentivoAdministrativo;
use App\Models\PorcentajeIncentivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class IncentivoConfiguracionController extends Controller
{
    private const GRUPO_SEGURIDAD = '6. Seguridad';
    private const GRUPOS_MONTO_FIJO = ['4. Operadores', '5. Servs. Tecnicos', self::GRUPO_SEGURIDAD];

    public function incentivoAdministrativoIndex(Request $request)
    {
        $buscarNombre = trim((string) $request->query('buscar_nombre', ''));
        $grupoFilter = trim((string) $request->query('grupo_filter', ''));
        $empresaFilter = trim((string) $request->query('empresa_filter', ''));
        $estatusFilter = trim((string) $request->query('estatus_filter', ''));

        $registros = $this->queryIncentivosAdministrativos($request)
            ->orderBy('grupo')
            ->orderBy('empresa')
            ->orderBy('nombre')
            ->paginate(30)
            ->withQueryString();

        $posiciones = PorcentajeIncentivo::query()
            ->orderBy('posicion')
            ->get(['posicion', 'bono_pct']);

        if (!$posiciones->contains('posicion', self::GRUPO_SEGURIDAD)) {
            $posiciones->push((object) [
                'posicion' => self::GRUPO_SEGURIDAD,
                'bono_pct' => 0,
            ]);
        }

        $empresas = collect(IncentivoAdministrativo::EMPRESAS_VALIDAS);

        return view('incentivos.incentivo_administrativo.index', compact('registros', 'posiciones', 'empresas', 'buscarNombre', 'grupoFilter', 'empresaFilter', 'estatusFilter'));
    }

    public function incentivoAdministrativoStore(Request $request)
    {
        $validated = $this->validateIncentivoAdministrativo($request, [
            'grupo' => ['required', 'string', 'max:70', $this->grupoAdministrativoRule()],
            'nombre' => [
                'required',
                'string',
                'max:120',
                Rule::unique('incentivo_administrativos', 'nombre')
                    ->where(fn ($query) => $query
                        ->where('grupo', $request->input('grupo'))
                        ->where('empresa', $request->input('empresa'))),
            ],
            'cedula' => ['nullable', 'string', 'regex:/^\d{11}$/'],
            'empresa' => ['required', 'string', 'max:50', Rule::in(IncentivoAdministrativo::EMPRESAS_VALIDAS)],
            'pct_total' => ['required', 'numeric', 'min:0', 'max:9999999'],
        ], [
            'nombre.unique' => 'Ya existe un colaborador registrado con ese grupo y empresa.',
        ]);

        IncentivoAdministrativo::create($validated);

        return redirect()
            ->route('incentivos.incentivo-administrativo.index')
            ->with('success', 'Registro creado correctamente.');
    }

    public function incentivoAdministrativoUpdate(Request $request, IncentivoAdministrativo $incentivoAdministrativo)
    {
        $validated = $this->validateIncentivoAdministrativo($request, [
            'grupo' => ['required', 'string', 'max:70', $this->grupoAdministrativoRule()],
            'nombre' => [
                'required',
                'string',
                'max:120',
                Rule::unique('incentivo_administrativos', 'nombre')
                    ->where(fn ($query) => $query
                        ->where('grupo', $request->input('grupo'))
                        ->where('empresa', $request->input('empresa')))
                    ->ignore($incentivoAdministrativo->id),
            ],
            'cedula' => ['nullable', 'string', 'regex:/^\d{11}$/'],
            'empresa' => ['required', 'string', 'max:50', Rule::in(IncentivoAdministrativo::EMPRESAS_VALIDAS)],
            'pct_total' => ['required', 'numeric', 'min:0', 'max:9999999'],
        ], [
            'nombre.unique' => 'Ya existe un colaborador registrado con ese grupo y empresa.',
        ]);

        $incentivoAdministrativo->update($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Registro actualizado correctamente.',
                'registro' => [
                    'id' => $incentivoAdministrativo->id,
                    'grupo' => $incentivoAdministrativo->grupo,
                    'nombre' => $incentivoAdministrativo->nombre,
                    'cedula' => $incentivoAdministrativo->cedula,
                    'empleadoid' => $this->findEmpleadoIdByCedula($incentivoAdministrativo->cedula),
                    'empresa' => $incentivoAdministrativo->empresa,
                    'pct_total' => number_format((float) $incentivoAdministrativo->pct_total, 2, '.', ''),
                ],
            ]);
        }

        return redirect()
            ->route('incentivos.incentivo-administrativo.index')
            ->with('success', 'Registro actualizado correctamente.');
    }

    public function incentivoAdministrativoExport(Request $request)
    {
        $rows = $this->queryIncentivosAdministrativos($request, true)
            ->orderBy('grupo')
            ->orderBy('empresa')
            ->orderBy('nombre')
            ->get();

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="incentivo_administrativo.xls"',
        ];

        return response()->stream(function () use ($rows) {
            echo "\xEF\xBB\xBF";
            echo '<html><head><meta charset="UTF-8"></head><body>';
            echo '<table border="1">';
            echo '<thead><tr>';
            foreach (['Grupo', 'Nombre', 'Cedula', 'IdEmpleado', 'Empresa', '% Total / Monto fijo'] as $heading) {
                echo '<th>' . e($heading) . '</th>';
            }
            echo '</tr></thead><tbody>';

            foreach ($rows as $row) {
                echo '<tr>';
                echo '<td>' . e($row->grupo) . '</td>';
                echo '<td>' . e($row->nombre) . '</td>';
                echo '<td style="mso-number-format:\'@\';">' . e((string) $row->cedula) . '</td>';
                echo '<td style="mso-number-format:\'@\';">' . e((string) $row->empleadoid) . '</td>';
                echo '<td>' . e($row->empresa) . '</td>';
                echo '<td>' . e(number_format((float) $row->pct_total, 2, '.', '')) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table></body></html>';
        }, 200, $headers);
    }

    public function incentivoAdministrativoDestroy(Request $request, IncentivoAdministrativo $incentivoAdministrativo)
    {
        $incentivoAdministrativo->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Registro eliminado correctamente.']);
        }

        return redirect()
            ->route('incentivos.incentivo-administrativo.index')
            ->with('success', 'Registro eliminado correctamente.');
    }

    private function queryIncentivosAdministrativos(Request $request, bool $withEmpleadoId = false)
    {
        $buscarNombre = trim((string) $request->query('buscar_nombre', ''));
        $grupoFilter = trim((string) $request->query('grupo_filter', ''));
        $empresaFilter = trim((string) $request->query('empresa_filter', ''));
        $estatusFilter = trim((string) $request->query('estatus_filter', ''));

        $query = IncentivoAdministrativo::query()
            ->select('incentivo_administrativos.*')
            ->when($buscarNombre !== '', function ($query) use ($buscarNombre) {
                $query->where('nombre', 'like', '%' . $buscarNombre . '%');
            })
            ->when($grupoFilter !== '', function ($query) use ($grupoFilter) {
                $query->where('grupo', $grupoFilter);
            })
            ->when($empresaFilter !== '', function ($query) use ($empresaFilter) {
                $query->where('empresa', $empresaFilter);
            });

        $hasActivo = Schema::hasColumn('empleados', 'activo');
        $hasFechaSalida = Schema::hasColumn('empleados', 'fechasalida');
        $hasFechaSalidaAlt = Schema::hasColumn('empleados', 'fecha_salida');
        $activoCondition = $hasActivo ? 'COALESCE(empleados.activo, 1) = 1' : '1 = 1';
        $fechaSalidaChecks = [];

        if ($hasFechaSalida) {
            $fechaSalidaChecks[] = "(NULLIF(TRIM(CAST(empleados.fechasalida AS CHAR)), '') IS NULL OR TRIM(CAST(empleados.fechasalida AS CHAR)) = '0000-00-00')";
        }

        if ($hasFechaSalidaAlt) {
            $fechaSalidaChecks[] = "(NULLIF(TRIM(CAST(empleados.fecha_salida AS CHAR)), '') IS NULL OR TRIM(CAST(empleados.fecha_salida AS CHAR)) = '0000-00-00')";
        }

        $fechaSalidaCondition = empty($fechaSalidaChecks)
            ? '1 = 1'
            : '(' . implode(' AND ', $fechaSalidaChecks) . ')';

        $query->selectSub(function ($subquery) use ($activoCondition, $fechaSalidaCondition) {
            $subquery->from('empleados')
                ->selectRaw("
                    CASE
                        WHEN COUNT(*) = 0 THEN 'no_existe'
                        WHEN SUM(CASE WHEN {$activoCondition} AND {$fechaSalidaCondition} THEN 1 ELSE 0 END) > 0 THEN 'activo'
                        ELSE 'inactivo'
                    END
                ")
                ->whereRaw("BINARY REPLACE(REPLACE(COALESCE(empleados.cedula, ''), '-', ''), ' ', '') = BINARY REPLACE(REPLACE(COALESCE(incentivo_administrativos.cedula, ''), '-', ''), ' ', '')")
                ->whereRaw("NULLIF(REPLACE(REPLACE(COALESCE(incentivo_administrativos.cedula, ''), '-', ''), ' ', ''), '') IS NOT NULL");
        }, 'empleado_estado');

        if (in_array($estatusFilter, ['activo', 'no_activo'], true)) {
            if ($estatusFilter === 'activo') {
                $query->having('empleado_estado', '=', 'activo');
            } else {
                $query->having('empleado_estado', '<>', 'activo');
            }
        }

        if ($withEmpleadoId) {
            $query->selectSub(function ($subquery) {
                    $subquery->from('empleados')
                        ->select('empleadoid')
                        ->whereRaw("BINARY REPLACE(REPLACE(COALESCE(empleados.cedula, ''), '-', ''), ' ', '') = BINARY REPLACE(REPLACE(COALESCE(incentivo_administrativos.cedula, ''), '-', ''), ' ', '')")
                        ->whereRaw("NULLIF(REPLACE(REPLACE(COALESCE(incentivo_administrativos.cedula, ''), '-', ''), ' ', ''), '') IS NOT NULL")
                        ->orderByDesc('empleadoid')
                        ->limit(1);
                }, 'empleadoid');
        }

        return $query;
    }

    private function findEmpleadoIdByCedula(?string $cedula): string
    {
        $cedula = preg_replace('/\D+/', '', (string) $cedula);

        if ($cedula === '') {
            return '';
        }

        return (string) (DB::table('empleados')
            ->whereRaw("BINARY REPLACE(REPLACE(COALESCE(cedula, ''), '-', ''), ' ', '') = ?", [$cedula])
            ->orderByDesc('empleadoid')
            ->value('empleadoid') ?? '');
    }

    private function validateIncentivoAdministrativo(Request $request, array $rules, array $messages = []): array
    {
        $request->merge([
            'cedula' => preg_replace('/\D+/', '', (string) $request->input('cedula', '')) ?: null,
        ]);

        $validator = validator($request->all(), $rules, array_merge([
            'cedula.regex' => 'La cedula debe tener exactamente 11 digitos numericos.',
        ], $messages));

        $validator->after(function ($validator) use ($request) {
            $grupo = trim((string) $request->input('grupo'));
            $monto = (float) $request->input('pct_total', 0);
            $esMontoFijo = in_array($grupo, self::GRUPOS_MONTO_FIJO, true);

            if (!$esMontoFijo && $monto > 100) {
                $validator->errors()->add('pct_total', 'El % Total no puede ser mayor que 100 para este grupo.');
            }
        });

        $validated = $validator->validate();
        return $validated;
    }

    private function grupoAdministrativoRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $grupo = trim((string) $value);

            if ($grupo === self::GRUPO_SEGURIDAD) {
                return;
            }

            $exists = PorcentajeIncentivo::query()
                ->where('posicion', $grupo)
                ->exists();

            if (!$exists) {
                $fail('El grupo seleccionado no es valido.');
            }
        };
    }

    public function porcentajeIncentivoIndex()
    {
        $registros = PorcentajeIncentivo::query()
            ->orderBy('posicion')
            ->paginate(30);

        return view('incentivos.porcentaje_incentivo.index', compact('registros'));
    }

    public function porcentajeIncentivoStore(Request $request)
    {
        $validated = $request->validate([
            'posicion' => [
                'required',
                'string',
                'max:70',
                Rule::unique('porcentaje_incentivos', 'posicion'),
            ],
            'bono_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        PorcentajeIncentivo::create($validated);

        return redirect()
            ->route('incentivos.porcentaje-incentivo.index')
            ->with('success', 'Registro creado correctamente.');
    }

    public function porcentajeIncentivoUpdate(Request $request, PorcentajeIncentivo $porcentajeIncentivo)
    {
        $validated = $request->validate([
            'posicion' => [
                'required',
                'string',
                'max:70',
                Rule::unique('porcentaje_incentivos', 'posicion')
                    ->ignore($porcentajeIncentivo->id),
            ],
            'bono_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        $porcentajeIncentivo->update($validated);

        return redirect()
            ->route('incentivos.porcentaje-incentivo.index')
            ->with('success', 'Registro actualizado correctamente.');
    }

    public function porcentajeIncentivoDestroy(PorcentajeIncentivo $porcentajeIncentivo)
    {
        $porcentajeIncentivo->delete();

        return redirect()
            ->route('incentivos.porcentaje-incentivo.index')
            ->with('success', 'Registro eliminado correctamente.');
    }
}
