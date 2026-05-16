<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppChatbotService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        private WhatsAppChatbotService $chatbotService,
        private WhatsAppService $whatsAppService
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (!$this->validToken($payload)) {
            Log::warning('WhatsApp webhook token invalido', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => 'unauthorized'], 401);
        }

        $data = $payload['data'] ?? $payload;
        $phone = (string) ($data['phone'] ?? $data['from'] ?? $data['sender'] ?? '');
        $message = trim((string) ($data['message'] ?? $data['text'] ?? $data['body'] ?? ''));
        $account = (string) ($data['wid'] ?? $data['account'] ?? $data['unique'] ?? '');

        if ($phone === '' || $message === '') {
            return response()->json(['status' => 'missing_data'], 422);
        }

        try {
            $result = $this->chatbotService->handleIncoming($phone, $message);
            $reply = (string) ($result['reply'] ?? '');

            if ($reply === '') {
                return response()->json(['status' => 'no_reply']);
            }

            $sendResult = $this->whatsAppService->sendText($phone, $reply, $account);

            if (empty($sendResult['success'])) {
                Log::warning('WhatsApp webhook no pudo enviar respuesta', [
                    'phone' => $phone,
                    'send_result' => $sendResult,
                ]);
            }

            return response()->json([
                'status' => 'ok',
                'sent' => (bool) ($sendResult['success'] ?? false),
            ]);
        } catch (\Throwable $e) {
            Log::error('WhatsApp webhook error', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    public function verify(Request $request): JsonResponse
    {
        $challenge = $request->input('challenge')
            ?? $request->input('hub_challenge')
            ?? $request->input('hub.challenge')
            ?? '';

        return response()->json([
            'status' => 'ok',
            'challenge' => $challenge,
        ]);
    }

    private function validToken(array $payload): bool
    {
        $expected = (string) config('services.whatsapp.webhook_token');

        if ($expected === '') {
            return true;
        }

        $provided = (string) ($payload['token'] ?? $payload['secret'] ?? '');

        return $provided !== '' && hash_equals($expected, $provided);
    }
}
