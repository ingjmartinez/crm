<?php

namespace App\Services;

use App\Models\ChatbotSession;
use Illuminate\Support\Facades\Log;

class WhatsAppChatbotService
{
    private const SESSION_TIMEOUT_MINUTES = 30;

    public function handleIncoming(string $phone, string $message): array
    {
        $normalizedPhone = $this->normalizePhone($phone);
        $message = trim($message);

        Log::debug('WhatsApp chatbot: mensaje recibido', [
            'phone_original' => $phone,
            'phone_normalized' => $normalizedPhone,
            'message_preview' => $this->preview($message),
            'message_length' => strlen($message),
        ]);

        $session = $this->getOrCreateSession($normalizedPhone);
        $wasRecentlyCreated = $session->wasRecentlyCreated;

        Log::debug('WhatsApp chatbot: sesion cargada', [
            'phone' => $normalizedPhone,
            'session_id' => $session->id,
            'created_now' => $wasRecentlyCreated,
            'step' => $session->step,
            'message_count' => $session->message_count,
            'last_interaction_at' => optional($session->last_interaction_at)->toDateTimeString(),
        ]);

        if ($this->sessionExpired($session)) {
            Log::debug('WhatsApp chatbot: sesion expirada, reiniciando', [
                'phone' => $normalizedPhone,
                'session_id' => $session->id,
                'previous_step' => $session->step,
                'last_interaction_at' => optional($session->last_interaction_at)->toDateTimeString(),
            ]);

            $session->step = 'inicio';
            $session->context = null;
            $session->message_count = 0;
        }

        $previousStep = $session->step;
        $previousMessageCount = $session->message_count;

        $session->last_message = $message;
        $session->last_interaction_at = now();
        $session->message_count = $session->message_count + 1;

        Log::debug('WhatsApp chatbot: procesando paso', [
            'phone' => $normalizedPhone,
            'session_id' => $session->id,
            'step_before' => $previousStep,
            'message_count_before' => $previousMessageCount,
            'message_count_after' => $session->message_count,
        ]);

        $reply = $this->processStep($session, $message);

        $session->save();

        Log::debug('WhatsApp chatbot: respuesta generada y sesion guardada', [
            'phone' => $normalizedPhone,
            'session_id' => $session->id,
            'step_before' => $previousStep,
            'step_after' => $session->step,
            'reply_empty' => $reply === '',
            'reply_preview' => $this->preview($reply),
            'context' => $session->context,
        ]);

        return [
            'session' => $session,
            'reply' => $reply,
        ];
    }

    private function processStep(ChatbotSession $session, string $message): string
    {
        Log::debug('WhatsApp chatbot: entrando a processStep', [
            'session_id' => $session->id,
            'phone' => $session->phone,
            'step' => $session->step,
        ]);

        return match ($session->step) {
            'inicio' => $this->handleInicio($session, $message),
            'consulta_hora_menu' => $this->handleConsultaHoraMenu($session, $message),
            default => $this->resetToInicio($session),
        };
    }

    private function handleInicio(ChatbotSession $session, string $message): string
    {
        $context = is_array($session->context) ? $session->context : [];

        Log::debug('WhatsApp chatbot: handleInicio', [
            'session_id' => $session->id,
            'phone' => $session->phone,
            'previous_context' => $context,
            'message_preview' => $this->preview($message),
        ]);

        $session->step = 'consulta_hora_menu';
        $session->context = [
            'first_message' => $context['first_message'] ?? $message,
            'last_message' => $message,
            'menu' => 'principal',
        ];

        return $this->consultaHoraMenuMessage();
    }

    private function handleConsultaHoraMenu(ChatbotSession $session, string $message): string
    {
        $option = trim($message);

        Log::debug('WhatsApp chatbot: handleConsultaHoraMenu', [
            'session_id' => $session->id,
            'phone' => $session->phone,
            'option' => $option,
        ]);

        if ($option === '1') {
            $session->step = 'inicio';
            $session->context = null;

            return '7:00 am a 9:00 pm';
        }

        if ($option === '2') {
            $session->step = 'inicio';
            $session->context = null;

            return 'desarrollo de software';
        }

        $session->step = 'consulta_hora_menu';

        return $this->consultaHoraMenuMessage();
    }

    private function consultaHoraMenuMessage(): string
    {
        return "Hola como estas soy el chat bot y estoy para servirte.\n\n"
            . "Por favor responde solo numericamente:\n\n"
            . "1- consultar el horario de servicio\n"
            . "2- consultar los servicios";
    }

    private function resetToInicio(ChatbotSession $session): string
    {
        Log::warning('WhatsApp chatbot: paso desconocido, reiniciando a inicio', [
            'session_id' => $session->id,
            'phone' => $session->phone,
            'unknown_step' => $session->step,
            'last_message_preview' => $this->preview((string) $session->last_message),
        ]);

        $session->step = 'inicio';
        $session->context = null;

        return $this->handleInicio($session, (string) $session->last_message);
    }

    private function getOrCreateSession(string $phone): ChatbotSession
    {
        return ChatbotSession::firstOrCreate(
            ['phone' => $phone],
            [
                'step' => 'inicio',
                'context' => null,
                'last_interaction_at' => now(),
                'message_count' => 0,
            ]
        );
    }

    private function sessionExpired(ChatbotSession $session): bool
    {
        return $session->last_interaction_at
            && $session->last_interaction_at->diffInMinutes(now()) > self::SESSION_TIMEOUT_MINUTES;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return $digits !== '' ? $digits : trim($phone);
    }

    private function preview(string $value, int $limit = 160): string
    {
        if (strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, $limit) . '...';
    }
}
