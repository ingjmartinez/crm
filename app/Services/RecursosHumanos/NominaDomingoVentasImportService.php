<?php

namespace App\Services\RecursosHumanos;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use ZipArchive;

class NominaDomingoVentasImportService
{
    private const IMPORT_CHUNK_SIZE = 1000;

    private string $fechaNomina;

    public function importar(UploadedFile $tradicional, UploadedFile $noTradicional, Carbon $fecha): void
    {
        set_time_limit(300);
        DB::connection()->disableQueryLog();
        $this->fechaNomina = $fecha->toDateString();

        DB::transaction(function () use ($tradicional, $noTradicional): void {
            DB::table('nomina_domingo_ventas')->whereDate('fecha_transaccion', $this->fechaNomina)->delete();

            $this->limpiarArchivo($tradicional, 'Tradicional', [
                'fecha' => 'Fecha',
                'agencia' => 'Agencia',
                'total_apostado' => 'Total Apostado',
                'estatus' => 'Estatus',
                'terminal' => 'Terminal',
                'usuario_venta' => 'Usr. Venta',
            ]);
            $this->verificarVentas('Tradicional');

            $this->limpiarArchivo($noTradicional, 'No Tradicional', [
                'agencia' => 'Agencia',
                'estatus' => 'Estatus',
                'fecha' => 'Fecha',
                'terminal' => 'Id Terminal',
                'usuario_venta' => 'Usr. Venta',
                'total_apostado' => 'Total Apostado',
            ]);
            $this->verificarVentas('No Tradicional');
        });
    }

    private function verificarVentas(string $tipo): void
    {
        $tieneVentas = DB::table('nomina_domingo_ventas')
            ->whereDate('fecha_transaccion', $this->fechaNomina)
            ->where('tipo', $tipo)
            ->exists();

        if (! $tieneVentas) {
            throw ValidationException::withMessages([
                $this->campoArchivo($tipo) => 'El archivo '.$tipo.' no contiene ventas válidas para el domingo '.$this->fechaNomina.'.',
            ]);
        }
    }

    private function fechaSql($valor): ?string
    {
        $valor = $this->limpiarTexto($valor);

        if ($valor === '') {
            return null;
        }

        try {
            return Carbon::parse($valor)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function limpiarArchivo(UploadedFile $archivo, string $tipo, array $columnasRequeridas): void
    {
        $path = $archivo->getRealPath();

        if (! $path) {
            throw ValidationException::withMessages([
                $this->campoArchivo($tipo) => 'No se pudo leer el archivo '.$tipo.'.',
            ]);
        }

        if ($this->esCsv($archivo)) {
            $this->limpiarCsv($archivo, $tipo, $columnasRequeridas);

            return;
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages([
                $this->campoArchivo($tipo) => 'No se pudo abrir el archivo '.$tipo.'.',
            ]);
        }

        try {
            $sheetPath = $this->obtenerPrimeraHoja($zip);
            $sharedStrings = $this->leerSharedStrings($zip);
            $sheetXml = $zip->getFromName($sheetPath);

            if ($sheetXml === false) {
                throw ValidationException::withMessages([
                    $this->campoArchivo($tipo) => 'No se pudo leer la hoja principal del archivo '.$tipo.'.',
                ]);
            }

            $headers = $this->leerPrimeraFila($sheetXml, $sharedStrings);
            $mapa = $this->mapearColumnas($headers, $columnasRequeridas, $tipo);
            $columnasPermitidas = array_flip(array_map(fn ($column) => $this->indiceColumna($column), array_values($mapa)));
            $filas = [];

            foreach ($this->iterarFilas($sheetXml) as $rowNumber => $rowXml) {
                if ($rowNumber <= 1) {
                    continue;
                }

                $row = $this->parsearFilaXml($rowXml, $sharedStrings, $columnasPermitidas);
                if ($this->filaVacia($row)) {
                    continue;
                }

                $this->agregarFilaProcesada(
                    $this->normalizarFilaReporte($row, $mapa, $tipo),
                    $filas
                );
            }

            $this->insertarFilasReporte($filas);
        } finally {
            $zip->close();
        }
    }

    private function limpiarCsv(UploadedFile $archivo, string $tipo, array $columnasRequeridas): void
    {
        $path = $archivo->getRealPath();

        if (! $path) {
            throw ValidationException::withMessages([
                $this->campoArchivo($tipo) => 'No se pudo leer el archivo '.$tipo.'.',
            ]);
        }

        $delimiter = $this->detectarSeparador($path);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages([
                $this->campoArchivo($tipo) => 'No se pudo abrir el archivo '.$tipo.'.',
            ]);
        }

        try {
            $primeraLinea = fgets($handle);
            $headers = $primeraLinea !== false ? str_getcsv($primeraLinea, $delimiter) : false;

            if (! $headers) {
                throw ValidationException::withMessages([
                    $this->campoArchivo($tipo) => 'El archivo '.$tipo.' no contiene encabezados validos.',
                ]);
            }

            [$headers, $primeraFila] = $this->normalizarPrimeraLineaCsv($headers, $tipo);
            $mapa = $this->mapearColumnasCsv($headers, $columnasRequeridas, $tipo);
            $filas = [];

            if ($primeraFila !== null) {
                $this->agregarFilaCsv($primeraFila, $mapa, $tipo, $filas);
            }

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $this->agregarFilaCsv($row, $mapa, $tipo, $filas);
            }

            $this->insertarFilasReporte($filas);
        } finally {
            fclose($handle);
        }
    }

