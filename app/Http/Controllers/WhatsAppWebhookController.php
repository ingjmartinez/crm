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

    public function handle(Request $request, ?string $routeAccount = null): JsonResponse
    {
        $payload = $request->all();

        Log::warning('WhatsApp webhook', ['payload' => $payload]);

        if (!$this->validToken($payload)) {
            Log::warning('WhatsApp webhook token invalido', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => 'unauthorized'], 401);
        }

        $data = $payload['data'] ?? $payload;
        $phone = (string) ($data['phone'] ?? $data['from'] ?? $data['sender'] ?? '');
        $message = trim((string) ($data['message'] ?? $data['text'] ?? $data['body'] ?? ''));
        $inboundAccount = $this->extractInboundAccount($payload, (array) $data);
        $sendAccount = $this->extractSendAccount($payload, (array) $data, $routeAccount);
        $sessionAccount = $inboundAccount !== '' ? $inboundAccount : $sendAccount;

        Log::debug('WhatsApp webhook datos extraidos', [
            'phone' => $phone,
            'inbound_account' => $inboundAccount,
            'send_account' => $sendAccount,
            'session_account' => $sessionAccount,
            'route_account' => $routeAccount,
            'message_preview' => $this->preview($message),
            'message_length' => strlen($message),
            'data_keys' => array_keys((array) $data),
        ]);

        if ($phone === '' || $message === '') {
            Log::warning('WhatsApp webhook datos incompletos', [
                'phone_empty' => $phone === '',
                'message_empty' => $message === '',
                'data_keys' => array_keys((array) $data),
            ]);

            return response()->json(['status' => 'missing_data'], 422);
        }

        try {
            Log::debug('WhatsApp webhook iniciando chatbot', [
                'phone' => $phone,
                'inbound_account' => $inboundAccount,
                'send_account' => $sendAccount,
            ]);

            if ($sessionAccount === '') {
                Log::warning('WhatsApp webhook sin cuenta identificada', [
                    'phone' => $phone,
                    'route_account' => $routeAccount,
                    'data_keys' => array_keys((array) $data),
                ]);

                return response()->json(['status' => 'missing_account'], 422);
            }

            if (!$this->accountAllowed($inboundAccount)) {
                Log::warning('WhatsApp webhook cuenta no permitida para este sistema', [
                    'phone' => $phone,
                    'inbound_account' => $inboundAccount,
                    'send_account' => $sendAccount,
                    'route_account' => $routeAccount,
                    'allowed_accounts' => config('services.whatsapp.allowed_accounts', []),
                ]);

                return response()->json(['status' => 'ignored_account']);
            }

            $result = $this->chatbotService->handleIncoming($phone, $message, $sessionAccount);
            $reply = (string) ($result['reply'] ?? '');
            $session = $result['session'] ?? null;

            Log::debug('WhatsApp webhook chatbot respondio', [
                'phone' => $phone,
                'session_id' => $session?->id,
                'step' => $session?->step,
                'reply_empty' => $reply === '',
                'reply_preview' => $this->preview($reply),
            ]);

            if ($reply === '') {
                Log::warning('WhatsApp webhook chatbot devolvio respuesta vacia', [
                    'phone' => $phone,
                    'session_id' => $session?->id,
                    'step' => $session?->step,
                ]);

                return response()->json(['status' => 'no_reply']);
            }

            Log::debug('WhatsApp webhook enviando respuesta', [
                'phone' => $phone,
                'inbound_account' => $inboundAccount,
                'send_account' => $sendAccount,
                'reply_preview' => $this->preview($reply),
            ]);

            $sendResult = $this->whatsAppService->sendText($phone, $reply, $sendAccount);

            if (empty($sendResult['success'])) {
                Log::warning('WhatsApp webhook no pudo enviar respuesta', [
                    'phone' => $phone,
                    'send_result' => $sendResult,
                ]);
            } else {
                Log::debug('WhatsApp webhook respuesta enviada', [
                    'phone' => $phone,
                    'status' => $sendResult['status'] ?? null,
                    'provider_response' => $sendResult['provider_response'] ?? null,
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
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    public function verify(Request $request, ?string $routeAccount = null): JsonResponse
    {
        $challenge = $request->input('challenge')
            ?? $request->input('hub_challenge')
            ?? $request->input('hub.challenge')
            ?? '';

        return response()->json([
            'status' => 'ok',
            'challenge' => $challenge,
            'account' => $routeAccount,
        ]);
    }

    private function extractInboundAccount(array $payload, array $data): string
    {
        $account = $data['wid']
            ?? $data['account']
            ?? $data['unique']
            ?? $data['account_unique']
            ?? $data['wa_account']
            ?? $payload['wid']
            ?? $payload['account']
            ?? $payload['unique']
            ?? '';

        if (is_array($account)) {
            $account = $account['unique']
                ?? $account['id']
                ?? $account['account']
                ?? '';
        }

        return trim((string) $account);
    }

    private function extractSendAccount(array $payload, array $data, ?string $routeAccount): string
    {
        $account = $data['account']
            ?? $data['unique']
            ?? $data['account_unique']
            ?? $data['wa_account']
            ?? $payload['account']
            ?? $payload['unique']
            ?? $routeAccount
            ?? config('services.whatsapp.default_account')
            ?? '';

        if (is_array($account)) {
            $account = $account['unique']
                ?? $account['id']
                ?? $account['account']
                ?? '';
        }

        return trim((string) $account);
    }

    private function accountAllowed(string $inboundAccount): bool
    {
        $allowedAccounts = config('services.whatsapp.allowed_accounts', []);

        if (empty($allowedAccounts)) {
            return true;
        }

        if ($inboundAccount === '') {
            return false;
        }

        return in_array($inboundAccount, $allowedAccounts, true);
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

    private function preview(string $value, int $limit = 160): string
    {
        if (strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, $limit) . '...';
    }
}
