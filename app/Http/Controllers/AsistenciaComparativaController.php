<?php

namespace App\Http\Controllers;

use App\Models\Agencia;
use App\Models\Token;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Throwable;

class AsistenciaComparativaController extends Controller
{
    public function index(Request $request)
    {
        return view('agencias.asistencia_comparativa');
    }

    public function list(Request $request)
    {
        $fecha = $request->input('fecha', now()->toDateString());
        $soloIncumplidas = $request->input('solo_incumplidas', '1') === '1';

        try {
            $agencias = Agencia::query()
                ->select('id', 'agencia', 'nombre_agencia', 'terminal', 'horario_am', 'horario_pm')
                ->whereNotNull('terminal')
                ->where(function ($q) {
                    $q->whereNotNull('horario_am')
                        ->orWhereNotNull('horario_pm');
                })
                ->get();

            $mapAsistencia = $this->consolidarAsistenciasPorTerminalDesdeApi($fecha);

            $rows = [];

            foreach ($agencias as $agencia) {
                $terminalKey = $this->normalizarTerminal($agencia->terminal);
                $asistencia = $mapAsistencia[$terminalKey] ?? null;

                $entradaAmProgramada = $this->extraerHoraInicio($agencia->horario_am);
                $salidaAmProgramada = $this->extraerHoraFin($agencia->horario_am);
                $entradaPmProgramada = $this->extraerHoraInicio($agencia->horario_pm);
                $salidaPmProgramada = $this->extraerHoraFin($agencia->horario_pm);

                $entradaProgramada = $entradaAmProgramada ?: $entradaPmProgramada;
                $salidaProgramada = $salidaPmProgramada ?: $salidaAmProgramada;

                $entradaAmProgramadaDateTime = $this->parseFechaHora($fecha, $entradaAmProgramada);
                $salidaAmProgramadaDateTime = $this->parseFechaHora($fecha, $salidaAmProgramada);
                $entradaPmProgramadaDateTime = $this->parseFechaHora($fecha, $entradaPmProgramada);
                $salidaPmProgramadaDateTime = $this->parseFechaHora($fecha, $salidaPmProgramada);

                $entradaProgramadaDateTime = $this->parseFechaHora($fecha, $entradaProgramada);
                $salidaProgramadaDateTime = $this->parseFechaHora($fecha, $salidaProgramada);

                $entradasReales = $this->parsearHorasReales($asistencia['entradas'] ?? []);
                $salidasReales = $this->parsearHorasReales($asistencia['salidas'] ?? []);

                $entradaReal = $entradasReales[0] ?? null;
                $salidaReal = !empty($salidasReales) ? $salidasReales[array_key_last($salidasReales)] : null;

                $salidaAmReal = $this->seleccionarHoraCercana(
                    $salidasReales,
                    $salidaAmProgramadaDateTime,
                    $entradaAmProgramadaDateTime,
                    $entradaPmProgramadaDateTime
                );

                $entradaPmReal = $this->seleccionarHoraCercana(
                    $entradasReales,
                    $entradaPmProgramadaDateTime,
                    $salidaAmProgramadaDateTime,
                    $salidaPmProgramadaDateTime
                );

                $incumpleEntrada = false;
                $incumpleSalida = false;
                $minutosTarde = 0;
                $minutosSalidaAntes = 0;
                $observaciones = [];

                if ($entradaProgramadaDateTime && $entradaReal) {
                    if ($entradaReal->greaterThan($entradaProgramadaDateTime)) {
                        $incumpleEntrada = true;
                        $minutosTarde = $entradaProgramadaDateTime->diffInMinutes($entradaReal);
                        $observaciones[] = 'Entrada tardía';
                    }
                } elseif ($entradaProgramadaDateTime && !$entradaReal) {
                    $incumpleEntrada = true;
                    $observaciones[] = 'Sin registro de entrada';
                }

                if ($salidaProgramadaDateTime && $salidaReal) {
                    if ($salidaReal->lessThan($salidaProgramadaDateTime)) {
                        $incumpleSalida = true;
                        $minutosSalidaAntes = $salidaReal->diffInMinutes($salidaProgramadaDateTime);
                        $observaciones[] = 'Salida anticipada';
                    }
                } elseif ($salidaProgramadaDateTime && !$salidaReal) {
                    $incumpleSalida = true;
                    $observaciones[] = 'Sin registro de salida';
                }

                $incumplida = $incumpleEntrada || $incumpleSalida;

                if ($soloIncumplidas && !$incumplida) {
                    continue;
                }

                $rows[] = [
                    'agencia_id' => $agencia->id,
                    'agencia' => $agencia->agencia,
                    'nombre_agencia' => $agencia->nombre_agencia,
                    'terminal' => $agencia->terminal,
                    'horario_am' => $agencia->horario_am,
                    'horario_pm' => $agencia->horario_pm,
                    'entrada_am_programada' => $entradaAmProgramada,
                    'salida_am_programada' => $salidaAmProgramada,
                    'entrada_pm_programada' => $entradaPmProgramada,
                    'salida_pm_programada' => $salidaPmProgramada,
                    'entrada_programada' => $entradaProgramada,
                    'salida_programada' => $salidaProgramada,
                    'entrada_real' => $entradaReal ? $entradaReal->format('h:i A') : '-',
                    'salida_am_real' => $salidaAmReal ? $salidaAmReal->format('h:i A') : '-',
                    'entrada_pm_real' => $entradaPmReal ? $entradaPmReal->format('h:i A') : '-',
                    'salida_real' => $salidaReal ? $salidaReal->format('h:i A') : '-',
                    'minutos_tarde' => $minutosTarde,
                    'minutos_salida_antes' => $minutosSalidaAntes,
                    'incumple_entrada' => $incumpleEntrada,
                    'incumple_salida' => $incumpleSalida,
                    'incumplida' => $incumplida,
                    'estado' => $incumplida ? 'INCUMPLE' : 'CUMPLE',
                    'observaciones' => empty($observaciones) ? 'Cumple horario' : implode(' | ', $observaciones),
                    'fuente' => $asistencia['fuente'] ?? '-',
                ];
            }

            return response()->json([
                'fecha' => $fecha,
                'total' => count($rows),
                'incumplidas' => collect($rows)->where('incumplida', true)->count(),
                'data' => array_values($rows),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'data' => [],
            ], 422);
        }
    }

