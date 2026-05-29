<?php

namespace App\Services;

use App\Models\Agencia;
use App\Models\ChatbotSession;
use App\Models\ServicioGeneralRequerimiento;
use App\Models\Token;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppChatbotService
{
    public const SESSION_TIMEOUT_SECONDS = 60;
    public const CLOSED_BY_TIMEOUT_STEP = 'cerrada_timeout';

    private const STEP_SG_TIPO = 'servicios_generales_tipo';
    private const STEP_SG_TERMINAL = 'servicios_generales_terminal';
    private const STEP_SG_IMAGEN = 'servicios_generales_imagen';

    public function handleIncoming(string $phone, string $message, ?string $account = null, array $incoming = []): array
    {
        $normalizedPhone = $this->normalizePhone($phone);
        $normalizedAccount = $this->normalizeAccount($account);
        $message = trim($message);

        Log::debug('WhatsApp chatbot: mensaje recibido', [
            'phone_original' => $phone,
            'phone_normalized' => $normalizedPhone,
            'account' => $normalizedAccount,
            'message_preview' => $this->preview($message),
            'message_length' => strlen($message),
        ]);

        $session = $this->getOrCreateSession($normalizedPhone, $normalizedAccount);
        $wasRecentlyCreated = $session->wasRecentlyCreated;

        Log::debug('WhatsApp chatbot: sesion cargada', [
            'phone' => $normalizedPhone,
            'account' => $normalizedAccount,
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

        $reply = $this->processStep($session, $message, $incoming);

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

    private function processStep(ChatbotSession $session, string $message, array $incoming = []): string
    {
        Log::debug('WhatsApp chatbot: entrando a processStep', [
            'session_id' => $session->id,
            'phone' => $session->phone,
            'step' => $session->step,
        ]);

        return match ($session->step) {
            'inicio' => $this->handleInicio($session, $message),
            'consulta_hora_menu' => $this->handleConsultaHoraMenu($session, $message),
            'consulta_horario_cedula' => $this->handleConsultaHorarioCedula($session, $message),
            'consulta_horario_terminal' => $this->handleConsultaHorarioTerminal($session, $message),
            'consulta_horario_fecha' => $this->handleConsultaHorarioFecha($session, $message),
            self::STEP_SG_TIPO => $this->handleServiciosGeneralesTipo($session, $message),
            self::STEP_SG_TERMINAL => $this->handleServiciosGeneralesTerminal($session, $message),
            self::STEP_SG_IMAGEN => $this->registrarRequerimientoServiciosGenerales($session, $incoming),
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
            $session->step = 'consulta_horario_cedula';
            $session->context = [
                'intent' => 'consultar_horario',
            ];

            return 'Por favor indica la cedula del usuario.';
        }

        if ($option === '2') {
            $session->step = self::STEP_SG_TIPO;
            $session->context = [
                'intent' => 'reportar_averia',
            ];

            return $this->serviciosGeneralesTiposMessage();
        }

        $session->step = 'consulta_hora_menu';

        return $this->consultaHoraMenuMessage();
    }

    private function handleServiciosGeneralesTipo(ChatbotSession $session, string $message): string
    {
        $tipos = [
            '1' => ['tipo' => 'internet', 'label' => 'No tengo internet'],
            '2' => ['tipo' => 'electricidad', 'label' => 'No tengo luz'],
            '3' => ['tipo' => 'sistema_frizado', 'label' => 'Se me friso el sistema'],
            '4' => ['tipo' => 'inversor', 'label' => 'Cambiar el inversor'],
        ];

        $option = trim($message);

        if (!isset($tipos[$option])) {
            return $this->serviciosGeneralesTiposMessage();
        }

        $session->step = self::STEP_SG_TERMINAL;
        $session->context = array_merge(
            is_array($session->context) ? $session->context : [],
            $tipos[$option]
        );

        return 'Indica el codigo del terminal afectado.';
    }

    private function handleServiciosGeneralesTerminal(ChatbotSession $session, string $message): string
    {
        $terminalCodigo = trim($message);

        if ($terminalCodigo === '' || strlen($terminalCodigo) < 2) {
            return 'No pude identificar el codigo del terminal. Envia solo el codigo del terminal afectado.';
        }

        $session->step = self::STEP_SG_IMAGEN;
        $session->context = array_merge(
            is_array($session->context) ? $session->context : [],
            ['terminal_codigo' => $terminalCodigo]
        );

        return "Perfecto. Terminal {$terminalCodigo} recibido.\n\nAhora envia la imagen de la averia para registrar la solicitud.";
    }

    private function registrarRequerimientoServiciosGenerales(ChatbotSession $session, array $incoming): string
    {
        $context = is_array($session->context) ? $session->context : [];
        $tipo = (string) ($context['tipo'] ?? '');
        $tipoLabel = (string) ($context['label'] ?? '');
        $terminalCodigo = trim((string) ($context['terminal_codigo'] ?? ''));
        $attachmentUrl = $this->normalizeAttachmentUrl($incoming['attachment_url'] ?? null);
        $attachmentMessageId = $this->normalizeMessageId($incoming['message_id'] ?? null);

        if ($tipo === '' || $terminalCodigo === '') {
            $session->step = 'inicio';
            $session->context = null;

            return 'Perdi el contexto de la solicitud. Por favor inicia de nuevo y elige la opcion 2.';
        }

        if ($attachmentUrl === null) {
            return 'Necesito que envies una imagen para continuar con el registro de la averia.';
        }

        try {
            $requerimiento = ServicioGeneralRequerimiento::create([
                'user_id' => $this->chatbotUserId(),
                'whatsapp_phone' => $session->phone,
                'tipo' => $tipo,
                'titulo' => 'Averia',
                'descripcion' => "Solicitud recibida por WhatsApp.\n\nTipo: {$tipoLabel}\nTerminal: {$terminalCodigo}",
                'prioridad' => 'media',
                'estado' => 'pendiente',
                'progreso' => 0,
                'attachment_url' => $attachmentUrl,
                'attachment_message_id' => $attachmentMessageId,
            ]);
        } catch (\Throwable $e) {
            Log::error('WhatsApp chatbot: error registrando averia de servicios generales', [
                'phone' => $session->phone,
                'tipo' => $tipo,
                'terminal_codigo' => $terminalCodigo,
                'attachment_url' => $attachmentUrl,
                'message' => $e->getMessage(),
            ]);

            $session->step = 'inicio';
            $session->context = null;

            return 'No pude registrar la averia en este momento. Por favor intenta mas tarde.';
        }

        $session->step = 'inicio';
        $session->context = null;

        return "Solicitud registrada correctamente.\n\nCodigo: {$requerimiento->ticket_codigo}\nTipo: {$tipoLabel}\nTerminal: {$terminalCodigo}\nImagen: Recibida\nEstado: Pendiente";
    }

    private function handleConsultaHorarioCedula(ChatbotSession $session, string $message): string
    {
        $cedula = $this->normalizeCedula($message);

        if ($cedula === '') {
            return 'No pude leer la cedula. Por favor envia solo los numeros de la cedula.';
        }

        $context = is_array($session->context) ? $session->context : [];
        $context['cedula'] = $cedula;

        $session->step = 'consulta_horario_terminal';
        $session->context = $context;

        return 'Ahora indica la terminal/agencia.';
    }

    private function handleConsultaHorarioTerminal(ChatbotSession $session, string $message): string
    {
        $terminal = $this->normalizeTerminal($message);

        if ($terminal === '') {
            return 'No pude leer la terminal. Por favor envia el numero de terminal/agencia.';
        }

        $context = is_array($session->context) ? $session->context : [];
        $context['terminal'] = $terminal;

        $session->step = 'consulta_horario_fecha';
        $session->context = $context;

        return 'Por ultimo indica la fecha a consultar en formato AAAA-MM-DD. Ejemplo: 2026-05-18';
    }

    private function handleConsultaHorarioFecha(ChatbotSession $session, string $message): string
    {
        $fecha = $this->parseFecha($message);

        if (!$fecha) {
            return 'No pude leer la fecha. Envia la fecha en formato AAAA-MM-DD. Ejemplo: 2026-05-18';
        }

        $context = is_array($session->context) ? $session->context : [];
        $cedula = (string) ($context['cedula'] ?? '');
        $terminal = (string) ($context['terminal'] ?? '');

        if ($cedula === '' || $terminal === '') {
            return $this->resetToInicio($session);
        }

        $reply = $this->consultarHorario($cedula, $terminal, $fecha);

        $session->step = 'inicio';
        $session->context = null;

        return $reply;
    }

    private function consultarHorario(string $cedula, string $terminal, string $fecha): string
    {
        $token = Token::find(1);

        if (!$token) {
            return 'No pude consultar la asistencia porque no hay un token generado.';
        }

        if (now()->greaterThan($token->fecha)) {
            return 'No pude consultar la asistencia porque el token de Lotobet ha expirado.';
        }

        try {
            $response = Http::timeout(45)
                ->acceptJson()
                ->withHeaders([
                    'AhfCC' => 'yB0tt5KW3wVVCYYtCpen',
                    'AhfVB' => 'xSzdgtOKbGRhUhtv1ois',
                ])
                ->withOptions(['verify' => false])
                ->get("https://ltkadapi.lotobet.bet/api/V1/var4XZ3ojQiPZq5BpI/{$token->token}/{$fecha}/05");
        } catch (\Throwable $e) {
            Log::error('WhatsApp chatbot: error consultando asistencias Lotobet', [
                'cedula' => $cedula,
                'terminal' => $terminal,
                'fecha' => $fecha,
                'error' => $e->getMessage(),
            ]);

            return 'No pude consultar la asistencia en este momento. Intentalo nuevamente mas tarde.';
        }

        if (!$response->successful()) {
            Log::warning('WhatsApp chatbot: respuesta no exitosa de asistencias Lotobet', [
                'status' => $response->status(),
                'fecha' => $fecha,
                'body' => $this->preview($response->body()),
            ]);

            return 'No pude consultar la asistencia en Lotobet para esa fecha.';
        }

        $payload = $response->json();
        $asistencias = is_array($payload['Content'] ?? null) ? $payload['Content'] : [];
        $asistencia = $this->findAsistencia($asistencias, $cedula, $terminal);
        $agencia = $this->findAgenciaByTerminal($terminal);

        if (!$asistencia) {
            $mensaje = "No encontre asistencia para la cedula {$cedula}, terminal {$terminal}, fecha {$fecha}.";

            if ($agencia) {
                $mensaje .= "\n\nHorario registrado para la terminal:\n" . $this->formatAgenciaHorario($agencia);
            }

            return $mensaje;
        }

        $nombreUsuario = $asistencia['usuario'] ?? $asistencia['nombre'] ?? $asistencia['nombre_usuario'] ?? 'No disponible';
        $cedulaApi = $asistencia['cedula'] ?? $cedula;
        $terminalApi = $asistencia['agencia'] ?? $asistencia['terminal'] ?? $terminal;
        $primerLogin = $asistencia['primer_login'] ?? $asistencia['entrada'] ?? null;
        $ultimoLogin = $asistencia['ultimo_logout'] ?? $asistencia['ultimo_login'] ?? $asistencia['salida'] ?? 'No disponible';
        $primerLoginHora = $this->formatHoraSinFecha($primerLogin);
        $ultimoLoginHora = $this->formatHoraSinFecha($ultimoLogin);
        $puntualidad = $this->formatPuntualidadLlegada($primerLogin, $agencia, $fecha);

        $mensaje = "Consulta de horario Lotobet\n\n"
            . "Usuario: {$nombreUsuario}\n"
            . "Cedula: {$cedulaApi}\n"
            . "Terminal: {$terminalApi}\n"
            . "Fecha: {$fecha}\n"
            . "Primer login: {$primerLoginHora}\n"
            . "Ultimo login: {$ultimoLoginHora}\n"
            . "{$puntualidad}";

        $mensaje .= "\n\n" . ($agencia
            ? $this->formatAgenciaHorario($agencia)
            : "No encontre esa terminal en el modulo de agencias.");

        return $mensaje;
    }

    private function findAsistencia(array $asistencias, string $cedula, string $terminal): ?array
    {
        foreach ($asistencias as $asistencia) {
            if (!is_array($asistencia)) {
                continue;
            }

            $cedulaApi = $this->normalizeCedula((string) ($asistencia['cedula'] ?? ''));
            $terminalApi = $this->normalizeTerminal((string) ($asistencia['agencia'] ?? $asistencia['terminal'] ?? ''));

            if ($cedulaApi === $cedula && $terminalApi === $terminal) {
                return $asistencia;
            }
        }

        return null;
    }

    private function findAgenciaByTerminal(string $terminal): ?Agencia
    {
        return Agencia::query()
            ->whereRaw("COALESCE(NULLIF(TRIM(LEADING '0' FROM terminal), ''), '0') = ?", [$terminal])
            ->first();
    }

    private function formatAgenciaHorario(Agencia $agencia): string
    {
        $nombre = $agencia->nombre_agencia ?: $agencia->agencia ?: 'No disponible';
        $terminal = $agencia->terminal ?: 'No disponible';
        $horarioAm = $agencia->horario_am ?: 'No registrado';
        $horarioPm = $agencia->horario_pm ?: 'No registrado';

        return "Agencia: {$nombre}\n"
            . "Terminal registrada: {$terminal}\n"
            . "Horario AM: {$horarioAm}\n"
            . "Horario PM: {$horarioPm}";
    }

    private function formatPuntualidadLlegada(?string $primerLogin, ?Agencia $agencia, string $fecha): string
    {
        if (!$primerLogin || !$agencia) {
            return "Minutos adelantados: No disponible\nMinutos atrasados: No disponible";
        }

        try {
            $llegada = Carbon::parse($primerLogin);
        } catch (\Throwable) {
            return "Minutos adelantados: No disponible\nMinutos atrasados: No disponible";
        }

        $horarios = array_filter([
            $this->parseInicioHorario($agencia->horario_am, $fecha, 'AM'),
            $this->parseInicioHorario($agencia->horario_pm, $fecha, 'PM'),
        ]);

        if ($horarios === []) {
            return "Minutos adelantados: No disponible\nMinutos atrasados: No disponible";
        }

        $horario = collect($horarios)
            ->sortBy(fn (array $item) => abs($llegada->diffInSeconds($item['inicio'], false)))
            ->first();

        $diffSeconds = $llegada->diffInSeconds($horario['inicio'], false);
        $diffMinutes = (int) floor(abs($diffSeconds) / 60);
        $minutosAdelantado = $diffSeconds > 0 ? $diffMinutes : 0;
        $minutosAtrasado = $diffSeconds < 0 ? $diffMinutes : 0;
        return "Minutos adelantados: {$minutosAdelantado}\n"
            . "Minutos atrasados: {$minutosAtrasado}";
    }

    private function formatHoraSinFecha(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '' || strcasecmp($value, 'No disponible') === 0) {
            return 'No disponible';
        }

        try {
            return Carbon::parse($value)->format('g:i A');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function parseInicioHorario(?string $horario, string $fecha, string $turno): ?array
    {
        $horario = trim((string) $horario);

        if ($horario === '') {
            return null;
        }

        $inicio = trim(explode('/', $horario)[0] ?? '');

        if ($inicio === '') {
            return null;
        }

        foreach (['g:i A', 'h:i A', 'G:i', 'H:i'] as $format) {
            try {
                $parsed = Carbon::createFromFormat("Y-m-d {$format}", "{$fecha} {$inicio}");

                if ($parsed) {
                    return [
                        'turno' => $turno,
                        'inicio' => $parsed,
                    ];
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function consultaHoraMenuMessage(): string
    {
        return "Hola, como estas? Soy tu asistente virtual y estoy aqui para servirte.\n\n"
            . "Por favor responde solo numericamente:\n\n"
            . "1- consultar el horario de servicio\n"
            . "2- averias";
    }

    private function serviciosGeneralesTiposMessage(): string
    {
        return "Selecciona el tipo de averia escribiendo solo el numero:\n\n"
            . "1-No tengo internet\n"
            . "2-No tengo luz\n"
            . "3-Se me friso el sistema\n"
            . "4-Cambiar el inversor";
    }

    public function inactivityFarewellMessage(): string
    {
        return "Gracias por comunicarte con nosotros. Por inactividad, esta sesion ha sido cerrada.\n\n"
            . "Cuando necesites ayuda, escribenos nuevamente.";
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

    private function getOrCreateSession(string $phone, string $account): ChatbotSession
    {
        return ChatbotSession::firstOrCreate(
            [
                'account' => $account,
                'phone' => $phone,
            ],
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
            && $session->last_interaction_at->diffInSeconds(now()) >= self::SESSION_TIMEOUT_SECONDS;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return $digits !== '' ? $digits : trim($phone);
    }

    private function normalizeAccount(?string $account): string
    {
        $account = trim((string) $account);

        return $account !== '' ? $account : 'default';
    }

    private function normalizeCedula(string $cedula): string
    {
        return preg_replace('/\D+/', '', $cedula) ?? '';
    }

    private function normalizeTerminal(string $terminal): string
    {
        $digits = preg_replace('/\D+/', '', $terminal) ?? '';
        $normalized = ltrim($digits, '0');

        return $normalized !== '' ? $normalized : $digits;
    }

    private function normalizeAttachmentUrl(mixed $attachment): ?string
    {
        if (!is_string($attachment)) {
            return null;
        }

        $attachment = trim($attachment);

        if ($attachment === '' || in_array(strtolower($attachment), ['false', 'null'], true)) {
            return null;
        }

        return $attachment;
    }

    private function normalizeMessageId(mixed $messageId): ?string
    {
        if ($messageId === null || is_array($messageId)) {
            return null;
        }

        $messageId = trim((string) $messageId);

        return $messageId !== '' ? $messageId : null;
    }

    private function chatbotUserId(): int
    {
        $user = User::firstOrCreate(
            ['email' => 'chatbot@crm.local'],
            [
                'name' => 'Chatbot CRM',
                'password' => Hash::make(Str::random(32)),
            ]
        );

        return (int) $user->id;
    }

    private function parseFecha(string $fecha): ?string
    {
        $fecha = trim($fecha);
        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y'];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $fecha);

                if ($parsed && $parsed->format($format) === $fecha) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function preview(string $value, int $limit = 160): string
    {
        if (strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, $limit) . '...';
    }
}
