<?php

namespace App\Services;

use App\Exceptions\LotobetTokenRequiredException;
use App\Models\Token;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AsistenciaTerminalEndpointService
{
    /**
     * @return array<string, array{fuente: string, entrada: string}>
     */
    public function terminalesConPonche(string $fecha): array
    {
        $terminales = [];

        foreach ($this->obtenerLotobet($fecha) as $asistencia) {
            $this->agregarPonche($terminales, $asistencia, 'BET');
        }

        return $terminales;
    }

    /** @return array<int, array<string, mixed>> */
    private function obtenerLotobet(string $fecha): array
    {
        $token = Token::query()->find(1);

        if (! $token) {
            throw new LotobetTokenRequiredException('Debe generar un token de Lotobet para consultar las asistencias.');
        }

        if (now()->greaterThan($token->fecha)) {
            throw new LotobetTokenRequiredException('El token de Lotobet está vencido.');
        }

        $response = $this->request()
            ->withHeaders([
                'AhfCC' => 'yB0tt5KW3wVVCYYtCpen',
                'AhfVB' => 'xSzdgtOKbGRhUhtv1ois',
            ])
            ->get("https://ltkadapi.lotobet.bet/api/V1/var4XZ3ojQiPZq5BpI/{$token->token}/{$fecha}/05");

        $payload = $response->json();

        if (in_array($response->status(), [401, 403], true)) {
            throw new LotobetTokenRequiredException('La API de Lotobet rechazó el token actual.');
        }

        if (is_array($payload)) {
            $this->validarRespuesta($payload, 'Lotobet');
        }

        $response->throw();

        if (! is_array($payload)) {
            throw new RuntimeException('La API de Lotobet devolvió una respuesta inválida.');
        }

        return $payload['Content'] ?? [];
    }

    private function request(): PendingRequest
    {
        return Http::withoutVerifying()
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(60)
            ->retry(2, 300);
    }

    /**
     * @param  array<string, array{fuente: string, entrada: string}>  $terminales
     * @param  array<string, mixed>  $asistencia
     */
    private function agregarPonche(array &$terminales, array $asistencia, string $fuente): void
    {
        $terminal = $this->normalizarTerminal((string) (
            $asistencia['agencia']
            ?? $asistencia['terminal']
            ?? $asistencia['agencia_id']
            ?? ''
        ));
        $entrada = $asistencia['primer_login'] ?? $asistencia['entrada'] ?? null;

        if ($terminal === '0' || blank($entrada)) {
            return;
        }

        $entradaNormalizada = (string) $entrada;

        if (! isset($terminales[$terminal]) || strtotime($entradaNormalizada) < strtotime($terminales[$terminal]['entrada'])) {
            $terminales[$terminal] = [
                'fuente' => $fuente,
                'entrada' => $entradaNormalizada,
            ];
        }
    }

    /** @param array<string, mixed> $payload */
    private function validarRespuesta(array $payload, string $fuente): void
    {
        $code = isset($payload['code']) ? strtolower(trim((string) $payload['code'])) : null;
        $message = (string) ($payload['msg'] ?? $payload['message'] ?? "Respuesta inválida de {$fuente}.");

        if ($this->respuestaRequiereToken($code, $message)) {
            throw new LotobetTokenRequiredException($message);
        }

        if ($code !== null && ! in_array($code, ['0', '00', '200', 'ok', 'success'], true)) {
            throw new RuntimeException($message);
        }
    }

    private function respuestaRequiereToken(?string $code, string $message): bool
    {
        if (in_array($code, ['401', '403', 'unauthorized'], true)) {
            return true;
        }

        $normalizedMessage = strtolower($message);
        $mentionsSession = str_contains($normalizedMessage, 'token')
            || str_contains($normalizedMessage, 'sesión')
            || str_contains($normalizedMessage, 'sesion');
        $indicatesExpiration = str_contains($normalizedMessage, 'venc')
            || str_contains($normalizedMessage, 'expir')
            || str_contains($normalizedMessage, 'invál')
            || str_contains($normalizedMessage, 'inval')
            || str_contains($normalizedMessage, 'rechaz')
            || str_contains($normalizedMessage, 'unauthorized');

        return $mentionsSession && $indicatesExpiration;
    }

    private function normalizarTerminal(string $terminal): string
    {
        $normalizada = ltrim(trim($terminal), '0');

        return $normalizada === '' ? '0' : $normalizada;
    }
}
