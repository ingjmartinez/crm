<?php

namespace App\Http\Controllers\Gerencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gerencia\ProcesarBeneficioBrutoRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class BeneficioBrutoController extends Controller
{
    private const GRUPOS = [
        'joselito' => 'Joselito',
        'negosur' => 'Negosur',
        'higuey' => 'Higuey',
    ];

    private const COLUMNAS = [
        'terminal' => 2,
        'tradicional_ventas' => 4,
        'tradicional_premios' => 5,
        'tradicional_pagados' => 6,
        'tradicional_resultados' => 7,
        'no_tradicional_ventas' => 8,
        'no_tradicional_premios' => 9,
        'no_tradicional_pagados' => 10,
        'no_tradicional_resultados' => 11,
        'recargas' => 12,
        'paqueticos' => 13,
        'seguros' => 14,
        'boletos' => 15,
    ];

    public function index(): View
    {
        return $this->vista();
    }

    public function procesar(ProcesarBeneficioBrutoRequest $request): View
    {
        $filasPorGrupo = collect(self::GRUPOS)->mapWithKeys(function (string $nombreGrupo, string $grupo) use ($request): array {
            $campo = "archivo_{$grupo}";
            /** @var UploadedFile $archivo */
            $archivo = $request->validated($campo);
            $filas = $this->leerCsv($archivo, $campo)->map(function (array $fila) use ($grupo, $nombreGrupo): array {
                $fila['grupo'] = $grupo;
                $fila['grupo_nombre'] = $nombreGrupo;

                return $fila;
            });

            return [$grupo => $filas];
        });
        $filas = $filasPorGrupo->collapse()->values();
        $nombresArchivos = collect(self::GRUPOS)->mapWithKeys(function (string $nombreGrupo, string $grupo) use ($request, $filasPorGrupo): array {
            /** @var UploadedFile $archivo */
            $archivo = $request->validated("archivo_{$grupo}");

            return [$grupo => [
                'grupo' => $nombreGrupo,
                'nombre' => $archivo->getClientOriginalName(),
                'filas' => $filasPorGrupo->get($grupo)->count(),
            ]];
        })->all();
        $resumenPorGrupo = $filasPorGrupo
            ->map(fn (Collection $filasGrupo): array => $this->crearResumen($filasGrupo))
            ->all();

        return $this->vista($filas, $nombresArchivos, $resumenPorGrupo);
    }

    /**
     * @param  Collection<int, array<string, bool|float|string|null>>|null  $filas
     * @param  array<string, array{grupo: string, nombre: string, filas: int}>|null  $nombresArchivos
     * @param  array<string, array<string, mixed>>|null  $resumenPorGrupo
     */
    private function vista(?Collection $filas = null, ?array $nombresArchivos = null, ?array $resumenPorGrupo = null): View
    {
        $filas ??= collect();
        $resumen = $this->crearResumen($filas);
        $resumenPorGrupo ??= collect(self::GRUPOS)
            ->mapWithKeys(fn (string $nombre, string $grupo): array => [$grupo => $this->crearResumen(collect())])
            ->all();
        $camposNumericos = array_keys(array_diff_key(self::COLUMNAS, ['terminal' => true]));
        $totales = collect($camposNumericos)
            ->mapWithKeys(fn (string $campo): array => [$campo => (float) $filas->sum($campo)])
            ->all();
        $informeGerencial = $this->crearInformeGerencial($filas, $resumen);
        $cruceAgencias = [
            'identificadas' => $filas->where('agencia_encontrada', true)->count(),
            'total' => $filas->count(),
            'con_ciudad' => $filas->whereNotNull('ciudad')->count(),
            'con_ruta' => $filas->whereNotNull('ruta')->count(),
        ];

        return view('gerencia.beneficio-bruto', [
            'filas' => $filas,
            'totales' => $totales,
            'resumen' => $resumen,
            'resumenPorGrupo' => $resumenPorGrupo,
            'informeGerencial' => $informeGerencial,
            'cruceAgencias' => $cruceAgencias,
            'nombresArchivos' => $nombresArchivos,
        ]);
    }

