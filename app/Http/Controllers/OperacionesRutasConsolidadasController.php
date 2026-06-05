<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class OperacionesRutasConsolidadasController extends Controller
{
    public function index(): View
    {
        return view('operaciones.rutas-consolidadas', [
            'filas' => collect(),
            'resumen' => null,
            'nombreArchivo' => null,
        ]);
    }

    public function procesar(Request $request): View
    {
        $validated = $request->validate([
            'archivo_csv' => ['required', 'file', 'mimes:csv,txt', 'max:51200'],
        ]);

        /** @var UploadedFile $archivo */
        $archivo = $validated['archivo_csv'];
        $filas = $this->limpiarCsv($archivo);

        return view('operaciones.rutas-consolidadas', [
            'filas' => $filas,
            'resumen' => [
                'total_rutas' => $filas->count(),
                'total_deposito' => $filas->sum('deposito'),
                'total_retiro' => $filas->sum('retiro'),
                'total_neto' => $filas->sum('neto'),
            ],
            'nombreArchivo' => $archivo->getClientOriginalName(),
        ]);
    }

    private function limpiarCsv(UploadedFile $archivo)
    {
        $path = $archivo->getRealPath();

        if (!$path) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'No se pudo leer el archivo cargado.',
            ]);
        }

        $delimiter = $this->detectarSeparador($path);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'No se pudo abrir el archivo cargado.',
            ]);
        }

        $headers = fgetcsv($handle, 0, $delimiter);

        if (!$headers) {
            fclose($handle);
            throw ValidationException::withMessages([
                'archivo_csv' => 'El archivo no contiene encabezados validos.',
            ]);
        }

        $mapa = $this->mapearColumnas($headers);
        $filas = collect();

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($this->filaVacia($row)) {
                continue;
            }

            $ruta = $this->limpiarTexto($row[$mapa['ruta']] ?? '');

            if ($ruta === '' || strcasecmp($ruta, 'Total:') === 0) {
                continue;
            }

            $deposito = $this->limpiarMonto($row[$mapa['deposito']] ?? 0);
            $retiro = $this->limpiarMonto($row[$mapa['retiro']] ?? 0);

            if ($deposito == 0.0 && $retiro == 0.0) {
                continue;
            }

            $filas->push([
                'ruta' => $ruta,
                'deposito' => $deposito,
                'retiro' => $retiro,
                'neto' => $retiro - $deposito,
            ]);
        }

        fclose($handle);

        return $filas->values();
    }

    private function mapearColumnas(array $headers): array
    {
        $normalizados = collect($headers)
            ->mapWithKeys(fn($header, $index) => [$this->normalizarEncabezado((string) $header) => $index]);

        $ruta = $normalizados->get('entidad', $normalizados->get('ruta'));
        $deposito = $normalizados->get('ddepositos', $normalizados->get('deposito', $normalizados->get('depositos')));
        $retiro = $normalizados->get('retiros', $normalizados->get('retiro'));

        $faltantes = [];
        if ($ruta === null) {
            $faltantes[] = 'Ruta/Entidad';
        }
        if ($deposito === null) {
            $faltantes[] = 'Deposito/DDepositos';
        }
        if ($retiro === null) {
            $faltantes[] = 'Retiro/Retiros';
        }

        if ($faltantes) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'No se encontraron estas columnas requeridas: ' . implode(', ', $faltantes) . '.',
            ]);
        }

        return [
            'ruta' => $ruta,
            'deposito' => $deposito,
            'retiro' => $retiro,
        ];
    }

    private function detectarSeparador(string $path): string
    {
        $linea = '';
        $handle = fopen($path, 'rb');

        if ($handle !== false) {
            $linea = (string) fgets($handle);
            fclose($handle);
        }

        $separadores = [',' => 0, ';' => 0, "\t" => 0, '|' => 0];

        foreach (array_keys($separadores) as $separador) {
            $separadores[$separador] = count(str_getcsv($linea, $separador));
        }

        arsort($separadores);

        return (string) array_key_first($separadores);
    }

    private function normalizarEncabezado(string $valor): string
    {
        $valor = preg_replace('/^\xEF\xBB\xBF/', '', $valor) ?? $valor;
        $valor = strtolower(trim($valor));
        $valor = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor) ?: $valor;

        return preg_replace('/[^a-z0-9]+/', '', $valor) ?? $valor;
    }

    private function limpiarTexto($valor): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $valor) ?? '');
    }

    private function limpiarMonto($valor): float
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return 0.0;
        }

        $negativo = str_starts_with($valor, '(') && str_ends_with($valor, ')');
        $valor = str_replace(['(', ')', 'RD$', '$', ' '], '', $valor);
        $valor = str_replace(',', '', $valor);
        $numero = (float) $valor;

        return $negativo ? -1 * $numero : $numero;
    }

    private function filaVacia(array $row): bool
    {
        return collect($row)->every(fn($value) => trim((string) $value) === '');
    }
}
