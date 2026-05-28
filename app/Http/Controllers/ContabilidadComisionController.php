<?php

namespace App\Http\Controllers;

use App\Models\AcuerdoComision;
use App\Models\Agencia;
use App\Models\CentroDeCosto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContabilidadComisionController extends Controller
{
    public function index()
    {
        [$fechaInicio, $fechaFin] = $this->resolvePeriodo(request());

        $acuerdos = AcuerdoComision::with('agencias:id,agencia,nombre_agencia,terminal')
            ->withCount('agencias')
            ->orderByDesc('id')
            ->paginate(15);

        $agencias = Agencia::select('id', 'agencia', 'nombre_agencia', 'terminal')
            ->orderBy('agencia')
            ->get();

        $calculos = session('calculos_comisiones', []);

        return view('contabilidad.reportes.comisiones', compact('acuerdos', 'agencias', 'calculos', 'fechaInicio', 'fechaFin'));
    }

    public function create()
    {
        return view('contabilidad.reportes.comisiones-create');
    }

    public function store(Request $request)
    {
        AcuerdoComision::create($this->validateAcuerdo($request));

        return redirect()->route('contabilidad.reportes.comisiones')
            ->with('success', 'Acuerdo creado correctamente.');
    }

    public function edit(AcuerdoComision $acuerdo)
    {
        return view('contabilidad.reportes.comisiones-edit', [
            'acuerdo' => $acuerdo,
        ]);
    }

    public function update(Request $request, AcuerdoComision $acuerdo)
    {
        $acuerdo->update($this->validateAcuerdo($request, $acuerdo));

        return redirect()->route('contabilidad.reportes.comisiones')
            ->with('success', 'Acuerdo actualizado correctamente.');
    }

    public function destroy(AcuerdoComision $acuerdo)
    {
        $acuerdo->delete();

        return redirect()->route('contabilidad.reportes.comisiones')
            ->with('success', 'Acuerdo eliminado correctamente.');
    }

    public function clone(AcuerdoComision $acuerdo)
    {
        $acuerdo->load('agencias:id');

        $nuevoAcuerdo = $acuerdo->replicate();
        $nuevoAcuerdo->nombre = $acuerdo->nombre . ' copia';
        $nuevoAcuerdo->save();

        $nuevoAcuerdo->agencias()->sync($acuerdo->agencias->pluck('id')->all());

        return redirect()->route('contabilidad.reportes.comisiones.acuerdos.edit', $nuevoAcuerdo->id)
            ->with('success', 'Acuerdo clonado correctamente. Puedes ajustar sus condiciones.');
    }

    public function asignarAgencias(Request $request, AcuerdoComision $acuerdo)
    {
        $validated = $request->validate([
            'agencias' => ['nullable', 'array'],
            'agencias.*' => ['integer', 'exists:agencias,id'],
        ]);

        $agenciasSeleccionadas = collect($validated['agencias'] ?? [])->map(fn ($id) => (int) $id)->values();

        $acuerdo->agencias()->sync($agenciasSeleccionadas->all());

        return redirect()->route('contabilidad.reportes.comisiones')
            ->with('success', 'Agencias asignadas correctamente.');
    }

    public function calcular(Request $request, AcuerdoComision $acuerdo)
    {
        [$fechaInicio, $fechaFin] = $this->resolvePeriodo($request);

        $calculo = $this->calcularComisionAcuerdo($acuerdo, $fechaInicio, $fechaFin);

        if (! $calculo['agencias']) {
            return redirect()->route('contabilidad.reportes.comisiones', [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
            ])->with('error', 'Este acuerdo no tiene agencias asignadas para consultar ventas.');
        }

        return redirect()->route('contabilidad.reportes.comisiones', [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
        ])->with('calculos_comisiones', [
            $acuerdo->id => $calculo,
        ])->with('success', 'Comision calculada correctamente.');
    }

    public function calcularTodas(Request $request)
    {
        [$fechaInicio, $fechaFin] = $this->resolvePeriodo($request);

        $acuerdos = AcuerdoComision::with('agencias:id,terminal')
            ->orderBy('id')
            ->get();

        $ventasPorTerminal = $this->ventasPorTerminales(
            $acuerdos->flatMap(fn (AcuerdoComision $acuerdo) => $acuerdo->agencias->pluck('terminal')),
            $fechaInicio,
            $fechaFin
        );

        $calculos = $acuerdos
            ->mapWithKeys(function (AcuerdoComision $acuerdo) use ($fechaInicio, $fechaFin, $ventasPorTerminal) {
                return [$acuerdo->id => $this->calcularComisionAcuerdo($acuerdo, $fechaInicio, $fechaFin, $ventasPorTerminal)];
            })
            ->all();

        return redirect()->route('contabilidad.reportes.comisiones', [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
        ])->with('calculos_comisiones', $calculos)
            ->with('success', 'Comisiones generadas correctamente.');
    }

    public function gruposIndex()
    {
        [$fechaInicio, $fechaFin] = $this->resolvePeriodo(request());
        $grupos = $this->obtenerGruposComision();
        $calculos = session('calculos_comisiones_grupo', []);

        return view('contabilidad.reportes.comisiones_por_grupo', compact('grupos', 'calculos', 'fechaInicio', 'fechaFin'));
    }

    public function calcularGrupo(Request $request, string $subgrupo)
    {
        [$fechaInicio, $fechaFin] = $this->resolvePeriodo($request);

        $validated = $request->validate([
            'porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
        ], [
            'porcentaje.decimal' => 'El porcentaje solo puede tener hasta 2 decimales.',
            'porcentaje.max' => 'El porcentaje no puede ser mayor a 100.',
        ]);
        $porcentaje = (float) ($validated['porcentaje'] ?? 0);

        $grupos = $this->obtenerGruposComision();
        $grupo = $grupos->firstWhere('subgrupo', $subgrupo);

        if (! $grupo) {
            return redirect()->route('contabilidad.reportes.comisiones-por-grupo', [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
            ])->with('error', 'No se encontro el subgrupo seleccionado.');
        }

        $calculo = $this->calcularComisionGrupo($grupo, $fechaInicio, $fechaFin, $porcentaje);

        if (! $calculo['agencias']) {
            return redirect()->route('contabilidad.reportes.comisiones-por-grupo', [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
            ])->with('error', 'Este subgrupo no tiene agencias para consultar ventas.');
        }

        return redirect()->route('contabilidad.reportes.comisiones-por-grupo', [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
        ])->with('calculos_comisiones_grupo', [
            $subgrupo => $calculo,
        ])->with('success', 'Subgrupo calculado correctamente.');
    }

    private function validateAcuerdo(Request $request, ?AcuerdoComision $acuerdo = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:150'],
            'cedula' => [
                'nullable',
                'regex:/^\d{11}$/',
            ],
            'telefono' => ['nullable', 'regex:/^\d{10}$/'],
            'porcentaje' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'activo' => ['required', 'boolean'],
        ], [
            'cedula.regex' => 'La cedula debe contener exactamente 11 digitos numericos.',
            'telefono.regex' => 'El telefono debe contener exactamente 10 digitos numericos.',
            'porcentaje.max' => 'El porcentaje no puede ser mayor a 100.',
            'porcentaje.decimal' => 'El porcentaje solo puede tener hasta 2 decimales.',
        ]);
    }

    private function resolvePeriodo(Request $request): array
    {
        $inicio = $request->input('fecha_inicio');
        $fin = $request->input('fecha_fin');

        $fechaInicio = $inicio
            ? Carbon::parse($inicio)->toDateString()
            : now()->startOfMonth()->toDateString();

        $fechaFin = $fin
            ? Carbon::parse($fin)->toDateString()
            : now()->toDateString();

        if ($fechaInicio > $fechaFin) {
            return [$fechaFin, $fechaInicio];
        }

        return [$fechaInicio, $fechaFin];
    }

    private function calcularComisionAcuerdo(AcuerdoComision $acuerdo, string $fechaInicio, string $fechaFin, ?Collection $ventasPorTerminal = null): array
    {
        $acuerdo->loadMissing('agencias:id,terminal');

        $terminales = $acuerdo->agencias
            ->pluck('terminal')
            ->map(fn ($terminal) => trim((string) $terminal))
            ->filter()
            ->unique()
            ->values();

        $ventaBase = $ventasPorTerminal
            ? $this->sumarVentasDesdeMapa($terminales, $ventasPorTerminal)
            : $this->sumarVentasPorTerminales($terminales, $fechaInicio, $fechaFin);
        $porcentaje = (float) $acuerdo->porcentaje;
        $montoComision = round($ventaBase * ($porcentaje / 100), 2);

        return [
            'venta_base' => round($ventaBase, 2),
            'porcentaje' => $porcentaje,
            'monto_comision' => $montoComision,
            'agencias' => $acuerdo->agencias->count(),
            'terminales' => $terminales->count(),
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
        ];
    }

    private function sumarVentasPorTerminales(Collection $terminales, string $fechaInicio, string $fechaFin): float
    {
        if ($terminales->isEmpty()) {
            return 0.0;
        }

        $terminalesConsulta = $terminales
            ->flatMap(function ($terminal) {
                $terminal = trim((string) $terminal);
                $normalizada = ltrim($terminal, '0') ?: '0';

                return [$terminal, $normalizada];
            })
            ->filter()
            ->unique()
            ->values();

        $sumarTabla = function (string $tabla) use ($terminalesConsulta, $fechaInicio, $fechaFin): float {
            if (! DB::getSchemaBuilder()->hasTable($tabla)) {
                return 0.0;
            }

            return (float) DB::table($tabla)
                ->whereBetween('fecha', [$fechaInicio, $fechaFin])
                ->whereIn('agencia_id', $terminalesConsulta)
                ->sum('monto');
        };

        return $sumarTabla('vt_usuarios_bet') + $sumarTabla('vt_usuarios_net');
    }

    private function ventasPorTerminales(Collection $terminales, string $fechaInicio, string $fechaFin): Collection
    {
        if ($terminales->isEmpty()) {
            return collect();
        }

        $terminalesConsulta = $terminales
            ->flatMap(function ($terminal) {
                $terminal = trim((string) $terminal);
                $normalizada = ltrim($terminal, '0') ?: '0';

                return [$terminal, $normalizada];
            })
            ->filter()
            ->unique()
            ->values();

        $ventas = collect();

        foreach (['vt_usuarios_bet', 'vt_usuarios_net'] as $tabla) {
            if (! DB::getSchemaBuilder()->hasTable($tabla)) {
                continue;
            }

            DB::table($tabla)
                ->select('agencia_id')
                ->selectRaw('SUM(monto) as total_venta')
                ->whereBetween('fecha', [$fechaInicio, $fechaFin])
                ->whereIn('agencia_id', $terminalesConsulta)
                ->groupBy('agencia_id')
                ->get()
                ->each(function ($row) use ($ventas) {
                    $terminal = ltrim(trim((string) $row->agencia_id), '0') ?: '0';
                    $ventas[$terminal] = (float) ($ventas[$terminal] ?? 0) + (float) $row->total_venta;
                });
        }

        return $ventas;
    }

    private function sumarVentasDesdeMapa(Collection $terminales, Collection $ventasPorTerminal): float
    {
        return (float) $terminales
            ->map(fn ($terminal) => ltrim(trim((string) $terminal), '0') ?: '0')
            ->unique()
            ->sum(fn ($terminal) => (float) ($ventasPorTerminal[$terminal] ?? 0));
    }

    private function obtenerGruposComision(): Collection
    {
        $centros = CentroDeCosto::query()
            ->whereNotNull('id_sub_grupo')
            ->where('id_sub_grupo', '<>', '')
            ->whereNotNull('id_viejo')
            ->where('id_viejo', '<>', '')
            ->get(['id_sub_grupo', 'id_viejo', 'atributos'])
            ->filter(function (CentroDeCosto $centro) {
                $atrib75 = trim((string) $this->getAtributoCentroCosto($centro->atributos, 'Atr75'));
                $atrib103 = trim((string) $this->getAtributoCentroCosto($centro->atributos, 'Atr103'));

                return $atrib75 === '5' || $atrib103 === '6';
            })
            ->values();

        $terminales = $centros
            ->pluck('id_viejo')
            ->map(fn ($terminal) => trim((string) $terminal))
            ->filter()
            ->unique()
            ->values();

        $terminalesConsulta = $terminales
            ->flatMap(function ($terminal) {
                $normalizada = ltrim($terminal, '0') ?: '0';

                return [$terminal, $normalizada];
            })
            ->unique()
            ->values();

        $agenciasPorTerminal = Agencia::query()
            ->select('agencia', 'nombre_agencia', 'terminal')
            ->whereIn(DB::raw('TRIM(CAST(terminal AS CHAR))'), $terminalesConsulta->all())
            ->get()
            ->keyBy(fn (Agencia $agencia) => ltrim(trim((string) $agencia->terminal), '0') ?: '0');

        return $centros
            ->map(function (CentroDeCosto $centro) use ($agenciasPorTerminal) {
                [$subgrupo, $nombre] = $this->parseSubgrupo($centro->id_sub_grupo);
                $terminal = trim((string) $centro->id_viejo);
                $agencia = $agenciasPorTerminal->get(ltrim($terminal, '0') ?: '0');

                return [
                    'subgrupo' => $subgrupo,
                    'nombre_subgrupo' => $nombre,
                    'subgrupo_original' => trim((string) $centro->id_sub_grupo),
                    'terminal' => $terminal,
                    'nombre_agencia' => (string) ($agencia->nombre_agencia ?? $agencia->agencia ?? ''),
                    'agencia' => (string) ($agencia->agencia ?? ''),
                ];
            })
            ->filter(fn (array $row) => $row['subgrupo'] !== '' && $row['terminal'] !== '')
            ->groupBy('subgrupo')
            ->map(function (Collection $rows, string $subgrupo) {
                return [
                    'subgrupo' => $subgrupo,
                    'nombre_subgrupo' => (string) ($rows->first()['nombre_subgrupo'] ?? ''),
                    'subgrupo_original' => (string) ($rows->first()['subgrupo_original'] ?? $subgrupo),
                    'agencias' => $rows
                        ->unique('terminal')
                        ->sortBy('terminal', SORT_NATURAL)
                        ->map(fn (array $row) => [
                            'terminal' => $row['terminal'],
                            'nombre_agencia' => $row['nombre_agencia'] ?: 'Sin nombre',
                            'agencia' => $row['agencia'],
                        ])
                        ->values(),
                ];
            })
            ->sortBy('subgrupo', SORT_NATURAL)
            ->values();
    }

    private function calcularComisionGrupo(array $grupo, string $fechaInicio, string $fechaFin, float $porcentaje = 0): array
    {
        $terminales = collect($grupo['agencias'] ?? [])
            ->map(fn ($agencia) => trim((string) (is_array($agencia) ? ($agencia['terminal'] ?? '') : $agencia)))
            ->filter()
            ->unique()
            ->values();

        $ventaBase = $this->sumarVentasPorTerminales($terminales, $fechaInicio, $fechaFin);
        $montoComision = round($ventaBase * ($porcentaje / 100), 2);

        return [
            'venta_base' => round($ventaBase, 2),
            'porcentaje' => round($porcentaje, 2),
            'monto_comision' => $montoComision,
            'agencias' => $terminales->count(),
            'terminales' => $terminales->count(),
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
        ];
    }

    private function parseSubgrupo(?string $valor): array
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return ['', ''];
        }

        $partes = preg_split('/\s*-\s*/', $texto, 2);
        $subgrupo = preg_replace('/\D+/', '', (string) ($partes[0] ?? ''));
        $nombre = trim((string) ($partes[1] ?? ''));

        return [$subgrupo, $nombre];
    }

    private function getAtributoCentroCosto(mixed $atributos, string $key): string
    {
        if (! is_array($atributos)) {
            return '';
        }

        $suffix = preg_replace('/\D+/', '', $key);
        $keys = collect([$key, "Atrib{$suffix}", "atrib{$suffix}", "Atr{$suffix}", "atr{$suffix}", $suffix])
            ->filter()
            ->unique()
            ->values();

        foreach ($keys as $candidate) {
            if (array_key_exists($candidate, $atributos)) {
                return (string) $atributos[$candidate];
            }

            $lookup = strtolower((string) $candidate);
            foreach ($atributos as $attrKey => $value) {
                if (is_string($attrKey) && strtolower($attrKey) === $lookup) {
                    return (string) $value;
                }
            }
        }

        return '';
    }
}
