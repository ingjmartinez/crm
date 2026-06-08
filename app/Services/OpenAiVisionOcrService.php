<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiVisionOcrService
{
    public function extraerMontoDeposito(string $imageUrl, float $montoDigitado): array
    {
        $apiKey = (string) config('services.openai.api_key');

        if ($apiKey === '') {
            return [
                'success' => false,
                'estado' => 'error',
                'observacion' => 'Falta configurar OPENAI_API_KEY.',
            ];
        }

        $imageDataUrl = $this->downloadImageAsDataUrl($imageUrl);

        if ($imageDataUrl === null) {
            return [
                'success' => false,
                'estado' => 'error',
                'observacion' => 'No se pudo descargar la imagen del comprobante.',
            ];
        }

        try {
            $response = Http::timeout((int) config('services.openai.timeout', 60))
                ->acceptJson()
                ->withToken($apiKey)
                ->withOptions([
                    'verify' => filter_var(config('services.openai.verify_ssl', true), FILTER_VALIDATE_BOOLEAN),
                ])
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('services.openai.vision_model', 'gpt-4.1-mini'),
                    'input' => [[
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => $this->prompt($montoDigitado),
                            ],
                            [
                                'type' => 'input_image',
                                'image_url' => $imageDataUrl,
                                'detail' => 'high',
                            ],
                        ],
                    ]],
                ]);
        } catch (\Throwable $e) {
            Log::warning('OpenAI OCR deposito: excepcion llamando API', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'estado' => 'error',
                'observacion' => 'Excepcion llamando OpenAI: ' . $e->getMessage(),
            ];
        }

        if (!$response->successful()) {
            Log::warning('OpenAI OCR deposito: respuesta no exitosa', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'estado' => 'error',
                'observacion' => 'OpenAI respondio con estatus ' . $response->status(),
            ];
        }

        $text = $this->extractOutputText($response->json());
        $payload = $this->parseJson($text);

        if ($payload === null) {
            return [
                'success' => false,
                'estado' => 'error',
                'observacion' => 'OpenAI no devolvio JSON valido.',
                'texto_extraido' => $text,
            ];
        }

        $montoDetectado = is_numeric($payload['monto_detectado'] ?? null)
            ? round((float) $payload['monto_detectado'], 2)
            : null;

        $estado = 'revision';

        if ($montoDetectado !== null) {
            $estado = abs($montoDetectado - $montoDigitado) <= 1.00 ? 'coincide' : 'no_coincide';
        }

        return [
            'success' => true,
            'estado' => $estado,
            'monto_detectado' => $montoDetectado,
            'confianza' => $payload['confianza'] ?? null,
            'observacion' => $payload['observacion'] ?? null,
            'texto_extraido' => $payload['texto_extraido'] ?? $text,
        ];
    }

    private function downloadImageAsDataUrl(string $imageUrl): ?string
    {
        try {
            $response = Http::timeout(45)->get($imageUrl);
        } catch (\Throwable $e) {
            Log::warning('OpenAI OCR deposito: no se pudo descargar imagen', [
                'url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (!$response->successful() || $response->body() === '') {
            return null;
        }

        $mime = strtolower(trim((string) $response->header('Content-Type', 'image/jpeg')));
        $mime = explode(';', $mime)[0] ?: 'image/jpeg';

        if (!in_array($mime, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'], true)) {
            $mime = 'image/jpeg';
        }

        return 'data:' . $mime . ';base64,' . base64_encode($response->body());
    }

    private function prompt(float $montoDigitado): string
    {
        $monto = number_format($montoDigitado, 2, '.', '');

        return "Lee este comprobante bancario y extrae el monto depositado. "
            . "Compara contra el monto digitado por el usuario: {$monto}. "
            . "Devuelve solamente JSON valido sin markdown con estas claves: "
            . "monto_detectado number|null, banco_detectado string|null, fecha_detectada string|null, "
            . "confianza alta|media|baja, observacion string, texto_extraido string. "
            . "Si ves varios montos, usa el monto total/deposito/transferencia mas probable.";
    }

    private function extractOutputText(mixed $payload): string
    {
        if (!is_array($payload)) {
            return '';
        }

        if (isset($payload['output_text']) && is_string($payload['output_text'])) {
            return trim($payload['output_text']);
        }

        $chunks = [];
        $this->collectText($payload['output'] ?? [], $chunks);

        return trim(implode("\n", $chunks));
    }

    private function collectText(mixed $value, array &$chunks): void
    {
        if (is_string($value)) {
            return;
        }

        if (!is_array($value)) {
            return;
        }

        if (($value['type'] ?? null) === 'output_text' && isset($value['text']) && is_string($value['text'])) {
            $chunks[] = $value['text'];
        }

        foreach ($value as $child) {
            $this->collectText($child, $chunks);
        }
    }

    private function parseJson(string $text): ?array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text) ?? $text;
        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }
}