    private function consolidarAsistenciasPorTerminalDesdeApi(string $fecha): array
    {
        $bet = $this->obtenerAsistenciaLotobetApi($fecha);
        $net = $this->obtenerAsistenciaLotonetApi($fecha);

        $map = [];

        foreach ($bet as $row) {
            $terminalRaw = (string) ($row['agencia'] ?? $row['agencia_id'] ?? '');
            $terminalKey = $this->normalizarTerminal($terminalRaw);

            if (!isset($map[$terminalKey])) {
                $map[$terminalKey] = [
                    'entrada' => null,
                    'salida' => null,
                    'entradas' => [],
                    'salidas' => [],
                    'has_bet' => false,
                    'has_net' => false,
                    'fuente' => '-',
                ];
            }

            $entrada = $row['primer_login'] ?? $row['entrada'] ?? null;
            $salida = $row['ultimo_logout'] ?? $row['ultimo_login'] ?? $row['salida'] ?? null;

            if ($entrada) {
                $map[$terminalKey]['entradas'][] = $entrada;
                if (!$map[$terminalKey]['entrada'] || Carbon::parse($entrada)->lessThan(Carbon::parse($map[$terminalKey]['entrada']))) {
                    $map[$terminalKey]['entrada'] = $entrada;
                }
            }

            if ($salida) {
                $map[$terminalKey]['salidas'][] = $salida;
                if (!$map[$terminalKey]['salida'] || Carbon::parse($salida)->greaterThan(Carbon::parse($map[$terminalKey]['salida']))) {
                    $map[$terminalKey]['salida'] = $salida;
                }
            }

            $map[$terminalKey]['has_bet'] = true;
        }

        foreach ($net as $row) {
            $terminalRaw = (string) ($row['agencia'] ?? $row['terminal'] ?? $row['agencia_id'] ?? '');
            if (trim($terminalRaw) === '') {
                continue;
            }

            $terminalKey = $this->normalizarTerminal($terminalRaw);

            if (!isset($map[$terminalKey])) {
                $map[$terminalKey] = [
                    'entrada' => null,
                    'salida' => null,
                    'entradas' => [],
                    'salidas' => [],
                    'has_bet' => false,
                    'has_net' => false,
                    'fuente' => '-',
                ];
            }

            $entrada = $row['entrada'] ?? $row['primer_login'] ?? null;
            $salida = $row['salida'] ?? $row['ultimo_logout'] ?? null;

            if ($entrada) {
                $map[$terminalKey]['entradas'][] = $entrada;
                if (!$map[$terminalKey]['entrada'] || Carbon::parse($entrada)->lessThan(Carbon::parse($map[$terminalKey]['entrada']))) {
                    $map[$terminalKey]['entrada'] = $entrada;
                }
            }

            if ($salida) {
                $map[$terminalKey]['salidas'][] = $salida;
                if (!$map[$terminalKey]['salida'] || Carbon::parse($salida)->greaterThan(Carbon::parse($map[$terminalKey]['salida']))) {
                    $map[$terminalKey]['salida'] = $salida;
                }
            }

            $map[$terminalKey]['has_net'] = true;
        }

        foreach ($map as $terminalKey => $row) {
            $map[$terminalKey]['fuente'] = $row['has_bet'] && $row['has_net']
                ? 'BET/NET'
                : ($row['has_bet'] ? 'BET' : 'NET');
        }

        return $map;
    }

