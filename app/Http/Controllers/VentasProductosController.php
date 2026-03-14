<?php

namespace App\Http\Controllers;

use App\Models\Agencia;
use App\Models\Token;
use App\Models\VtProducto;
use App\Models\VtProductoNet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentasProductosController extends Controller
{
    public function getVentasProductosLotobet(Request $request)
    {
        header('Content-Type: application/json');

        $curl = curl_init();

        $fecha = $request->query('fecha');

        $token = Token::find(1);

        if (!$token) {
            return response()->json(['error' => 'Genere un token'], 404);
        }

        $fechaActual = now();
        if ($fechaActual->greaterThan($token->fecha)) {
            return response()->json(['error' => 'El token ha expirado, genere uno nuevo'], 401);
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://ltkadapi.lotobet.bet/api/V1/kotFQlCe5XVFoJcjEz/{$token->token}/{$fecha}/05",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'AhfCC: yB0tt5KW3wVVCYYtCpen',
                'AhfVB: xSzdgtOKbGRhUhtv1ois'
            ),
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $ventas = json_decode($response, true);

        $contenido = $ventas['Content'] ?? [];
        if (!is_array($contenido)) {
            $contenido = [];
        }

        $normalizarClave = static function ($value): string {
            $raw = trim((string) $value);
            if ($raw === '') {
                return '';
            }

            $sinCeros = ltrim($raw, '0');
            return $sinCeros === '' ? '0' : $sinCeros;
        };

        $agenciasActivas = Agencia::query()
            ->select(['agencia', 'nombre_agencia', 'terminal'])
            ->where('estatus', 1)
            ->whereNotNull('terminal')
            ->get();

        $agenciasActivasByTerminal = [];
        foreach ($agenciasActivas as $agenciaActiva) {
            $terminalRaw = trim((string) ($agenciaActiva->terminal ?? ''));
            $terminalNormalizada = $normalizarClave($terminalRaw);
            if ($terminalNormalizada === '') {
                continue;
            }

            if (!isset($agenciasActivasByTerminal[$terminalNormalizada])) {
                $agenciasActivasByTerminal[$terminalNormalizada] = [
                    'agencia' => trim((string) ($agenciaActiva->agencia ?? '')),
                    'nombre_agencia' => trim((string) ($agenciaActiva->nombre_agencia ?? '')),
                    'terminal' => $terminalRaw !== '' ? $terminalRaw : $terminalNormalizada,
                ];
            }
        }

        $terminalesActivasNormalizadas = collect(array_keys($agenciasActivasByTerminal));

        $terminalesConVentaNormalizadas = collect($contenido)
            ->map(fn ($item) => $normalizarClave($item['agencia_id'] ?? ''))
            ->filter()
            ->unique()
            ->values();

        $agenciasTabla = Agencia::query()
            ->select(['terminal'])
            ->whereNotNull('terminal')
            ->get();

        $terminalesTablaNormalizadas = $agenciasTabla
            ->map(fn ($agencia) => $normalizarClave($agencia->terminal ?? ''))
            ->filter()
            ->unique()
            ->values();

        $terminalesNoRegistradasMap = [];
        foreach ($contenido as $item) {
            $terminalRaw = trim((string) ($item['agencia_id'] ?? ''));
            $terminalNormalizada = $normalizarClave($terminalRaw);

            if ($terminalNormalizada === '') {
                continue;
            }

            if ($terminalesTablaNormalizadas->contains($terminalNormalizada)) {
                continue;
            }

            if (!isset($terminalesNoRegistradasMap[$terminalNormalizada])) {
                $terminalesNoRegistradasMap[$terminalNormalizada] = $terminalRaw;
            }
        }

        $terminalesNoRegistradas = array_values($terminalesNoRegistradasMap);

        $agenciasActivasConVenta = $terminalesActivasNormalizadas
            ->filter(fn ($terminal) => $terminalesConVentaNormalizadas->contains($terminal))
            ->count();

        $agenciasSinVentasListado = [];
        foreach ($agenciasActivasByTerminal as $terminalNormalizada => $agenciaActivaData) {
            if ($terminalesConVentaNormalizadas->contains($terminalNormalizada)) {
                continue;
            }

            $nombreAgencia = $agenciaActivaData['nombre_agencia'] !== ''
                ? $agenciaActivaData['nombre_agencia']
                : ($agenciaActivaData['agencia'] !== '' ? $agenciaActivaData['agencia'] : $agenciaActivaData['terminal']);

            $agenciasSinVentasListado[] = [
                'agencia_id' => $agenciaActivaData['terminal'],
                'nombre_agencia' => $nombreAgencia,
                'terminal' => $agenciaActivaData['terminal'],
            ];
        }

        $totalAgenciasActivas = $terminalesActivasNormalizadas->count();
        $agenciasActivasSinVenta = max(0, $totalAgenciasActivas - $agenciasActivasConVenta);

        $agencias = Agencia::query()
            ->select(['agencia', 'nombre_agencia', 'terminal', 'ciudad', 'ruta', 'operador', 'coordinador', 'estatus'])
            ->whereNotNull('terminal')
            ->get();

        $agenciasByTerminal = [];
        foreach ($agencias as $agencia) {
            $terminal = trim((string) ($agencia->terminal ?? ''));
            if ($terminal === '') {
                continue;
            }

            $agenciasByTerminal[$terminal] = [
                'agencia' => $agencia->agencia,
                'nombre_agencia' => $agencia->nombre_agencia,
                'ciudad' => $agencia->ciudad,
                'ruta' => $agencia->ruta,
                'operador' => $agencia->operador,
                'coordinador' => $agencia->coordinador,
                'estatus' => (int) ($agencia->estatus ?? 0),
            ];

            $terminalSinCeros = ltrim($terminal, '0');
            if ($terminalSinCeros !== '' && !isset($agenciasByTerminal[$terminalSinCeros])) {
                $agenciasByTerminal[$terminalSinCeros] = $agenciasByTerminal[$terminal];
            }
        }

        $ventasEnriquecidas = collect($contenido)->map(function ($item) use ($agenciasByTerminal) {
            $agenciaId = trim((string) ($item['agencia_id'] ?? ''));
            $agenciaLookup = $agenciasByTerminal[$agenciaId]
                ?? $agenciasByTerminal[ltrim($agenciaId, '0')]
                ?? ['agencia' => null, 'nombre_agencia' => null, 'ciudad' => null, 'ruta' => null, 'operador' => null, 'coordinador' => null, 'estatus' => 0];

            return array_merge($item, $agenciaLookup);
        })->values()->all();

        return response()->json([
            'ventas' => $ventasEnriquecidas,
            'resumen_agencias' => [
                'activas' => $totalAgenciasActivas,
                'con_ventas' => $agenciasActivasConVenta,
                'sin_ventas' => $agenciasActivasSinVenta,
                'agencias_sin_ventas' => $agenciasSinVentasListado,
                'terminales_no_registradas_count' => count($terminalesNoRegistradas),
                'terminales_no_registradas' => $terminalesNoRegistradas,
            ],
            'code' => $ventas['code'] ?? null,
            'message' => $ventas['msg'] ?? null,
        ]);
    }

    public function saveVentasProductosLotobet(Request $request)
    {
        ini_set('memory_limit', '1G'); // Aumentar el límite de memoria a 512MB
        ini_set('max_execution_time', 300); // 300 segundos = 5 minutos
        set_time_limit(300);                // alternativa equivalente
        header('Content-Type: application/json');

        $curl = curl_init();

        $fecha = $request->query('fecha');

        $token = Token::find(1);

        if (!$token) {
            return response()->json(['error' => 'Genere un token'], 404);
        }

        $fechaActual = now();
        if ($fechaActual->greaterThan($token->fecha)) {
            return response()->json(['error' => 'El token ha expirado, genere uno nuevo'], 401);
        }

        $existe = VtProducto::whereDate('fecha', $fecha)->exists();

        if ($existe) {
            return response()->json(['message' => 'Ya hay data guardada en la fecha: ' . $fecha]);
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://ltkadapi.lotobet.bet/api/V1/kotFQlCe5XVFoJcjEz/{$token->token}/{$fecha}/05",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'AhfCC: yB0tt5KW3wVVCYYtCpen',
                'AhfVB: xSzdgtOKbGRhUhtv1ois'
            ),
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $ventas = json_decode($response, true);

        $data = [];

        foreach ($ventas['Content'] as $v) {
            $data[] = [
                'consorcio_id'  => $v['consorcio_id'] ?? null,
                'agencia_id'    => $v['agencia_id'] ?? null,
                'producto_id'   => $v['producto_id'] ?? null,
                'descripcion'   => $v['descripcion'] ?? null,
                'monto'         => $v['monto'] ?? 0,
                'fecha'         => $fecha,
                'comision'      => $v['comision'] ?? null,
                'numero_sorteo' => $v['numero_sorteo'] ?? null,
                'comision_supervisor' => $v['comision_supervisor'] ?? null,
            ];
        }

        if (!empty($data)) {
            foreach (array_chunk($data, 5000) as $chunk) {
                DB::table('ventas_producto_bet')->insert($chunk);
            }
        }

        return response()->json([
            'message' => 'Datos guardados correctamente. Total insertados: ' . count($data),
            'total' => count($data)
        ]);
    }

    public function deleteVentasProductosLotobet(Request $request)
    {
        header('Content-Type: application/json');

        $fecha = $request->query('fecha');

        VtProducto::whereDate('fecha', $fecha)->delete();

        return response()->json([
            'message' => 'Datos eliminados correctamente',
        ]);
    }

    public function getVentasProductosLotonet(Request $request)
    {
        header('Content-Type: application/json');

        $curl = curl_init();

        $fecha = $request->query('fecha');

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://contable.apploteka.com//api/finan/ventas_loteria/{$fecha}/5",
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
            CURLOPT_HTTPHEADER => array(
                'token: ZFozLWdBYyqERusVdTsW',
                'Content-Type: application/json',
                'Cookie: _orkapi_session=RkZLWFpIMnM1UTdUdjRXVzNuMFRmZFZnQ2U5N0JoV0JaSzBheUFlZ21TSVoyUEhWWFc2Y2R4Nzd2SmVhQXJKOGtsSktHWnNmelgzWGsxcmJESEVkcXRlWW5tdGpzU1ZZcXRBZFNva2lqL3pGMFppZFZnZUxPUXBscWxLYVdVcUwzdURYb1V5bGJwanZkeDdJTGUzZndkV3FxNmtiMjdvNkxpU0ZQK2RWRU1nPS0tbkVwL215TXpYTXpLS1lYYXJTR3Y2UT09--7e272c2a327d71d9feb7996870d828122936b682'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $ventas = json_decode($response, true);

        $data = $ventas['data']['result'] ?? [];

        return response()->json(['ventas' => $data, 'code' => $ventas['code'], 'message' => '']);
    }

    public function saveVentasProductosLotonet(Request $request)
    {
        ini_set('memory_limit', '1G'); // Aumentar el límite de memoria a 512MB
        ini_set('max_execution_time', 300); // 300 segundos = 5 minutos
        set_time_limit(300);                // alternativa equivalente
        header('Content-Type: application/json');

        $curl = curl_init();

        $fecha = $request->query('fecha');

        $existe = VtProductoNet::whereDate('fecha', $fecha)->exists();

        if ($existe) {
            return response()->json(['message' => 'Ya hay data guardada en la fecha: ' . $fecha]);
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://contable.apploteka.com//api/finan/ventas_loteria/{$fecha}/5",
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
            CURLOPT_HTTPHEADER => array(
                'token: ZFozLWdBYyqERusVdTsW',
                'Content-Type: application/json',
                'Cookie: _orkapi_session=RkZLWFpIMnM1UTdUdjRXVzNuMFRmZFZnQ2U5N0JoV0JaSzBheUFlZ21TSVoyUEhWWFc2Y2R4Nzd2SmVhQXJKOGtsSktHWnNmelgzWGsxcmJESEVkcXRlWW5tdGpzU1ZZcXRBZFNva2lqL3pGMFppZFZnZUxPUXBscWxLYVdVcUwzdURYb1V5bGJwanZkeDdJTGUzZndkV3FxNmtiMjdvNkxpU0ZQK2RWRU1nPS0tbkVwL215TXpYTXpLS1lYYXJTR3Y2UT09--7e272c2a327d71d9feb7996870d828122936b682'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $ventas = json_decode($response, true);

        $data = $ventas['data']['result'] ?? [];

        if (!empty($data)) {
            foreach (array_chunk($data, 5000) as $chunk) {
                DB::table('ventas_producto_net')->insert($chunk);
            }
        }

        return response()->json([
            'message' => 'Datos guardados correctamente. Total insertados: ' . count($data),
            'total' => count($data)
        ]);
    }

    public function deleteVentasProductosLotonet(Request $request)
    {
        header('Content-Type: application/json');

        $fecha = $request->query('fecha');

        VtProductoNet::whereDate('fecha', $fecha)->delete();

        return response()->json([
            'message' => 'Datos eliminados correctamente',
        ]);
    }
}
