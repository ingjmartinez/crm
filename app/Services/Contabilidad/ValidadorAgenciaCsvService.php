<?php

namespace App\Services\Contabilidad;

use App\Models\CentroDeCosto;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ValidadorAgenciaCsvService
{
    /**
     * @return array{
     *   filas: array<int, array<string, mixed>>,
     *   control: array<string, int>
     * }
     */
    public function procesar(UploadedFile $archivo): array
    {
        $path = $archivo->getRealPath();
        if ($path === false) {
            $this->fallar('No se pudo leer el archivo cargado.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $this->fallar('No se pudo abrir el archivo cargado.');
        }

        $control = [
            'filas_leidas' => 0,
            'filas_validas' => 0,
            'filas_vacias' => 0,
            'duplicadas' => 0,
            'conflictos_archivo' => 0,
        ];
        $filas = [];
        $separador = $this->detectarSeparador($path);

        try {
            $headers = fgetcsv($handle, 0, $separador);
            if ($headers === false) {
                $this->fallar('El archivo no contiene encabezados válidos.');
            }

            $columnas = $this->mapearColumnas($headers);
            while (($row = fgetcsv($handle, 0, $separador)) !== false) {
                $control['filas_leidas']++;
                $terminal = $this->limpiarTexto($row[$columnas['terminal']] ?? '');
                $nombreAgencia = $this->limpiarTexto($row[$columnas['nombre_agencia']] ?? '');
                $sociedad = $this->limpiarTexto($row[$columnas['sociedad']] ?? '');
                $ruta = $this->limpiarTexto($row[$columnas['ruta']] ?? '');

                if ($terminal === '' && $nombreAgencia === '' && $sociedad === '' && $ruta === '') {
                    $control['filas_vacias']++;

                    continue;
                }

                if ($terminal === '' || $nombreAgencia === '' || $sociedad === '' || $ruta === '') {
                    $this->fallar("La fila {$control['filas_leidas']} tiene uno de los campos requeridos vacío.");
                }

                $companyId = $this->companyIdDesdeSociedad($sociedad);
                $terminalNormalizada = $this->normalizarTerminal($terminal);
                $key = "{$companyId}|{$terminalNormalizada}";
                $fila = [
                    'terminal' => $terminal,
                    'terminal_normalizada' => $terminalNormalizada,
                    'nombre_agencia' => $nombreAgencia,
                    'sociedad' => $sociedad,
                    'ruta' => $ruta,
                    'company_id' => $companyId,
                ];

                if (isset($filas[$key])) {
                    $control['duplicadas']++;

                    if ($this->normalizarNombre($filas[$key]['nombre_agencia']) !== $this->normalizarNombre($nombreAgencia)) {
                        $filas[$key]['conflicto_archivo'] = true;
                        $filas[$key]['observacion'] = 'La terminal aparece varias veces en el archivo con nombres diferentes.';
                        $control['conflictos_archivo']++;
                    }

                    continue;
                }

                $filas[$key] = $fila;
                $control['filas_validas']++;
            }
        } finally {
            fclose($handle);
        }

        return [
            'filas' => $this->clasificar(array_values($filas)),
            'control' => $control,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     * @return array<int, array<string, mixed>>
     */
    public function clasificar(array $filas): array
    {
        $filasCollection = collect($filas);
        $centros = collect();

        foreach ($filasCollection->groupBy('company_id') as $companyId => $filasEmpresa) {
            $terminales = $filasEmpresa->pluck('terminal_normalizada')->unique()->flip();

            CentroDeCosto::query()
                ->where('company_id', 'like', $companyId.'%')
                ->whereNotNull('id_viejo')
                ->get([
                    'id',
                    'id_centro_costo',
                    'company_id',
                    'descripcion',
                    'id_viejo',
                    'id_grupo',
                    'id_sociedad',
                ])
                ->filter(fn (CentroDeCosto $centro): bool => $terminales->has(
                    $this->normalizarTerminal($centro->id_viejo)
                ))
                ->each(function (CentroDeCosto $centro) use ($centros, $companyId): void {
                    $key = $companyId.'|'.$this->normalizarTerminal($centro->id_viejo);
                    $centros->push(['key' => $key, 'centro' => $centro]);
                });
        }

        $centrosPorTerminal = $centros->groupBy('key');

        return $filasCollection
            ->map(function (array $fila) use ($centrosPorTerminal): array {
                if (($fila['conflicto_archivo'] ?? false) === true) {
                    return [
                        ...$fila,
                        'estado' => 'conflicto_archivo',
                        'centro_costo_id' => null,
                        'nombre_centro_costo' => null,
                        'ruta_centro_costo' => null,
                        'sociedad_centro_costo' => null,
                    ];
                }

                $matches = $centrosPorTerminal->get(
                    $fila['company_id'].'|'.$fila['terminal_normalizada'],
                    collect()
                );

                if ($matches->isEmpty()) {
                    return [
                        ...$fila,
                        'estado' => 'nuevo',
                        'centro_costo_id' => null,
                        'nombre_centro_costo' => null,
                        'ruta_centro_costo' => null,
                        'sociedad_centro_costo' => null,
                        'observacion' => 'La terminal no existe en Centros de Costo.',
                    ];
                }

                if ($matches->count() > 1) {
                    return [
                        ...$fila,
                        'estado' => 'conflicto_centro',
                        'centro_costo_id' => null,
                        'nombre_centro_costo' => $matches
                            ->map(fn (array $match): string => (string) $match['centro']->descripcion)
                            ->unique()
                            ->implode(' | '),
                        'ruta_centro_costo' => $matches
                            ->map(fn (array $match): string => (string) $match['centro']->id_grupo)
                            ->unique()
                            ->implode(' | '),
                        'sociedad_centro_costo' => $matches
                            ->map(fn (array $match): string => (string) $match['centro']->id_sociedad)
                            ->unique()
                            ->implode(' | '),
                        'observacion' => 'Existen varios Centros de Costo para esta terminal y empresa.',
                    ];
                }

                /** @var CentroDeCosto $centro */
                $centro = $matches->first()['centro'];
                $coincideNombre = $this->normalizarTexto($centro->descripcion)
                    === $this->normalizarNombre($fila['nombre_agencia']);
                $coincideRuta = $this->normalizarTexto($centro->id_grupo)
                    === $this->normalizarTexto($fila['ruta']);
                $coincideSociedad = $this->normalizarTexto($centro->id_sociedad)
                    === $this->normalizarTexto($fila['sociedad']);
                $camposDiferentes = collect([
                    'nombre' => $coincideNombre,
                    'ruta' => $coincideRuta,
                    'sociedad' => $coincideSociedad,
                ])->reject()->keys()->values()->all();
                $estado = match (count($camposDiferentes)) {
                    0 => 'correcto',
                    1 => $camposDiferentes[0].'_diferente',
                    default => implode('_', $camposDiferentes).'_diferentes',
                };

                return [
                    ...$fila,
                    'estado' => $estado,
                    'centro_costo_id' => $centro->id,
                    'nombre_centro_costo' => $centro->descripcion,
                    'ruta_centro_costo' => $centro->id_grupo,
                    'sociedad_centro_costo' => $centro->id_sociedad,
                    'observacion' => $estado === 'correcto'
                        ? 'La terminal, el nombre, la ruta y la sociedad coinciden.'
                        : 'Diferencias detectadas en: '.implode(', ', $camposDiferentes).'.',
                ];
            })
            ->sortBy([
                ['estado', 'asc'],
                ['terminal_normalizada', 'asc'],
            ])
            ->values()
            ->all();
    }

    public function normalizarTerminal(mixed $terminal): string
    {
        $terminal = trim((string) $terminal);
        $normalizada = ltrim($terminal, '0');

        return $normalizada === '' ? '0' : $normalizada;
    }

    public function companyIdDesdeSociedad(mixed $sociedad): string
    {
        return Str::contains((string) $sociedad, 'NEGOSUR', ignoreCase: true) ? '169' : '168';
    }

    private function normalizarNombre(mixed $nombre): string
    {
        return $this->normalizarTexto($nombre);
    }

    private function normalizarTexto(mixed $value): string
    {
        return Str::of($this->limpiarTexto($value))->lower()->value();
    }

    private function detectarSeparador(string $path): string
    {
        $lineas = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $line = (string) ($lineas[0] ?? '');
        if ($line === '') {
            $this->fallar('El archivo está vacío.');
        }
        $separadores = [
            ',' => substr_count($line, ','),
            ';' => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
        ];
        arsort($separadores);

        return (string) array_key_first($separadores);
    }

    /**
     * @param  array<int, string|null>  $headers
     * @return array<string, int>
     */
    private function mapearColumnas(array $headers): array
    {
        $normalizados = collect($headers)
            ->mapWithKeys(fn ($header, int $index): array => [
                Str::lower(trim((string) $header, " \t\n\r\0\x0B\xEF\xBB\xBF")) => $index,
            ]);
        $requeridas = [
            'terminal' => 'textbox40',
            'nombre_agencia' => 'banca',
            'sociedad' => 'grupo',
            'ruta' => 'ruta',
        ];
        $faltantes = collect($requeridas)
            ->filter(fn (string $header): bool => ! $normalizados->has($header))
            ->values();

        if ($faltantes->isNotEmpty()) {
            $this->fallar('Faltan estas columnas requeridas: '.$faltantes->implode(', ').'.');
        }

        return collect($requeridas)
            ->map(fn (string $header): int => (int) $normalizados->get($header))
            ->all();
    }

    private function limpiarTexto(mixed $value): string
    {
        $text = trim((string) $value);
        if ($text !== '' && ! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }

        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }

    private function fallar(string $mensaje): never
    {
        throw ValidationException::withMessages([
            'archivo_csv' => $mensaje,
        ]);
    }
}
