<?php

namespace App\Jobs;

use App\Models\OperacionDepositoRuta;
use App\Services\OpenAiVisionOcrService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProcessOperacionDepositoRutaOcr implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;
    public int $tries = 2;

    public function __construct(public int $depositoId)
    {
    }

    public function handle(OpenAiVisionOcrService $ocrService): void
    {
        if (!Schema::hasTable('operaciones_deposito_rutas') || !Schema::hasColumn('operaciones_deposito_rutas', 'ocr_estado')) {
            return;
        }

        $deposito = OperacionDepositoRuta::find($this->depositoId);

        if (!$deposito) {
            return;
        }

        $deposito->update([
            'ocr_estado' => 'procesando',
            'ocr_observacion' => null,
        ]);

        $result = $ocrService->extraerMontoDeposito(
            (string) $deposito->comprobante_url,
            (float) $deposito->monto_depositado
        );

        $deposito->update([
            'monto_ocr' => $result['monto_detectado'] ?? null,
            'ocr_estado' => $result['estado'] ?? 'error',
            'ocr_confianza' => $result['confianza'] ?? null,
            'ocr_observacion' => $result['observacion'] ?? null,
            'ocr_texto' => $result['texto_extraido'] ?? null,
            'ocr_procesado_at' => now(),
        ]);

        Log::info('Deposito ruta OCR procesado', [
            'deposito_id' => $deposito->id,
            'ocr_estado' => $result['estado'] ?? null,
            'monto_digitado' => (float) $deposito->monto_depositado,
            'monto_ocr' => $result['monto_detectado'] ?? null,
        ]);
    }
}
