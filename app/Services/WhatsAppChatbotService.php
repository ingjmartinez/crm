<?php

namespace App\Services;

use App\Models\ChatbotSession;

class WhatsAppChatbotService
{
    private const SESSION_TIMEOUT_MINUTES = 30;

    public function handleIncoming(string $phone, string $message): array
    {
        $normalizedPhone = $this->normalizePhone($phone);
        $message = trim($message);
        $session = $this->getOrCreateSession($normalizedPhone);

        if ($this->sessionExpired($session)) {
            $session->step = 'inicio';
            $session->context = null;
            $session->message_count = 0;
        }

        $session->last_message = $message;
        $session->last_interaction_at = now();
        $session->message_count = $session->message_count + 1;

        $reply = $this->processStep($session, $message);

        $session->save();

        return [
            'session' => $session,
            'reply' => $reply,
        ];
    }

    private function processStep(ChatbotSession $session, string $message): string
    {
        return match ($session->step) {
            'inicio' => $this->handleInicio($session, $message),
            default => $this->resetToInicio($session),
        };
    }

    private function handleInicio(ChatbotSession $session, string $message): string
    {
        $context = is_array($session->context) ? $session->context : [];

        $session->step = 'inicio';
        $session->context = [
            'first_message' => $context['first_message'] ?? $message,
            'last_message' => $message,
        ];

        return (string) config(
            'services.whatsapp.chatbot_welcome_message',
            'Hola, soy el asistente virtual. Hemos recibido tu mensaje.'
        );
    }

    private function resetToInicio(ChatbotSession $session): string
    {
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
}
