<?php

namespace App\Console\Commands;

use App\Models\ChatbotSession;
use App\Services\WhatsAppChatbotService;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CloseInactiveChatbotSessions extends Command
{
    protected $signature = 'chatbot:close-inactive-sessions';

    protected $description = 'Cierra sesiones activas del chatbot por inactividad';

    public function handle(
        WhatsAppChatbotService $chatbotService,
        WhatsAppService $whatsAppService
    ): int {
        $cutoff = now()->subSeconds(WhatsAppChatbotService::SESSION_TIMEOUT_SECONDS);
        $message = $chatbotService->inactivityFarewellMessage();
        $closed = 0;

        ChatbotSession::query()
            ->whereNotNull('last_interaction_at')
            ->where('last_interaction_at', '<=', $cutoff)
            ->whereNotIn('step', ['inicio', WhatsAppChatbotService::CLOSED_BY_TIMEOUT_STEP])
            ->orderBy('id')
            ->chunkById(100, function ($sessions) use ($message, $whatsAppService, &$closed) {
                foreach ($sessions as $session) {
                    $result = $whatsAppService->sendText(
                        (string) $session->phone,
                        $message,
                        (string) $session->account
                    );

                    $session->step = WhatsAppChatbotService::CLOSED_BY_TIMEOUT_STEP;
                    $session->context = null;
                    $session->message_count = 0;
                    $session->save();

                    $closed++;

                    Log::info('WhatsApp chatbot: sesion cerrada por inactividad', [
                        'session_id' => $session->id,
                        'phone' => $session->phone,
                        'account' => $session->account,
                        'sent' => (bool) ($result['success'] ?? false),
                        'status' => $result['status'] ?? null,
                    ]);
                }
            });

        $this->info("Sesiones cerradas por inactividad: {$closed}");

        return self::SUCCESS;
    }
}
