<?php

namespace App\Services\Operaciones;

use App\Models\MovimientoRutaV2Importacion;
use App\Models\MovimientoRutaV2Transaccion;
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
    public function importar(UploadedFile $archivo, ?int $userId): array
    {
        $resultado = $this->csvService->procesar($archivo);
        $transacciones = $this->agenciaService->enriquecer($resultado['transacciones']);
        $fechas = collect($transacciones)->pluck('fecha_iso')->unique()->sort()->values()->all();

        if ($fechas === []) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'El documento no contiene movimientos válidos para importar.',
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