    /**
     * @param  Collection<int, array<string, bool|float|string|null>>  $filas
     * @return array<string, mixed>
     */
    private function crearResumen(Collection $filas): array
    {
        $camposNumericos = array_keys(array_diff_key(self::COLUMNAS, ['terminal' => true]));
        $totales = collect($camposNumericos)
            ->mapWithKeys(fn (string $campo): array => [$campo => (float) $filas->sum($campo)])
            ->all();
        $contarTerminales = fn (array $campos): int => $filas
            ->filter(fn (array $fila): bool => collect($campos)->contains(
                fn (string $campo): bool => (float) $fila[$campo] > 0,
            ))
            ->unique('terminal')
            ->count();
        $resumen = [
            'tradicional' => [
                'total_vendido' => $totales['tradicional_ventas'],
                'premios_sacados' => $totales['tradicional_premios'],
                'premios_pagados' => $totales['tradicional_pagados'],
                'balance_general' => $totales['tradicional_resultados'],
                'terminales' => $contarTerminales(['tradicional_ventas']),
            ],
            'no_tradicional' => [
                'total_vendido' => $totales['no_tradicional_ventas'],
                'premios_sacados' => $totales['no_tradicional_premios'],
                'premios_pagados' => $totales['no_tradicional_pagados'],
                'balance_general' => $totales['no_tradicional_resultados'],
                'terminales' => $contarTerminales(['no_tradicional_ventas']),
            ],
            'recargas' => [
                'recargas' => $totales['recargas'],
                'paqueticos' => $totales['paqueticos'],
                'total_vendido' => $totales['recargas'] + $totales['paqueticos'],
                'terminales' => $contarTerminales(['recargas', 'paqueticos']),
                'terminales_recargas' => $contarTerminales(['recargas']),
                'terminales_paqueticos' => $contarTerminales(['paqueticos']),
            ],
            'ventas_externas' => [
                'seguros' => $totales['seguros'],
                'boletos' => $totales['boletos'],
                'total_vendido' => $totales['seguros'] + $totales['boletos'],
                'terminales' => $contarTerminales(['seguros', 'boletos']),
                'terminales_seguros' => $contarTerminales(['seguros']),
                'terminales_boletos' => $contarTerminales(['boletos']),
            ],
        ];
        $resumen['balance'] = $resumen['tradicional']['total_vendido']
            + $resumen['no_tradicional']['total_vendido']
            + $resumen['recargas']['total_vendido']
            + $resumen['ventas_externas']['total_vendido'];

        return $resumen;
    }

    /**
     * @param  Collection<int, array<string, bool|float|string|null>>  $filas
     * @param  array<string, mixed>  $resumen
     * @return array<string, mixed>
     */
    private function crearInformeGerencial(Collection $filas, array $resumen): array
    {
        $bloques = collect([
            [
                'nombre' => 'Tradicional',
                'ventas' => $resumen['tradicional']['total_vendido'],
                'terminales' => $resumen['tradicional']['terminales'],
            ],
            [
                'nombre' => 'No Tradicional',
                'ventas' => $resumen['no_tradicional']['total_vendido'],
                'terminales' => $resumen['no_tradicional']['terminales'],
            ],
            [
                'nombre' => 'Recargas y Paqueticos',
                'ventas' => $resumen['recargas']['total_vendido'],
                'terminales' => $resumen['recargas']['terminales'],
            ],
            [
                'nombre' => 'Ventas externas',
                'ventas' => $resumen['ventas_externas']['total_vendido'],
                'terminales' => $resumen['ventas_externas']['terminales'],
            ],
        ])->map(function (array $bloque) use ($resumen): array {
            $bloque['participacion'] = $resumen['balance'] > 0
                ? ($bloque['ventas'] / $resumen['balance']) * 100
                : 0.0;
            $bloque['promedio_terminal'] = $bloque['terminales'] > 0
                ? $bloque['ventas'] / $bloque['terminales']
                : 0.0;

            return $bloque;
        });

        $balanceLoterias = $resumen['tradicional']['balance_general']
            + $resumen['no_tradicional']['balance_general'];

        return [
            'terminales_analizadas' => $filas->unique('terminal')->count(),
            'balance_loterias' => $balanceLoterias,
            'ventas_recargas' => $resumen['recargas']['total_vendido'],
            'ventas_externas' => $resumen['ventas_externas']['total_vendido'],
            'balance_general_neto' => $balanceLoterias
                + $resumen['recargas']['total_vendido']
                + $resumen['ventas_externas']['total_vendido'],
            'bloques' => $bloques->all(),
        ];
    }