    private function agregarFilaCsv(array $row, array $mapa, string $tipo, array &$filas): void
    {
        if ($this->filaVacia($row)) {
            return;
        }

        $this->agregarFilaProcesada(
            $this->normalizarFilaReporte($row, $mapa, $tipo),
            $filas
        );
    }

    private function normalizarFilaReporte(array $row, array $mapa, string $tipo): ?array
    {
        $estatus = $this->limpiarTexto($row[$mapa['estatus']] ?? '');
        if ($estatus === '' || strcasecmp($estatus, 'Validos') !== 0) {
            return null;
        }

        $fecha = $this->normalizarFecha($row[$mapa['fecha']] ?? '');
        $fechaArchivo = $this->fechaSql($fecha['orden'] ?? null);
        if ($fechaArchivo === null || substr($fechaArchivo, 0, 10) !== $this->fechaNomina) {
            $fechaEncontrada = $fechaArchivo === null ? 'inválida' : substr($fechaArchivo, 0, 10);
            throw ValidationException::withMessages([
                $this->campoArchivo($tipo) => 'El archivo '.$tipo.' contiene una venta válida con fecha '.$fechaEncontrada.'; solo se permite el domingo '.$this->fechaNomina.'.',
            ]);
        }

        $agencia = $this->limpiarTexto($row[$mapa['agencia']] ?? '');
        $terminal = $this->formatearTerminalConsulta($row[$mapa['terminal']] ?? '');
        $usuarioVenta = $this->limpiarTexto($row[$mapa['usuario_venta']] ?? '');
        $totalApostado = $this->limpiarMonto($row[$mapa['total_apostado']] ?? 0);

        if ($agencia === '' && $terminal === '' && $usuarioVenta === '' && $totalApostado == 0.0) {
            return null;
        }

        return [
            'tipo' => $tipo,
            'fecha_transaccion' => $this->fechaSql($fecha['orden'] ?? null),
            'fecha_texto' => $fecha['texto'],
            'agencia' => $agencia,
            'terminal' => $terminal,
            'terminal_clave' => $this->claveAgenciaReporte($terminal, $agencia),
            'usuario_venta' => $usuarioVenta,
            'total_apostado' => $totalApostado,
            'estatus' => 'Validos',
        ];
    }

    private function agregarFilaProcesada(?array $fila, array &$filas): void
    {
        if ($fila === null) {
            return;
        }

        $filas[] = $fila;

        if (count($filas) >= self::IMPORT_CHUNK_SIZE) {
            $this->insertarFilasReporte($filas);
            $filas = [];
        }
    }

    private function insertarFilasReporte(array $filas): void
    {
        if ($filas === []) {
            return;
        }

        $now = now();
        $rows = array_map(function ($fila) use ($now) {
            $fila['created_at'] = $now;
            $fila['updated_at'] = $now;

            return $fila;
        }, $filas);

        DB::table('nomina_domingo_ventas')->insert($rows);
    }

    private function formatearTerminalConsulta($valor): string
    {
        $terminal = $this->limpiarTexto($valor);

        if ($terminal === '' || str_starts_with($terminal, '0')) {
            return $terminal;
        }

        return '0'.$terminal;
    }

    private function claveAgenciaReporte(string $terminal, string $agencia): string
    {
        $terminal = preg_replace('/\D+/', '', $terminal) ?? '';
        $terminal = ltrim($terminal, '0');

        if ($terminal !== '') {
            return $terminal;
        }

        return $this->normalizarEncabezado($agencia);
    }

    private function normalizarPrimeraLineaCsv(array $headers, string $tipo): array
    {
        $encabezadosEsperados = $this->encabezadosEsperadosCsv($tipo);
        $totalEsperados = count($encabezadosEsperados);

        if ($totalEsperados === 0 || count($headers) <= $totalEsperados) {
            return [$headers, null];
        }

        $ultimoEncabezado = $encabezadosEsperados[$totalEsperados - 1];
        $campoPegado = (string) ($headers[$totalEsperados - 1] ?? '');

        if (! $this->primeraLineaTieneRegistroPegado($headers, $encabezadosEsperados, $campoPegado, $ultimoEncabezado)) {
            return [$headers, null];
        }

        $primerValor = substr($campoPegado, strlen($ultimoEncabezado));
        $primeraFila = array_merge([$primerValor], array_slice($headers, $totalEsperados));

        return [$encabezadosEsperados, $primeraFila];
    }

