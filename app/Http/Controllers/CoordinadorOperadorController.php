<?php

namespace App\Http\Controllers;

use App\Exports\CoordinadorOperadorExport;
use App\Http\Requests\SearchCoordinadorEmpleadoRequest;
use App\Http\Requests\StoreCoordinadorOperadorRequest;
use App\Http\Requests\UpdateCoordinadorOperadorRequest;
use App\Models\Agencia;
use App\Models\CoordinadorOperador;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CoordinadorOperadorController extends Controller
{
    private const EMPRESAS = [
        'Consorcio Joselito' => '168',
        'Negosur' => '169',
    ];

    public function index(Request $request): View
    {
        $buscar = trim((string) $request->query('buscar', ''));
        $buscarDigits = preg_replace('/\D+/', '', $buscar);
        $buscarCedulaSinCeros = ltrim((string) $buscarDigits, '0');

        $registrosQuery = CoordinadorOperador::with([
            'agencias:id,agencia,nombre_agencia,terminal',
            'empleado:id,empleadoid',
        ])
            ->withCount('agencias');

        $this->aplicarBusqueda($registrosQuery, $buscar, $buscarDigits, $buscarCedulaSinCeros);

        $registros = $registrosQuery
            ->orderByDesc('id')
            ->paginate(15)
            ->appends($request->query());

        $cedulasCoordinadores = $registros->getCollection()
            ->map(fn (CoordinadorOperador $registro): string => $this->normalizarCedula($registro->cedula))
            ->filter()
            ->unique()
            ->values();

        $cedulasEnMaestra = DB::table('empleados')
            ->whereNotNull('cedula')
            ->whereIn(
                DB::raw("REPLACE(REPLACE(REPLACE(TRIM(CAST(cedula AS CHAR)), '-', ''), ' ', ''), '.', '')"),
                $cedulasCoordinadores
            )
            ->pluck('cedula')
            ->map(fn ($cedula): string => $this->normalizarCedula($cedula))
            ->flip();

        $registros->getCollection()->each(function (CoordinadorOperador $registro) use ($cedulasEnMaestra): void {
            $cedula = $this->normalizarCedula($registro->cedula);
            $registro->setAttribute('cedula_en_maestra', $cedula !== '' && $cedulasEnMaestra->has($cedula));
        });

        $agencias = Agencia::select('id', 'agencia', 'nombre_agencia')
            ->addSelect('terminal')
            ->orderBy('agencia')
            ->get();

        $asignacionesAgencia = DB::table('coordinador_operador_agencia as coa')
            ->join('coordinador_operador as co', 'co.id', '=', 'coa.coordinador_operador_id')
            ->select('coa.agencia_id', 'co.id as coordinador_id', 'co.nombre', 'co.apellido')
            ->get()
            ->groupBy('agencia_id')
            ->map(function ($rows) {
                return $rows->map(function ($row): array {
                    return [
                        'id' => (int) $row->coordinador_id,
                        'nombre' => trim(($row->nombre ?? '').' '.($row->apellido ?? '')),
                    ];
                })->values();
            });

        $empresas = collect(array_keys(self::EMPRESAS));
        $departamentos = $this->empleadosActivosQuery()
            ->whereIn('companyid', array_values(self::EMPRESAS))
            ->whereNotNull('depto')
            ->whereRaw("TRIM(CAST(depto AS CHAR)) <> ''")
            ->selectRaw('CAST(companyid AS CHAR) as companyid')
            ->selectRaw('TRIM(CAST(depto AS CHAR)) as departamento')
            ->distinct()
            ->orderBy('companyid')
            ->orderBy('departamento')
            ->get();

        return view('coordinador_operador.index', compact(
            'registros',
            'agencias',
            'asignacionesAgencia',
            'buscar',
            'empresas',
            'departamentos'
        ));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $buscar = trim((string) $request->query('buscar', ''));
        $buscarDigits = preg_replace('/\D+/', '', $buscar);
        $buscarCedulaSinCeros = ltrim((string) $buscarDigits, '0');
        $query = CoordinadorOperador::with([
            'agencias:id,agencia,nombre_agencia,terminal',
            'empleado:id,empleadoid',
        ]);

        $this->aplicarBusqueda($query, $buscar, $buscarDigits, $buscarCedulaSinCeros);

        $coordinadores = $query
            ->orderBy('nombre')
            ->orderBy('apellido')
            ->get();

        return Excel::download(
            new CoordinadorOperadorExport($coordinadores),
            'coordinadores_terminales_'.now()->format('Y-m-d_His').'.xlsx'
        );
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('coordinador-operador.index');
    }

    public function empleados(SearchCoordinadorEmpleadoRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $companyId = self::EMPRESAS[$validated['empresa']];
        $departamento = trim($validated['departamento']);
        $buscar = trim((string) ($validated['buscar'] ?? ''));

        $empleados = $this->empleadosActivosQuery()
            ->where('companyid', $companyId)
            ->whereRaw('TRIM(CAST(depto AS CHAR)) = ?', [$departamento])
            ->whereNotNull('cedula')
            ->whereRaw("TRIM(CAST(cedula AS CHAR)) <> ''")
            ->when($buscar !== '', function (Builder $query) use ($buscar): void {
                $terminos = preg_split('/\s+/', $buscar, -1, PREG_SPLIT_NO_EMPTY);

                foreach ($terminos as $termino) {
                    $like = '%'.$termino.'%';
                    $query->where(function (Builder $subQuery) use ($like): void {
                        $subQuery
                            ->where('nombres', 'like', $like)
                            ->orWhere('apellidos', 'like', $like)
                            ->orWhere('cedula', 'like', $like)
                            ->orWhere('empleadoid', 'like', $like);
                    });
                }
            })
            ->select('id', 'empleadoid', 'nombres', 'apellidos', 'cedula', 'companyid', 'email')
            ->orderBy('nombres')
            ->orderBy('apellidos')
            ->limit(30)
            ->get();

        $empleadoIds = $empleados->pluck('id')->map(fn ($id) => (int) $id);
        $cedulas = $empleados
            ->map(fn ($empleado) => preg_replace('/\D+/', '', (string) $empleado->cedula))
            ->filter();

        $coordinadores = CoordinadorOperador::query()
            ->where(function ($query) use ($empleadoIds, $cedulas): void {
                $query->whereIn('empleado_id', $empleadoIds)
                    ->orWhereIn('cedula', $cedulas);
            })
            ->get(['id', 'empleado_id', 'cedula']);

        $coordinadoresPorEmpleado = $coordinadores
            ->whereNotNull('empleado_id')
            ->keyBy('empleado_id');
        $coordinadoresPorCedula = $coordinadores
            ->keyBy(fn (CoordinadorOperador $coordinador) => preg_replace('/\D+/', '', $coordinador->cedula));

        $data = $empleados->map(function ($empleado) use ($coordinadoresPorEmpleado, $coordinadoresPorCedula): array {
            $cedula = preg_replace('/\D+/', '', (string) $empleado->cedula);
            $coordinador = $coordinadoresPorEmpleado->get((int) $empleado->id)
                ?? $coordinadoresPorCedula->get($cedula);

            return [
                'id' => (int) $empleado->id,
                'empleadoid' => (string) $empleado->empleadoid,
                'nombre' => trim(preg_replace('/\s+/', ' ', $empleado->nombres.' '.$empleado->apellidos)),
                'cedula' => $cedula,
                'correo' => trim((string) $empleado->email),
                'empresa_nombre' => $this->nombreEmpresaDesdeCompanyId($empleado->companyid),
                'coordinador' => $coordinador ? ['id' => (int) $coordinador->id] : null,
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function store(StoreCoordinadorOperadorRequest $request): RedirectResponse
    {
        $payload = $this->payloadDesdeEmpleado($request->validated());

        CoordinadorOperador::create($payload);

        return redirect()->route('coordinador-operador.index')
            ->with('success', 'Registro creado correctamente.');
    }

    public function update(UpdateCoordinadorOperadorRequest $request, CoordinadorOperador $coordinador_operador): RedirectResponse
    {
        $coordinador_operador->update(
            $this->payloadDesdeEmpleado($request->validated(), $coordinador_operador)
        );

        return redirect()->route('coordinador-operador.index')
            ->with('success', 'Registro actualizado correctamente.');
    }

    public function destroy(CoordinadorOperador $coordinador_operador): RedirectResponse
    {
        $coordinador_operador->delete();

        return redirect()->route('coordinador-operador.index')
            ->with('success', 'Registro eliminado correctamente.');
    }

    public function asignarAgencias(Request $request, CoordinadorOperador $coordinador_operador): RedirectResponse
    {
        $validated = $request->validate([
            'agencias' => ['nullable', 'array'],
            'agencias.*' => ['integer', 'exists:agencias,id'],
            'confirmar_reasignacion' => ['nullable', 'boolean'],
        ]);

        $agenciasSeleccionadas = collect($validated['agencias'] ?? [])->map(fn ($id) => (int) $id)->values();

        $conflictos = DB::table('coordinador_operador_agencia as coa')
            ->join('coordinador_operador as co', 'co.id', '=', 'coa.coordinador_operador_id')
            ->join('agencias as a', 'a.id', '=', 'coa.agencia_id')
            ->whereIn('coa.agencia_id', $agenciasSeleccionadas)
            ->where('coa.coordinador_operador_id', '!=', $coordinador_operador->id)
            ->select('coa.agencia_id', 'a.terminal', 'co.nombre', 'co.apellido')
            ->get();

        $confirmarReasignacion = (bool) ($validated['confirmar_reasignacion'] ?? false);

        if ($conflictos->isNotEmpty() && ! $confirmarReasignacion) {
            return redirect()->route('coordinador-operador.index')
                ->with('error', 'Algunas agencias ya están asignadas a otro coordinador. Confirma la reasignación para moverlas.');
        }

        if ($conflictos->isNotEmpty() && $confirmarReasignacion) {
            DB::table('coordinador_operador_agencia')
                ->whereIn('agencia_id', $conflictos->pluck('agencia_id')->unique()->values())
                ->where('coordinador_operador_id', '!=', $coordinador_operador->id)
                ->delete();
        }

        $coordinador_operador->agencias()->sync($agenciasSeleccionadas->all());

        return redirect()->route('coordinador-operador.index')
            ->with('success', 'Agencias asignadas correctamente.');
    }

    private function empleadosActivosQuery(): Builder
    {
        return DB::table('empleados')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('fechasalida')
                    ->orWhereRaw("TRIM(CAST(fechasalida AS CHAR)) = ''")
                    ->orWhereRaw("TRIM(CAST(fechasalida AS CHAR)) = '0000-00-00'");
            });
    }

    private function aplicarBusqueda(
        EloquentBuilder $query,
        string $buscar,
        string $buscarDigits,
        string $buscarCedulaSinCeros
    ): void {
        if ($buscar === '') {
            return;
        }

        $query->where(function (EloquentBuilder $query) use ($buscar, $buscarDigits, $buscarCedulaSinCeros): void {
            $query->where('nombre', 'like', "%{$buscar}%")
                ->orWhere('apellido', 'like', "%{$buscar}%")
                ->orWhereRaw("TRIM(CONCAT(COALESCE(nombre, ''), ' ', COALESCE(apellido, ''))) LIKE ?", ["%{$buscar}%"])
                ->orWhere('correo', 'like', "%{$buscar}%")
                ->orWhere('puesto', 'like', "%{$buscar}%")
                ->orWhereHas('empleado', function (EloquentBuilder $empleadoQuery) use ($buscar): void {
                    $empleadoQuery->where('empleadoid', 'like', "%{$buscar}%");
                });

            if ($buscarDigits !== '') {
                $query->orWhereRaw('CAST(cedula AS CHAR) LIKE ?', ["%{$buscarDigits}%"]);

                if ($buscarCedulaSinCeros !== '') {
                    $query->orWhereRaw('CAST(CAST(cedula AS UNSIGNED) AS CHAR) LIKE ?', ["%{$buscarCedulaSinCeros}%"]);
                }
            }
        });
    }

    private function normalizarCedula(mixed $cedula): string
    {
        return preg_replace('/\D+/', '', (string) $cedula);
    }

    /**
     * @param  array{empresa: string, departamento: string, empleado_id: int}  $seleccion
     * @return array{empleado_id: int, nombre: string, apellido: string, correo: ?string, cedula: string, telefono: ?string, puesto: string}
     */
    private function payloadDesdeEmpleado(array $seleccion, ?CoordinadorOperador $registroActual = null): array
    {
        $companyId = self::EMPRESAS[$seleccion['empresa']];
        $departamento = trim($seleccion['departamento']);
        $empleado = $this->empleadosActivosQuery()
            ->where('id', $seleccion['empleado_id'])
            ->where('companyid', $companyId)
            ->whereRaw('TRIM(CAST(depto AS CHAR)) = ?', [$departamento])
            ->first(['id', 'nombres', 'apellidos', 'cedula', 'email', 'tel1']);

        $cedula = preg_replace('/\D+/', '', (string) ($empleado->cedula ?? ''));

        if (! $empleado || strlen($cedula) !== 11) {
            throw ValidationException::withMessages([
                'empleado_id' => 'El empleado seleccionado no está activo o no tiene una cédula válida.',
            ]);
        }

        $otroCoordinador = CoordinadorOperador::query()
            ->where(function ($query) use ($empleado, $cedula): void {
                $query->where('empleado_id', $empleado->id)
                    ->orWhere('cedula', $cedula);
            })
            ->when($registroActual, fn ($query) => $query->whereKeyNot($registroActual->getKey()))
            ->exists();

        if ($otroCoordinador) {
            throw ValidationException::withMessages([
                'empleado_id' => 'El empleado seleccionado ya pertenece a otro coordinador o pool.',
            ]);
        }

        $telefono = preg_replace('/\D+/', '', (string) ($empleado->tel1 ?? ''));

        return [
            'empleado_id' => (int) $empleado->id,
            'nombre' => trim(preg_replace('/\s+/', ' ', (string) $empleado->nombres)),
            'apellido' => trim(preg_replace('/\s+/', ' ', (string) $empleado->apellidos)),
            'correo' => ($correo = trim((string) ($empleado->email ?? ''))) !== '' ? mb_substr($correo, 0, 150) : null,
            'cedula' => $cedula,
            'telefono' => $telefono !== '' ? substr($telefono, 0, 10) : null,
            'puesto' => 'coordinador',
        ];
    }

    private function nombreEmpresaDesdeCompanyId(mixed $companyId): string
    {
        return match ((string) $companyId) {
            '168' => 'Grupo Joselito',
            '169' => 'Negosur',
            default => 'Sin empresa',
        };
    }
}
