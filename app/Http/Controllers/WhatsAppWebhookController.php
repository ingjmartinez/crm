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
        $messageId = $this->extractMessageId((array) $data, $payload);
        $attachmentUrl = $this->extractAttachmentUrl((array) $data, $payload);
        $location = $this->extractLocation((array) $data, $payload);
        $inboundAccount = $this->extractInboundAccount($payload, (array) $data);
        $sendAccount = $this->extractSendAccount($payload, (array) $data, $routeAccount);
        $sessionAccount = $inboundAccount !== '' ? $inboundAccount : $sendAccount;
        $hasMessage = $message !== '';
        $hasLocation = $location !== null;

        if (!$hasLocation && $attachmentUrl === null && $messageId !== null) {
            $attachmentUrl = $this->whatsAppService->fetchReceivedAttachmentByMessageId($messageId);
        }

        Log::debug('WhatsApp webhook datos extraidos', [
            'phone' => $phone,
            'message_id' => $messageId,
            'attachment_url' => $attachmentUrl,
            'location' => $location,
            'inbound_account' => $inboundAccount,
            'send_account' => $sendAccount,
            'session_account' => $sessionAccount,
            'route_account' => $routeAccount,
            'message_preview' => $this->preview($message),
            'message_length' => strlen($message),
            'data_keys' => array_keys((array) $data),
        ]);

        if ($phone === '' || (!$hasMessage && $attachmentUrl === null && !$hasLocation)) {
            Log::warning('WhatsApp webhook datos incompletos', [
                'phone_empty' => $phone === '',
                'message_empty' => !$hasMessage,
                'attachment_empty' => $attachmentUrl === null,
                'location_empty' => !$hasLocation,
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

            $result = $this->chatbotService->handleIncoming($phone, $message, $sessionAccount, [
                'message_id' => $messageId,
                'attachment_url' => $attachmentUrl,
                'location' => $location,
            ]);
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

    private function extractMessageId(array $data, array $payload): ?string
    {
        $messageId = $data['id']
            ?? $data['message_id']
            ?? $data['msg_id']
            ?? $payload['id']
            ?? null;

        if ($messageId === null) {
            return null;
        }

        if (is_array($messageId)) {
            $messageId = $messageId['id'] ?? null;
        }

        $messageId = trim((string) $messageId);

        return $messageId !== '' ? $messageId : null;
    }

    private function extractAttachmentUrl(array $data, array $payload): ?string
    {
        $attachment = $data['attachment']
            ?? $data['media']
            ?? $data['image']
            ?? $payload['attachment']
            ?? $payload['media']
            ?? $payload['image']
            ?? null;

        if (is_array($attachment)) {
            $attachment = $attachment['url']
                ?? $attachment['link']
                ?? $attachment['src']
                ?? null;
        }

        if (!is_string($attachment)) {
            return null;
        }

        $attachment = trim($attachment);

        if ($attachment === '' || in_array(strtolower($attachment), ['false', 'null'], true)) {
            return null;
        }

        return $attachment;
    }

    private function extractLocation(array $data, array $payload): ?array
    {
        $candidates = [
            $data['location'] ?? null,
            $data['ubicacion'] ?? null,
            $data['geo'] ?? null,
            $payload['location'] ?? null,
            $payload['ubicacion'] ?? null,
            $payload['geo'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $location = $this->normalizeLocationCandidate($candidate);
            if ($location !== null) {
                return $location;
            }
        }

        $recursive = $this->findLocationRecursive($payload);

        return $recursive !== null ? $this->normalizeLocationCandidate($recursive) : null;
    }

    private function findLocationRecursive(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 5 || !is_array($value)) {
            return null;
        }

        $location = $this->normalizeLocationCandidate($value);
        if ($location !== null) {
            return $location;
        }

        foreach ($value as $child) {
            if (!is_array($child)) {
                continue;
            }

            $found = $this->findLocationRecursive($child, $depth + 1);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function normalizeLocationCandidate(mixed $candidate): ?array
    {
        if (!is_array($candidate)) {
            return null;
        }

        $latitude = $candidate['latitude']
            ?? $candidate['lat']
            ?? $candidate['gps_lat']
            ?? null;
        $longitude = $candidate['longitude']
            ?? $candidate['lng']
            ?? $candidate['lon']
            ?? $candidate['gps_lng']
            ?? null;

        if ($latitude === null || $longitude === null) {
            return null;
        }

        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return null;
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }

        return [
            'lat' => round($latitude, 7),
            'lng' => round($longitude, 7),
            'name' => trim((string) ($candidate['name'] ?? $candidate['address'] ?? '')),
        ];
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