    private function primeraLineaTieneRegistroPegado(array $headers, array $encabezadosEsperados, string $campoPegado, string $ultimoEncabezado): bool
    {
        foreach (array_slice($encabezadosEsperados, 0, -1) as $index => $encabezado) {
            if ($this->normalizarEncabezado((string) ($headers[$index] ?? '')) !== $this->normalizarEncabezado($encabezado)) {
                return false;
            }
        }

        return str_starts_with($campoPegado, $ultimoEncabezado) && strlen($campoPegado) > strlen($ultimoEncabezado);
    }

    private function encabezadosEsperadosCsv(string $tipo): array
    {
        if ($tipo === 'Tradicional') {
            return [
                'Fecha',
                'No. Ticket',
                'Grupo',
                'Agencia',
                'No. Agencia',
                'Total Apostado',
                'Total Ganado',
                'Usr. Anulo',
                'Fec. Anulo',
                'Usr. Pago',
                'Fec. Pago',
                'Estatus',
                'Orden',
                'Empresa',
                'Serial No.',
                'Terminal',
                'Empresa Pago',
                'Usr. Venta',
            ];
        }

        return [
            'Ticket',
            'Consorcio',
            'Grupo',
            'Agencia',
            'Estatus',
            'Fecha',
            'Id Terminal',
            'Serial',
            'Usr. Venta',
            'Total Apostado',
            'Total Ganado',
            'Usr. Pago',
            'Fec. Pago',
            'Empresa Pago',
            'Usr. Anulo',
            'Fec. Anulado',
        ];
    }

    private function obtenerPrimeraHoja(ZipArchive $zip): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $relsXml === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbook = simplexml_load_string($workbookXml);
        $rels = simplexml_load_string($relsXml);

        if (! $workbook || ! $rels) {
            return 'xl/worksheets/sheet1.xml';
        }

        $sheets = $workbook->sheets->sheet ?? [];
        $firstSheet = $sheets[0] ?? null;

        if (! $firstSheet) {
            return 'xl/worksheets/sheet1.xml';
        }

        $attributes = $firstSheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $relationshipId = (string) ($attributes['id'] ?? '');