    private function obtenerAsistenciaLotobetApi(string $fecha): array
    {
        $token = Token::find(1);

        if (!$token) {
            throw new \RuntimeException('Genere un token de Lotobet.');
        }

        if (now()->greaterThan($token->fecha)) {
            throw new \RuntimeException('El token de Lotobet expiró, genere uno nuevo.');
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://ltkadapi.lotobet.bet/api/V1/var4XZ3ojQiPZq5BpI/{$token->token}/{$fecha}/05",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'AhfCC: yB0tt5KW3wVVCYYtCpen',
                'AhfVB: xSzdgtOKbGRhUhtv1ois',
            ],
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        $payload = json_decode((string) $response, true);
        $code = isset($payload['code']) ? (string) $payload['code'] : null;
        if ($code !== null && !in_array(strtolower(trim($code)), ['0', '00', '200', 'ok', 'success'], true)) {
            $msg = $payload['msg'] ?? $payload['message'] ?? 'Respuesta inválida de API Lotobet';
            throw new \RuntimeException($msg);
        }

        return $payload['Content'] ?? [];
    }

    private function obtenerAsistenciaLotonetApi(string $fecha): array
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://contable.apploteka.com//api/finan/asistencia_usuarios/{$fecha}/5",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_POSTFIELDS => '{
                "usuario": {
                    "username": "fjoselito",
                    "password": "mnXd5pSyF3HXjCC4"
                }
            }',
            CURLOPT_HTTPHEADER => [
                'token: ZFozLWdBYyqERusVdTsW',
                'Content-Type: application/json',
                'Cookie: _orkapi_session=RkZLWFpIMnM1UTdUdjRXVzNuMFRmZFZnQ2U5N0JoV0JaSzBheUFlZ21TSVoyUEhWWFc2Y2R4Nzd2SmVhQXJKOGtsSktHWnNmelgzWGsxcmJESEVkcXRlWW5tdGpzU1ZZcXRBZFNva2lqL3pGMFppZFZnZUxPUXBscWxLYVdVcUwzdURYb1V5bGJwanZkeDdJTGUzZndkV3FxNmtiMjdvNkxpU0ZQK2RWRU1nPS0tbkVwL215TXpYTXpLS1lYYXJTR3Y2UT09--7e272c2a327d71d9feb7996870d828122936b682',
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        $payload = json_decode((string) $response, true);
        $code = isset($payload['code']) ? (string) $payload['code'] : null;
        if ($code !== null && !in_array(strtolower(trim($code)), ['0', '00', '200', 'ok', 'success'], true)) {
            $msg = $payload['msg'] ?? $payload['message'] ?? 'Respuesta inválida de API Lotonet';
            throw new \RuntimeException($msg);
        }

        return $payload['data']['result'] ?? [];
    }

    private function normalizarTerminal(?string $terminal): string
    {
        if (!$terminal) {
            return '0';
        }

        $valor = ltrim(trim($terminal), '0');
        return $valor === '' ? '0' : $valor;
    }

    private function extraerHoraInicio(?string $horario): ?string
    {
        if (!$horario || !str_contains($horario, '/')) {
            return null;
        }

        $partes = explode('/', $horario);
        return isset($partes[0]) ? trim($partes[0]) : null;
    }

    private function extraerHoraFin(?string $horario): ?string
    {
        if (!$horario || !str_contains($horario, '/')) {
            return null;
        }

        $partes = explode('/', $horario);
        return isset($partes[1]) ? trim($partes[1]) : null;
    }

    private function parseFechaHora(string $fecha, ?string $hora): ?Carbon
    {
        if (!$hora) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d g:i A', $fecha . ' ' . strtoupper($hora));
        } catch (Throwable $e) {
            return null;
        }
    }

    private function parsearHorasReales(array $horas): array
    {
        $parsed = [];

        foreach ($horas as $hora) {
            if (!$hora) {
                continue;
            }

            try {
                $parsed[] = Carbon::parse($hora);
            } catch (Throwable $e) {
                // Ignorar valores no parseables
            }
        }

        usort($parsed, fn (Carbon $a, Carbon $b) => $a->getTimestamp() <=> $b->getTimestamp());

        return $parsed;
    }

    private function seleccionarHoraCercana(array $horas, ?Carbon $objetivo, ?Carbon $desde = null, ?Carbon $hasta = null): ?Carbon
    {
        $filtradas = array_values(array_filter($horas, function (Carbon $hora) use ($desde, $hasta) {
            if ($desde && $hora->lessThan($desde)) {
                return false;
            }

            if ($hasta && $hora->greaterThan($hasta)) {
                return false;
            }

            return true;
        }));

        if (empty($filtradas)) {
            return null;
        }

        if (!$objetivo) {
            return $filtradas[0];
        }

        usort($filtradas, fn (Carbon $a, Carbon $b) => abs($a->diffInSeconds($objetivo, false)) <=> abs($b->diffInSeconds($objetivo, false)));

        return $filtradas[0] ?? null;
    }
}
