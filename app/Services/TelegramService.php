<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    /**
     * Envía un mensaje de texto a un chat de Telegram.
     */
    public function sendMessage(string $chatId, string $mensaje): bool
    {
        $chatId = trim($chatId);
        $botToken = config('services.telegram.bot_token');

        if (empty($botToken) || $chatId === '') {
            Log::warning('TelegramService::sendMessage sin token o chat_id', [
                'chat_id' => $chatId,
            ]);

            return false;
        }

        return $this->enviar('sendMessage', $chatId, fn (PendingRequest $peticion): Response => $peticion
            ->asJson()
            ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $mensaje,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]));
    }

    /**
     * Envía un documento (por ejemplo, un PDF) a un chat de Telegram.
     */
    public function sendDocument(string $chatId, string $contenido, string $nombreArchivo, ?string $caption = null): bool
    {
        $chatId = trim($chatId);
        $botToken = config('services.telegram.bot_token');

        if (empty($botToken) || $chatId === '') {
            Log::warning('TelegramService::sendDocument sin token o chat_id', [
                'chat_id' => $chatId,
            ]);

            return false;
        }

        return $this->enviar('sendDocument', $chatId, fn (PendingRequest $peticion): Response => $peticion
            ->attach('document', $contenido, $nombreArchivo)
            ->post("https://api.telegram.org/bot{$botToken}/sendDocument", array_filter([
                'chat_id' => $chatId,
                'caption' => $caption,
                'parse_mode' => $caption !== null ? 'HTML' : null,
            ], fn (mixed $valor): bool => $valor !== null)));
    }

    /**
     * @param  \Closure(PendingRequest): Response  $callback
     */
    private function enviar(string $metodo, string $chatId, \Closure $callback): bool
    {
        $verifySsl = filter_var(config('services.telegram.verify_ssl', true), FILTER_VALIDATE_BOOLEAN);

        try {
            $response = $callback(Http::timeout(30)->withOptions(['verify' => $verifySsl]));

            if (! $response->successful()) {
                Log::warning("TelegramService::{$metodo} respuesta no exitosa", [
                    'chat_id' => $chatId,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning("TelegramService::{$metodo} excepcion", [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