        foreach ($rels->Relationship as $relationship) {
            if ((string) $relationship['Id'] !== $relationshipId) {
                continue;
            }

            $target = (string) $relationship['Target'];
            $target = str_replace('\\', '/', $target);

            if (str_starts_with($target, '/')) {
                return ltrim($target, '/');
            }

            return 'xl/'.ltrim($target, '/');
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private function leerSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $sharedStrings = [];
        $offset = 0;

        while (preg_match('/<si\b[^>]*>.*?<\/si>/s', $xml, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $sharedStrings[] = $this->extraerTextos($match[0][0]);
            $offset = $match[0][1] + strlen($match[0][0]);
        }

        return $sharedStrings;
    }

    private function leerPrimeraFila(string $sheetXml, array $sharedStrings): array
    {
        foreach ($this->iterarFilas($sheetXml) as $rowXml) {
            return $this->parsearFilaXml($rowXml, $sharedStrings);
        }

        return [];
    }

    private function parsearFilaXml(string $rowXml, array $sharedStrings, ?array $columnasPermitidas = null): array
    {
        $row = [];

        if (! preg_match_all('/<c\b([^>]*)>(.*?)<\/c>/s', $rowXml, $matches, PREG_SET_ORDER)) {
            return $row;
        }

        foreach ($matches as $match) {
            $attributes = $match[1];
            $content = $match[2];
            $reference = $this->atributoXml($attributes, 'r');

            if (! preg_match('/^([A-Z]+)/', $reference, $referenceMatch)) {
                continue;
            }

            $column = $referenceMatch[1];
            $columnIndex = $this->indiceColumna($column);

            if ($columnasPermitidas !== null && ! isset($columnasPermitidas[$columnIndex])) {
                continue;
            }

            $row[$column] = $this->valorCelda($attributes, $content, $sharedStrings);
        }

        return $row;
    }

    private function valorCelda(string $attributes, string $content, array $sharedStrings): string
    {
        $type = $this->atributoXml($attributes, 't');
        $value = preg_match('/<v>(.*?)<\/v>/s', $content, $match)
            ? html_entity_decode($match[1], ENT_QUOTES | ENT_XML1, 'UTF-8')
            : '';

        if ($type === 's') {
            $index = (int) $value;

            return (string) ($sharedStrings[$index] ?? '');
        }

        if ($type === 'inlineStr') {
            return $this->extraerTextos($content);
        }

        return $value;
    }

    private function iterarFilas(string $sheetXml): \Generator
    {
        $offset = 0;

        while (preg_match('/<row\b[^>]*r="(\d+)"[^>]*>.*?<\/row>/s', $sheetXml, $match, PREG_OFFSET_CAPTURE, $offset)) {
            yield (int) $match[1][0] => $match[0][0];
            $offset = $match[0][1] + strlen($match[0][0]);
        }
    }

    private function atributoXml(string $attributes, string $name): string
    {
        return preg_match('/\b'.preg_quote($name, '/').'="([^"]*)"/', $attributes, $match)
            ? html_entity_decode($match[1], ENT_QUOTES | ENT_XML1, 'UTF-8')
            : '';
    }

    private function indiceColumna(string $column): int
    {
        $index = 0;

        foreach (str_split($column) as $char) {
            $index = ($index * 26) + (ord($char) - 64);
        }

        return $index;
    }

    private function extraerTextos(string $xml): string
    {
        if (! preg_match_all('/<t(?:\s[^>]*)?>(.*?)<\/t>/s', $xml, $matches)) {
            return '';
        }

        return implode('', array_map(
            fn ($text) => html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            $matches[1]
        ));
    }

    private function mapearColumnas(array $headers, array $columnasRequeridas, string $tipo): array
    {
        $normalizados = collect($headers)
            ->mapWithKeys(fn ($header, $column) => [$this->normalizarEncabezado((string) $header) => $column]);

        $mapa = [];
        $faltantes = [];

        foreach ($columnasRequeridas as $clave => $nombre) {
            $columna = $normalizados->get($this->normalizarEncabezado($nombre));

            if ($columna === null) {
                $faltantes[] = $nombre;

                continue;
            }

            $mapa[$clave] = $columna;
        }

        if ($faltantes) {
            throw ValidationException::withMessages([
                $this->campoArchivo($tipo) => 'En '.$tipo.' faltan estas columnas: '.implode(', ', $faltantes).'.',
            ]);
        }

        return $mapa;
    }

    private function mapearColumnasCsv(array $headers, array $columnasRequeridas, string $tipo): array
    {
        $normalizados = collect($headers)
            ->mapWithKeys(fn ($header, $index) => [$this->normalizarEncabezado((string) $header) => $index]);

        $mapa = [];
        $faltantes = [];

        foreach ($columnasRequeridas as $clave => $nombre) {
            $columna = $normalizados->get($this->normalizarEncabezado($nombre));

            if ($columna === null) {
                $faltantes[] = $nombre;

                continue;
            }

            $mapa[$clave] = $columna;
        }

        if ($faltantes) {
            throw ValidationException::withMessages([
                $this->campoArchivo($tipo) => 'En '.$tipo.' faltan estas columnas: '.implode(', ', $faltantes).'.',
            ]);
        }

        return $mapa;
    }

    private function normalizarFecha($valor): array
    {
        if (is_numeric($valor)) {
            $fecha = ExcelDate::excelToDateTimeObject((float) $valor);

            return [
                'texto' => $fecha->format('d-m-Y h:i:s A'),
                'orden' => $fecha->format('Y-m-d H:i:s'),
            ];
        }

        if ($valor instanceof DateTimeInterface) {
            return [
                'texto' => $valor->format('d-m-Y h:i:s A'),
                'orden' => $valor->format('Y-m-d H:i:s'),
            ];
        }

        $texto = $this->limpiarTexto($valor);
        $formatos = ['d-m-Y h:i:s A', 'd-m-Y H:i:s', 'd/m/Y h:i:s A', 'd/m/Y H:i:s', 'Y-m-d H:i:s'];

        foreach ($formatos as $formato) {
            try {
                $fecha = Carbon::createFromFormat($formato, $texto);

                return [
                    'texto' => $fecha->format('d-m-Y h:i:s A'),
                    'orden' => $fecha->format('Y-m-d H:i:s'),
                ];
            } catch (\Throwable) {
                continue;
            }
        }

        return [
            'texto' => $texto,
            'orden' => $texto,
        ];
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

    private function filaVacia(array $row): bool
    {
        return collect($row)->every(fn ($value) => trim((string) $value) === '');
    }

    private function esCsv(UploadedFile $archivo): bool
    {
        return in_array(strtolower($archivo->getClientOriginalExtension()), ['csv', 'txt'], true);
    }

    private function campoArchivo(string $tipo): string
    {
        return $tipo === 'Tradicional' ? 'tradicional' : 'no_tradicional';
    }
}
