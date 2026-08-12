<?php

namespace App\Services;

use App\Exceptions\LotobetTokenRequiredException;
use App\Models\Token;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class AsistenciaAgenteVentaEndpointService
{
    private ?string $lotonetSessionCookie = null;

    public function prepararAcceso(string $sistema): void
    {
        if (in_array($sistema, ['todos', 'lotobet'], true)) {
            $this->tokenLotobet();
        }

        if (in_array($sistema, ['todos', 'lotonet'], true)) {
            $this->iniciarSesionLotonet();
        }
    }

    /**
     * @return array<int, array{fecha: string, sistema: string, cedula: string, nombre: string, terminal: string, entrada: ?string, salida: ?string, ultimo_login: ?string}>
     */
    public function consultar(string $fecha, string $sistema = 'todos'): array
    {
        $registros = [];

        if (in_array($sistema, ['todos', 'lotobet'], true)) {
            $registros = array_merge($registros, $this->normalizar($this->obtenerLotobet($fecha), $fecha, 'LOTOBET'));
        }

        if (in_array($sistema, ['todos', 'lotonet'], true)) {
            $registros = array_merge($registros, $this->normalizar($this->obtenerLotonet($fecha), $fecha, 'LOTONET'));
        }

        return $this->consolidar($registros, $fecha);
    }

    /** @return array<int, array<string, mixed>> */
    private function obtenerLotobet(string $fecha): array
    {
        $token = $this->tokenLotobet();

        $response = $this->request()
            ->withHeaders([
                'AhfCC' => 'yB0tt5KW3wVVCYYtCpen',
                'AhfVB' => 'xSzdgtOKbGRhUhtv1ois',
            ])
            ->get("https://ltkadapi.lotobet.bet/api/V1/var4XZ3ojQiPZq5BpI/{$token->token}/{$fecha}/05");

        if (in_array($response->status(), [401, 403], true)) {
            throw new LotobetTokenRequiredException('La API de Lotobet rechazó el token actual.');
        }

        $payload = $response->json();

        if (is_array($payload)) {
            $this->validarTokenLotobet($payload);
        }

        $response->throw();

        return $this->contenidoRespuesta($payload, 'Content', 'Lotobet');
    }

    private function tokenLotobet(): Token
    {
        $token = Token::query()->find(1);

        if (! $token || blank($token->token)) {
            throw new LotobetTokenRequiredException('Debe generar un token de Lotobet para consultar las asistencias.');
        }

        if (blank($token->fecha) || now()->greaterThan($token->fecha)) {
            throw new LotobetTokenRequiredException('El token de Lotobet está vencido.');
        }

        return $token;
    }

    /** @return array<int, array<string, mixed>> */
    private function obtenerLotonet(string $fecha): array
    {
        $headers = [
            'token' => (string) config('services.lotonet.attendance_token'),
            'Content-Type' => 'application/json',
        ];
        $cookie = trim((string) ($this->lotonetSessionCookie ?: config('services.lotonet.attendance_cookie')));

        if ($cookie !== '') {
            $headers['Cookie'] = $cookie;
        }

        $response = $this->request()
            ->withHeaders($headers)
            ->send('GET', rtrim((string) config('services.lotonet.attendance_url'), '/')."/{$fecha}/5", [
                'body' => json_encode([
                    'usuario' => [
                        'username' => (string) config('services.lotonet.username'),
                        'password' => (string) config('services.lotonet.password'),
                    ],
                ], JSON_THROW_ON_ERROR),
            ]);

        $payload = $response->json();
        $response->throw();

        return $this->contenidoRespuesta($payload, 'data.result', 'Lotonet');
    }

    private function iniciarSesionLotonet(): void
    {
        $response = $this->request()->post((string) config('services.lotonet.session_url'), [
            'usuario' => [
                'username' => (string) config('services.lotonet.username'),
                'password' => (string) config('services.lotonet.password'),
            ],
        ]);

        $response->throw();
        $cookie = trim((string) $response->header('Set-Cookie'));

        if ($cookie !== '') {
            $this->lotonetSessionCookie = trim(explode(';', $cookie, 2)[0]);
        }
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
     * @param  array<string, mixed>|null  $payload
     * @return array<int, array<string, mixed>>
     */
    private function contenidoRespuesta(?array $payload, string $ruta, string $fuente): array
    {
        if (! is_array($payload)) {
            throw new RuntimeException("La API de {$fuente} devolvió una respuesta inválida.");
        }

        $code = isset($payload['code']) ? strtolower(trim((string) $payload['code'])) : null;

        if ($code !== null && ! in_array($code, ['0', '00', '200', 'ok', 'success'], true)) {
            throw new RuntimeException((string) ($payload['msg'] ?? $payload['message'] ?? "Respuesta inválida de {$fuente}."));
        }

        $contenido = data_get($payload, $ruta, []);

        return is_array($contenido) ? array_values(array_filter($contenido, 'is_array')) : [];
    }

    /** @param array<string, mixed> $payload */
    private function validarTokenLotobet(array $payload): void
    {
        $code = isset($payload['code']) ? strtolower(trim((string) $payload['code'])) : null;
        $message = trim((string) ($payload['msg'] ?? $payload['message'] ?? ''));

        if (in_array($code, ['401', '403', 'unauthorized'], true)) {
            throw new LotobetTokenRequiredException(
                $message !== '' ? $message : 'La API de Lotobet rechazó el token actual.'
            );
        }

        if ($message === '') {
            return;
        }

        $normalizedMessage = mb_strtolower($message);
        $mentionsSession = str_contains($normalizedMessage, 'token')
            || str_contains($normalizedMessage, 'sesión')
            || str_contains($normalizedMessage, 'sesion');
        $indicatesExpiration = str_contains($normalizedMessage, 'venc')
            || str_contains($normalizedMessage, 'expir')
            || str_contains($normalizedMessage, 'invál')
            || str_contains($normalizedMessage, 'inval')
            || str_contains($normalizedMessage, 'rechaz')
            || str_contains($normalizedMessage, 'unauthorized');

        if ($mentionsSession && $indicatesExpiration) {
            throw new LotobetTokenRequiredException($message);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $registros
     * @return array<int, array{fecha: string, sistema: string, cedula: string, nombre: string, terminal: string, entrada: ?string, salida: ?string, ultimo_login: ?string}>
     */
    private function normalizar(array $registros, string $fecha, string $sistema): array
    {
        return collect($registros)->map(function (array $registro) use ($fecha, $sistema): array {
            return [
                'fecha' => $fecha,
                'sistema' => $sistema,
                'cedula' => $this->normalizarCedula($registro['cedula'] ?? $registro['identificacion'] ?? ''),
                'nombre' => trim((string) ($registro['usuario'] ?? $registro['nombre'] ?? $registro['nombre_usuario'] ?? $registro['username'] ?? '')),
                'terminal' => $this->normalizarTerminal($registro['agencia'] ?? $registro['terminal'] ?? $registro['agencia_id'] ?? ''),
                'entrada' => $this->valorPonche($registro['primer_login'] ?? $registro['entrada'] ?? null),
                'salida' => $this->valorPonche($registro['ultimo_logout'] ?? $registro['salida'] ?? null),
                'ultimo_login' => $this->valorPonche($registro['ultimo_login'] ?? null),
            ];
        })->filter(fn (array $registro): bool => $registro['terminal'] !== '0')->values()->all();
    }

    /**
     * @param  array<int, array{fecha: string, sistema: string, cedula: string, nombre: string, terminal: string, entrada: ?string, salida: ?string, ultimo_login: ?string}>  $registros
     * @return array<int, array{fecha: string, sistema: string, cedula: string, nombre: string, terminal: string, entrada: ?string, salida: ?string, ultimo_login: ?string}>
     */
    private function consolidar(array $registros, string $fecha): array
    {
        $consolidados = [];

        foreach ($registros as $indice => $registro) {
            $identidad = $registro['cedula'] !== '' ? $registro['cedula'] : mb_strtolower($registro['nombre']);
            $identidad = $identidad !== '' ? $identidad : "sin-identidad-{$indice}";
            $clave = implode('|', [$registro['sistema'], $registro['terminal'], $identidad]);

            if (! isset($consolidados[$clave])) {
                $consolidados[$clave] = $registro;

                continue;
            }

            if ($consolidados[$clave]['nombre'] === '' && $registro['nombre'] !== '') {
                $consolidados[$clave]['nombre'] = $registro['nombre'];
            }

            $consolidados[$clave]['entrada'] = $this->seleccionarPonche(
                $consolidados[$clave]['entrada'],
                $registro['entrada'],
                $fecha,
                true
            );
            $consolidados[$clave]['salida'] = $this->seleccionarPonche(
                $consolidados[$clave]['salida'],
                $registro['salida'],
                $fecha,
                false
            );
            $consolidados[$clave]['ultimo_login'] = $this->seleccionarPonche(
                $consolidados[$clave]['ultimo_login'],
                $registro['ultimo_login'],
                $fecha,
                false
            );
        }

        return array_values($consolidados);
    }

    private function seleccionarPonche(?string $actual, ?string $nuevo, string $fecha, bool $primero): ?string
    {
        if ($actual === null) {
            return $nuevo;
        }

        if ($nuevo === null) {
            return $actual;
        }

        $actualFecha = $this->instante($actual, $fecha);
        $nuevoFecha = $this->instante($nuevo, $fecha);

        if ($actualFecha === null || $nuevoFecha === null) {
            return $actual;
        }

        return $primero
            ? ($nuevoFecha->lessThan($actualFecha) ? $nuevo : $actual)
            : ($nuevoFecha->greaterThan($actualFecha) ? $nuevo : $actual);
    }

    private function instante(string $valor, string $fecha): ?Carbon
    {
        try {
            return Carbon::parse(str_contains($valor, '-') || str_contains($valor, '/') ? $valor : "{$fecha} {$valor}");
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizarCedula(mixed $cedula): string
    {
        $digitos = preg_replace('/\D/', '', (string) $cedula) ?? '';

        return $digitos !== '' && strlen($digitos) <= 11 ? str_pad($digitos, 11, '0', STR_PAD_LEFT) : $digitos;
    }

    private function normalizarTerminal(mixed $terminal): string
    {
        $normalizada = ltrim(trim((string) $terminal), '0');

        return $normalizada === '' ? '0' : $normalizada;
    }

    private function valorPonche(mixed $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }
}
