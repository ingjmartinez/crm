<?php

namespace App\Services\Contabilidad;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class VolantePagoSocioBanreservasService
{
    public function __construct(private readonly VolantePagoSocioCsvService $csvService) {}

    /** @return array{carga: array<string, mixed>, detalles: array<int, array<string, mixed>>} */
    public function procesar(UploadedFile $archivo): array
    {
        $ruta = $archivo->getRealPath();
        if ($ruta === false) {
            throw ValidationException::withMessages(['archivo_csv' => 'No se pudo leer el archivo cargado.']);
        }

        try {
            $libro = IOFactory::load($ruta);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'No se pudo abrir el archivo de Banreservas. Verifica que sea un Excel válido.',
            ]);
        }

        try {
            $hoja = $libro->getSheetByName('Movimientos') ?? $libro->getActiveSheet();
            $filaInformacion = $this->buscarFila($hoja, [
                'A' => 'fecha',
                'C' => 'tipo de transaccion',
                'E' => 'usuario',
            ]);
            $filaResumen = $this->buscarFila($hoja, [
                'A' => 'producto debito',
                'B' => 'importe',
                'C' => 'importe impuesto',
                'D' => 'fecha',
                'E' => 'tipo de transaccion',
                'F' => 'estado',
            ]);
            $filaDetalles = $this->buscarFila($hoja, [
                'C' => 'producto credito',
                'D' => 'importe',
                'E' => 'descripcion',
                'F' => 'estado',
            ]);

            if ($filaInformacion === null || $filaResumen === null || $filaDetalles === null) {
                throw ValidationException::withMessages([
                    'archivo_csv' => 'El archivo no contiene la estructura esperada de Pago de Nómina Masivo de Banreservas.',
                ]);
            }

            $filaResumenDatos = $filaResumen + 1;
            [, $cuentaOrigen] = $this->cuenta(
                (string) $hoja->getCell("A{$filaResumenDatos}")->getFormattedValue(),
                true,
            );
            $detalles = $this->detalles($hoja, $filaDetalles + 1);
            $montoTotal = $this->monto((string) $hoja->getCell("B{$filaResumenDatos}")->getFormattedValue());
            $sumaDetalles = round((float) collect($detalles)->sum('monto'), 2);

            if (abs($montoTotal - $sumaDetalles) > 0.01) {
                throw ValidationException::withMessages([
                    'archivo_csv' => 'El total del archivo de Banreservas no coincide con la suma de los beneficiarios.',
                ]);
            }

            $carga = [
                'empresa_origen' => 'No especificada en el archivo',
                'rnc_origen' => null,
                'cuenta_origen' => $cuentaOrigen,
                'tipo_transaccion' => trim((string) $hoja->getCell("E{$filaResumenDatos}")->getFormattedValue()),
                'estado' => trim((string) $hoja->getCell("F{$filaResumenDatos}")->getFormattedValue()),
                'monto_total' => $montoTotal,
                'impuesto_total' => $this->monto((string) $hoja->getCell("C{$filaResumenDatos}")->getFormattedValue()),
                'fecha_transaccion' => $this->fecha($hoja, 'A'.($filaInformacion + 1)),
                'cantidad_transacciones' => count($detalles),
            ];
            $carga['huella_contenido'] = $this->csvService->huellaContenido($carga, $detalles);

            return [
                'carga' => $carga,
                'detalles' => $detalles,
            ];
        } finally {
            $libro->disconnectWorksheets();
        }
    }

    /** @param array<string, string> $cabeceras */
    private function buscarFila(Worksheet $hoja, array $cabeceras): ?int
    {
        for ($fila = 1; $fila <= $hoja->getHighestDataRow(); $fila++) {
            $coincide = collect($cabeceras)->every(
                fn (string $valor, string $columna): bool => $this->normalizar(
                    (string) $hoja->getCell("{$columna}{$fila}")->getFormattedValue()
                ) === $valor
            );

            if ($coincide) {
                return $fila;
            }
        }

        return null;
    }

    /** @return array<int, array<string, mixed>> */
    private function detalles(Worksheet $hoja, int $primeraFila): array
    {
        $detalles = [];

        for ($fila = $primeraFila; $fila <= $hoja->getHighestDataRow(); $fila++) {
            $producto = trim((string) $hoja->getCell("C{$fila}")->getFormattedValue());
            if ($this->normalizar($producto) === 'nombre usuario') {
                break;
            }

            if ($producto === '') {
                continue;
            }

            [$tipoCuenta, $cuenta] = $this->cuenta($producto);
            $nombre = trim((string) $hoja->getCell("E{$fila}")->getFormattedValue());
            $estado = trim((string) $hoja->getCell("F{$fila}")->getFormattedValue());
            $monto = $this->monto((string) $hoja->getCell("D{$fila}")->getFormattedValue());

            if ($nombre === '' || $estado === '' || $monto <= 0) {
                throw ValidationException::withMessages([
                    'archivo_csv' => "La fila {$fila} de beneficiarios está incompleta.",
                ]);
            }

            $detalles[] = [
                'numero_linea' => count($detalles) + 1,
                'nombre' => $nombre,
                'tipo_identificacion' => null,
                'identificacion' => null,
                'cuenta' => $cuenta,
                'tipo_cuenta' => $tipoCuenta,
                'monto' => $monto,
                'estado' => $estado,
            ];
        }

        if ($detalles === []) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'El archivo de Banreservas no contiene beneficiarios para generar volantes.',
            ]);
        }

        return $detalles;
    }

    /** @return array{0: string, 1: string} */
    private function cuenta(string $producto, bool $permiteMoneda = false): array
    {
        $moneda = $permiteMoneda ? '(?:\s*-\s*[A-Z]{3})?' : '';
        if (preg_match('/^(.*?)\s*-\s*([0-9]+)'.$moneda.'$/u', trim($producto), $coincidencias) !== 1) {
            throw ValidationException::withMessages([
                'archivo_csv' => "No se pudo interpretar el producto bancario: {$producto}.",
            ]);
        }

        return [trim($coincidencias[1]), trim($coincidencias[2])];
    }

    private function fecha(Worksheet $hoja, string $celda): CarbonImmutable
    {
        $valor = $hoja->getCell($celda)->getValue();
        if (is_numeric($valor)) {
            return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $valor));
        }

        try {
            return CarbonImmutable::createFromFormat('j/n/Y H:i:s', trim((string) $valor));
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'La fecha del archivo de Banreservas no tiene el formato esperado.',
            ]);
        }
    }

    private function monto(string $valor): float
    {
        return round((float) preg_replace('/[^0-9.-]/', '', $valor), 2);
    }

    private function normalizar(string $valor): string
    {
        return Str::of($valor)->ascii()->lower()->squish()->toString();
    }
}
