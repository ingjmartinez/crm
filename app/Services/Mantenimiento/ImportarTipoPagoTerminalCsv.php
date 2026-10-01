<?php

namespace App\Services\Mantenimiento;

use App\Models\TipoPagoTerminalDia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportarTipoPagoTerminalCsv
{
    /**
     * @return array{cargadas: int, omitidas: int}
     */
    public function importar(UploadedFile $archivo, string $fecha, ?int $usuarioId): array
    {
        $stream = fopen($archivo->getRealPath(), 'rb');

        if ($stream === false) {
            throw ValidationException::withMessages(['archivo' => 'No se pudo leer el archivo CSV.']);
        }

        try {
            $header = fgetcsv($stream);
            if (! is_array($header) || count($header) < 23
                || trim((string) ($header[1] ?? '')) !== 'Textbox40'
                || trim((string) ($header[22] ?? '')) !== 'Textbox34') {
                throw ValidationException::withMessages([
                    'archivo' => 'El CSV debe tener terminal en la columna B (Textbox40) y tipo de pago en la W (Textbox34).',
                ]);
            }

            $rowsByTerminal = [];
            $omitidas = 0;
            $fila = 1;

            while (($columns = fgetcsv($stream)) !== false) {
                $fila++;
                $terminal = trim((string) ($columns[1] ?? ''));
                if ($terminal === '') {
                    $omitidas++;

                    continue;
                }

                $original = trim((string) ($columns[22] ?? ''));
                if (! preg_match('/^\d{1,50}$/', $terminal)
                    || ! preg_match('/^(60|70|80)(?:\b|-)/', $original, $matches)) {
                    throw ValidationException::withMessages([
                        'archivo' => "Fila {$fila}: terminal o tipo de pago inválido en las columnas B y W.",
                    ]);
                }

                $terminal = ltrim($terminal, '0') ?: '0';
                if (isset($rowsByTerminal[$terminal]) && $rowsByTerminal[$terminal]['tipo_pago_original'] !== $original) {
                    throw ValidationException::withMessages([
                        'archivo' => "Fila {$fila}: la terminal {$terminal} aparece con dos tipos de pago distintos.",
                    ]);
                }

                $rowsByTerminal[$terminal] = [
                    'fecha' => $fecha,
                    'terminal' => $terminal,
                    'tipo_pago' => (int) $matches[1],
                    'tipo_pago_original' => $original,
                    'archivo_origen' => $archivo->getClientOriginalName(),
                    'cargado_por_id' => $usuarioId,
                ];
            }
        } finally {
            fclose($stream);
        }

        if ($rowsByTerminal === []) {
            throw ValidationException::withMessages(['archivo' => 'El CSV no contiene terminales válidas.']);
        }

        DB::transaction(function () use ($rowsByTerminal): void {
            foreach (array_chunk(array_values($rowsByTerminal), 500) as $rows) {
                TipoPagoTerminalDia::query()->upsert(
                    $rows,
                    ['fecha', 'terminal'],
                    ['tipo_pago', 'tipo_pago_original', 'archivo_origen', 'cargado_por_id', 'updated_at']
                );
            }
        });

        return ['cargadas' => count($rowsByTerminal), 'omitidas' => $omitidas];
    }
}
