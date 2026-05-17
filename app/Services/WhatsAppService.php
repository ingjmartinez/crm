<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function sendText(string $recipient, string $message, ?string $account = null): array
    {
        $endpoint = config('services.whatsapp.send_endpoint');
        $secret = config('services.whatsapp.api_key');
        $timeout = (int) config('services.whatsapp.timeout', 30);
        $account = $account ?: config('services.whatsapp.default_account');
        $verifySsl = (bool) config('services.whatsapp.verify_ssl', true);

        if (empty($endpoint) || empty($secret)) {
            Log::warning('WhatsAppService::sendText configuracion incompleta', [
                'endpoint_empty' => empty($endpoint),
                'secret_empty' => empty($secret),
                'account_empty' => empty($account),
            ]);

            return [
                'success' => false,
                'message' => 'Falta configurar WA_API_URL_SINGLE o WA_API_KEY.',
            ];
        }

        $payload = [
            'secret' => $secret,
            'account' => $account,
            'recipient' => $recipient,
            'type' => 'text',
            'message' => $message,
            'priority' => 1,
        ];

        if (empty($payload['account'])) {
            unset($payload['account']);
        }

        try {
            Log::debug('WhatsAppService::sendText enviando request', [
                'endpoint' => $endpoint,
                'recipient' => $recipient,
                'account' => $payload['account'] ?? null,
                'type' => $payload['type'],
                'message_length' => strlen($message),
                'body_format' => 'multipart',
                'verify_ssl' => $verifySsl,
            ]);

            $response = Http::timeout($timeout)
                ->acceptJson()
                ->asMultipart()
                ->withOptions(['verify' => $verifySsl])
                ->post($endpoint, $payload);

            Log::debug('WhatsAppService::sendText respuesta proveedor', [
                'recipient' => $recipient,
                'account' => $payload['account'] ?? null,
                'successful' => $response->successful(),
                'status' => $response->status(),
                'body' => $this->responseBody($response->body()),
            ]);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful() ? 'Mensaje enviado' : 'Error al enviar mensaje',
                'provider_response' => $this->responseBody($response->body()),
            ];
        } catch (\Throwable $e) {
            Log::error('WhatsAppService::sendText', [
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => 'Excepcion al enviar mensaje de WhatsApp',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function responseBody(string $body): mixed
    {
        $decoded = json_decode($body, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $body;
    }
}
