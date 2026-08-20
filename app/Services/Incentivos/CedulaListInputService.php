<?php

namespace App\Services\Incentivos;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class CedulaListInputService
{
    /** @return array{cedulas: list<string>, invalidas: list<string>} */
    public function extract(
        ?string $cedula,
        ?string $cedulasManual,
        ?UploadedFile $archivo
    ): array {
        $valores = collect();

        if (filled($cedula)) {
            $valores->push(['valor' => $cedula, 'archivo' => false]);
        }

        if (filled($cedulasManual)) {
            $valores->push(...collect(preg_split('/[\s,;]+/', $cedulasManual) ?: [])
                ->filter()
                ->map(fn (string $valor): array => ['valor' => $valor, 'archivo' => false]));
        }

        if ($archivo !== null) {
            $valores->push(...$this->fileValues($archivo));
        }

        $normalizados = $valores
            ->map(fn (array $item): array => [
                'original' => trim((string) $item['valor']),
                'cedula' => $this->normalize($item['valor'], $item['archivo']),
            ])
            ->reject(fn (array $item): bool => $item['original'] === '' || $this->isHeader($item['original']));

        return [
            'cedulas' => $normalizados
                ->pluck('cedula')
                ->filter(fn (?string $valor): bool => $valor !== null)
                ->unique()
                ->values()
                ->all(),
            'invalidas' => $normalizados
                ->filter(fn (array $item): bool => $item['cedula'] === null)
                ->pluck('original')
                ->unique()
                ->values()
                ->all(),
        ];
    }

    /** @return Collection<int, array{valor: mixed, archivo: bool}> */
    private function fileValues(UploadedFile $archivo): Collection
    {
        return Excel::toCollection(null, $archivo)
            ->flatMap(fn (Collection $hoja): Collection => $hoja)
            ->map(fn (mixed $fila): mixed => collect($fila)->first())
            ->filter(fn (mixed $valor): bool => filled($valor))
            ->map(fn (mixed $valor): array => ['valor' => $valor, 'archivo' => true])
            ->values();
    }

    private function normalize(mixed $valor, bool $fromFile): ?string
    {
        if ($fromFile && is_numeric($valor)) {
            $digitos = number_format((float) $valor, 0, '', '');
            $digitos = str_pad($digitos, 11, '0', STR_PAD_LEFT);
        } else {
            $digitos = preg_replace('/\D+/', '', (string) $valor) ?? '';
        }

        return preg_match('/^\d{11}$/', $digitos) === 1 ? $digitos : null;
    }

    private function isHeader(string $valor): bool
    {
        return in_array(strtolower(trim($valor)), ['cedula', 'cédula', 'documento'], true);
    }
}
