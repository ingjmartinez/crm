<?php

namespace App\Http\Controllers;

use App\Models\IncentivoAdministrativo;
use App\Models\PorcentajeIncentivo;
use Illuminate\Http\Request;
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

        $registros = IncentivoAdministrativo::query()
            ->when($buscarNombre !== '', function ($query) use ($buscarNombre) {
                $query->where('nombre', 'like', '%' . $buscarNombre . '%');
            })
            ->when($grupoFilter !== '', function ($query) use ($grupoFilter) {
                $query->where('grupo', $grupoFilter);
            })
            ->when($empresaFilter !== '', function ($query) use ($empresaFilter) {
                $query->where('empresa', $empresaFilter);
            })
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

        return view('incentivos.incentivo_administrativo.index', compact('registros', 'posiciones', 'empresas', 'buscarNombre', 'grupoFilter', 'empresaFilter'));
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
            'empresa' => ['required', 'string', 'max:50', Rule::in(IncentivoAdministrativo::EMPRESAS_VALIDAS)],
            'pct_total' => ['required', 'numeric', 'min:0', 'max:9999999'],
        ], [
            'nombre.unique' => 'Ya existe un colaborador registrado con ese grupo y empresa.',
        ]);

        $incentivoAdministrativo->update($validated);

        return redirect()
            ->route('incentivos.incentivo-administrativo.index')
            ->with('success', 'Registro actualizado correctamente.');
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

    private function validateIncentivoAdministrativo(Request $request, array $rules, array $messages = []): array
    {
        $validator = validator($request->all(), $rules, $messages);

        $validator->after(function ($validator) use ($request) {
            $grupo = trim((string) $request->input('grupo'));
            $monto = (float) $request->input('pct_total', 0);
            $esMontoFijo = in_array($grupo, self::GRUPOS_MONTO_FIJO, true);

            if (!$esMontoFijo && $monto > 100) {
                $validator->errors()->add('pct_total', 'El % Total no puede ser mayor que 100 para este grupo.');
            }
        });

        return $validator->validate();
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
