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
        $account = config('services.whatsapp.default_account');

        if (empty($endpoint) || empty($secret)) {
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
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->asForm()
                ->post($endpoint, $payload);

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