    /**
     * @return Collection<int, array<string, bool|float|string|null>>
     */
    private function leerCsv(UploadedFile $archivo, string $campoArchivo): Collection
    {
        $path = $archivo->getRealPath();

        if ($path === false) {
            throw ValidationException::withMessages([
                $campoArchivo => 'No se pudo leer el documento cargado.',
            ]);
        }

        $separador = $this->detectarSeparador($path);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages([
                $campoArchivo => 'No se pudo abrir el documento cargado.',
            ]);
        }

        try {
            $encabezados = fgetcsv($handle, 0, $separador);

            if ($encabezados === false || count($encabezados) <= max(self::COLUMNAS)) {
                throw ValidationException::withMessages([
                    $campoArchivo => 'El documento no contiene las columnas requeridas desde C hasta P.',
                ]);
            }

            $filas = collect();

            while (($fila = fgetcsv($handle, 0, $separador)) !== false) {
                $terminal = $this->limpiarTexto($fila[self::COLUMNAS['terminal']] ?? '');

                if ($terminal === '') {
                    continue;
                }

                $codigoTerminal = $this->extraerCodigoTerminal($terminal);
                $registro = [
                    'terminal' => $terminal,
                    'codigo_terminal' => $codigoTerminal,
                ];

                foreach (array_diff_key(self::COLUMNAS, ['terminal' => true]) as $campo => $indice) {
                    $registro[$campo] = $this->limpiarMonto($fila[$indice] ?? 0);
                }

                $filas->push($registro);
            }

            return $this->agregarDatosAgencia($filas);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  Collection<int, array<string, bool|float|string|null>>  $filas
     * @return Collection<int, array<string, bool|float|string|null>>
     */
    private function agregarDatosAgencia(Collection $filas): Collection
    {
        $terminales = $filas
            ->pluck('codigo_terminal')
            ->filter()
            ->map(fn (string $codigo): string => '0'.$codigo)
            ->unique()
            ->values();

        $agencias = $terminales
            ->chunk(500)
            ->flatMap(fn (Collection $grupo): Collection => DB::connection('crm')
                ->table('agencias')
                ->select(['terminal', 'ciudad', 'ruta'])
                ->whereIn('terminal', $grupo->all())
                ->get())
            ->keyBy(fn (object $agencia): string => trim((string) $agencia->terminal));

        return $filas->map(function (array $fila) use ($agencias): array {
            $terminalNormalizada = $fila['codigo_terminal'] !== null
                ? '0'.$fila['codigo_terminal']
                : null;
            $agencia = $terminalNormalizada !== null ? $agencias->get($terminalNormalizada) : null;

            $fila['terminal_crm'] = $terminalNormalizada;
            $fila['agencia_encontrada'] = $agencia !== null;
            $fila['ciudad'] = $this->valorUbicacion($agencia?->ciudad);
            $fila['ruta'] = $this->valorUbicacion($agencia?->ruta);

            return $fila;
        });
    }

    private function extraerCodigoTerminal(string $terminal): ?string
    {
        if (preg_match('/\((\d+)\)\s*$/', $terminal, $coincidencias) !== 1) {
            return null;
        }

        return $coincidencias[1];
    }

    private function valorUbicacion(mixed $valor): ?string
    {
        $texto = $this->limpiarTexto($valor);

        return $texto !== '' ? $texto : null;
    }

    private function detectarSeparador(string $path): string
    {
        $primeraLinea = '';
        $handle = fopen($path, 'rb');

        if ($handle !== false) {
            $primeraLinea = (string) fgets($handle);
            fclose($handle);
        }

        $resultados = collect([',', ';', "\t", '|'])
            ->mapWithKeys(fn (string $separador): array => [
                $separador => count(str_getcsv($primeraLinea, $separador)),
            ]);

        return (string) $resultados->sortDesc()->keys()->first();
    }

    private function limpiarTexto(mixed $valor): string
    {
        $texto = trim(preg_replace('/\s+/', ' ', (string) $valor) ?? '');

        if ($texto !== '' && ! mb_check_encoding($texto, 'UTF-8')) {
            $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
        }

        return $texto;
    }

    private function limpiarMonto(mixed $valor): float
    {
        $texto = trim((string) $valor);

        if ($texto === '') {
            return 0.0;
        }

        $esNegativo = str_starts_with($texto, '(') && str_ends_with($texto, ')');
        $numero = (float) str_replace([',', '(', ')', 'RD$', '$', ' '], '', $texto);

        return $esNegativo ? -$numero : $numero;
    }
}
