<?php

namespace App\Services\Operaciones;

use App\Models\MovimientoRutaV2Importacion;
use App\Models\MovimientoRutaV2Transaccion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MovimientosRutasV2ImportService
{
    private const IMPORT_CHUNK_SIZE = 1000;

    public function __construct(
        private readonly MovimientosRutasCsvService $csvService,
        private readonly MovimientosRutasAgenciaService $agenciaService,
    ) {}

    /**
     * @return array{importacion: MovimientoRutaV2Importacion, fechas: array<int, string>, control: array<string, int>}
     */
    public function importar(UploadedFile $archivo, ?int $userId, string $fechaReporte): array
    {
        $resultado = $this->csvService->procesar($archivo);
        $transacciones = $this->agenciaService->enriquecer($resultado['transacciones']);
        $fechas = collect($transacciones)->pluck('fecha_iso')->unique()->sort()->values()->all();

        if ($fechas === []) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'El documento no contiene movimientos válidos para importar.',
            ]);
        }

        if (count($fechas) !== 1 || $fechas[0] !== $fechaReporte) {
            $fechaReporteLegible = Carbon::createFromFormat('Y-m-d', $fechaReporte)->format('d/m/Y');
            $fechasArchivo = collect($fechas)
                ->map(fn (string $fecha): string => Carbon::createFromFormat('Y-m-d', $fecha)->format('d/m/Y'))
                ->implode(', ');

            throw ValidationException::withMessages([
                'fecha_reporte' => "La fecha del reporte ({$fechaReporteLegible}) no corresponde con la fecha del archivo ({$fechasArchivo}). No se importó ningún movimiento.",
            ]);
        }

        $importacion = DB::transaction(function () use ($archivo, $userId, $resultado, $transacciones, $fechas): MovimientoRutaV2Importacion {
            MovimientoRutaV2Transaccion::query()->whereIn('fecha', $fechas)->delete();

            $importacion = MovimientoRutaV2Importacion::query()->create([
                'nombre_archivo' => $archivo->getClientOriginalName(),
                'fecha_desde' => $fechas[0],
                'fecha_hasta' => $fechas[array_key_last($fechas)],
                'fechas_reemplazadas' => count($fechas),
                'filas_aceptadas' => $resultado['control']['filas_aceptadas'],
                'filas_descartadas' => $resultado['control']['filas_descartadas'],
                'user_id' => $userId,
            ]);

            $now = now();
            $filas = array_map(static fn (array $transaccion): array => [
                'importacion_id' => $importacion->id,
                'fecha' => $transaccion['fecha_iso'],
                'ruta_key' => $transaccion['ruta_key'],
                'ruta' => $transaccion['ruta'],
                'id_trans' => $transaccion['id_trans'],
                'terminal' => $transaccion['terminal'] ?: null,
                'nombre_agencia' => $transaccion['nombre_agencia'] ?? null,
                'tipo' => $transaccion['tipo'],
                'tipo_etiqueta' => $transaccion['tipo_etiqueta'],
                'monto' => $transaccion['monto'],
                'monto_original' => $transaccion['monto_original'],
                'created_at' => $now,
                'updated_at' => $now,
            ], $transacciones);

            foreach (array_chunk($filas, self::IMPORT_CHUNK_SIZE) as $chunk) {
                MovimientoRutaV2Transaccion::query()->insert($chunk);
            }

            return $importacion;
        });

        return ['importacion' => $importacion, 'fechas' => $fechas, 'control' => $resultado['control']];
    }
}
