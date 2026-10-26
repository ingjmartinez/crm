<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarClasificacionCuentaRequest;
use App\Models\ClasificacionCuenta;
use App\Models\CuentaContable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ContabilidadClasificacionCuentaController extends Controller
{
    public function index(): View
    {
        $clasificaciones = config('clasificacion_cuentas.cuentas', []);
        $guardadas = ClasificacionCuenta::query()->get()->keyBy('cuenta');
        $codigos = array_values(array_unique(array_merge(array_keys($clasificaciones), $guardadas->keys()->all())));
        $codigosActivos = collect($codigos)->filter(fn (string|int $codigo): bool => $guardadas->get((string) $codigo)?->activo ?? isset($clasificaciones[$codigo]))->values()->all();
        $catalogo = CuentaContable::query()
            ->whereIn('cuenta', $codigosActivos)
            ->get(['cuenta', 'descripcion'])
            ->keyBy('cuenta');

        $cuentas = collect($codigosActivos)->map(function (string|int $codigo) use ($catalogo, $clasificaciones, $guardadas): array {
            $codigo = (string) $codigo;
            $guardada = $guardadas->get($codigo);
            $clasificacion = $guardada?->clasificacion ?? $clasificaciones[$codigo];
            $tipoCriterio = $guardada?->criterio ?? config("clasificacion_cuentas.criterios.{$codigo}", $clasificacion);
            $criterio = match ($tipoCriterio) {
                'FIJO' => 'Estructura recurrente necesaria para operar',
                'EXCLUIDA' => 'Excluida del gasto contable para factibilidad; sustituida por estándar por agencia',
                default => 'Variable, eventual o no estructural para la prueba de factibilidad',
            };

            return [
                'cuenta' => $codigo,
                'nombre' => $catalogo->get($codigo)?->descripcion,
                'clasificacion' => $clasificacion,
                'criterio' => $criterio,
                'observacion' => $guardada?->observacion ?? config("clasificacion_cuentas.observaciones.{$codigo}", ''),
                'requiere_revision' => $tipoCriterio !== $clasificacion,
            ];
        })->values();

        return view('contabilidad.clasificacion-cuentas', [
            'cuentas' => $cuentas,
            'cuentasFaltantes' => $cuentas->whereNull('nombre')->pluck('cuenta')->all(),
            'cuentasConCriterioDistinto' => $cuentas->where('requiere_revision', true)->pluck('cuenta')->all(),
            'cuentasDisponibles' => CuentaContable::query()->whereNotIn('cuenta', $codigosActivos)
                ->orderBy('cuenta')->get(['cuenta', 'descripcion']),
        ]);
    }

    public function store(GuardarClasificacionCuentaRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $cuenta = $datos['cuenta'];
        $guardada = ClasificacionCuenta::query()->where('cuenta', $cuenta)->first();
        if ($guardada?->activo ?? isset(config('clasificacion_cuentas.cuentas', [])[$cuenta])) {
            throw ValidationException::withMessages(['cuenta' => 'La cuenta ya está en la clasificación.']);
        }

        ClasificacionCuenta::query()->updateOrCreate(['cuenta' => $cuenta], [
            'clasificacion' => $datos['clasificacion'],
            'criterio' => $datos['criterio'],
            'observacion' => $datos['observacion'] ?? null,
            'activo' => true,
        ]);

        return to_route('contabilidad.clasificacion-cuentas')->with('mensaje', 'La cuenta se agregó a la clasificación.');
    }

    public function destroy(string $cuenta): RedirectResponse
    {
        $guardada = ClasificacionCuenta::query()->where('cuenta', $cuenta)->first();
        abort_unless($guardada?->activo ?? isset(config('clasificacion_cuentas.cuentas', [])[$cuenta]), 404);

        ClasificacionCuenta::query()->updateOrCreate(['cuenta' => $cuenta], [
            'clasificacion' => $guardada?->clasificacion ?? config("clasificacion_cuentas.cuentas.{$cuenta}"),
            'criterio' => $guardada?->criterio ?? config("clasificacion_cuentas.criterios.{$cuenta}", config("clasificacion_cuentas.cuentas.{$cuenta}")),
            'observacion' => $guardada?->observacion ?? config("clasificacion_cuentas.observaciones.{$cuenta}"),
            'activo' => false,
        ]);

        return to_route('contabilidad.clasificacion-cuentas')->with('mensaje', 'La cuenta se quitó de la clasificación.');
    }
}
