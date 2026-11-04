<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarPremiosLotobetRequest;
use App\Models\Premio;
use App\Models\PremioNet;
use App\Services\Lotobet\LotobetSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PremioController extends Controller
{
    public function getPremiosLotobet(ConsultarPremiosLotobetRequest $request): JsonResponse
    {
        try {
            $data = app(LotobetSessionService::class)->getPremios($request->validated('fecha'));
        } catch (RuntimeException $exception) {
            return response()->json(['error' => strip_tags($exception->getMessage())], 502);
        }

        return response()->json(['premios' => $data['Content'], 'code' => 0, 'message' => 'Datos obtenidos correctamente.']);
    }

    public function savePremiosLotobet(ConsultarPremiosLotobetRequest $request): JsonResponse
    {
        ini_set('memory_limit', '1G');
        set_time_limit(300);
        $fecha = $request->validated('fecha');
        if (Premio::query()->whereDate('fecha', $fecha)->exists()) {
            return response()->json(['message' => 'Ya hay data guardada en la fecha: '.$fecha]);
        }

        try {
            $response = app(LotobetSessionService::class)->getPremios($fecha);
        } catch (RuntimeException $exception) {
            return response()->json(['error' => strip_tags($exception->getMessage())], 502);
        }

        $data = array_map(fn (array $premio): array => [
            'consorcio_id' => $premio['consorcio_id'] ?? null,
            'agencia_id' => $premio['agencia_id'] ?? null,
            'producto_id' => $premio['producto_id'] ?? null,
            'descripcion' => $premio['descripcion'] ?? null,
            'monto' => $premio['monto'] ?? null,
            'fecha' => $premio['fecha'] ?? null,
        ], $response['Content']);

        (new Premio)->getConnection()->transaction(function () use ($data): void {
            foreach (array_chunk($data, 5000) as $chunk) {
                Premio::query()->insert($chunk);
            }
        });

        return response()->json([
            'message' => 'Datos guardados correctamente. Total insertados: '.count($data),
            'total' => count($data),
        ]);
    }

    public function deletePremiosLotobet(Request $request)
    {
        header('Content-Type: application/json');

        $fecha = $request->query('fecha');

        Premio::whereDate('fecha', $fecha)->delete();

        return response()->json([
            'message' => 'Datos eliminados correctamente',
        ]);
    }

    public function getPremiosLotonet(Request $request)
    {
        header('Content-Type: application/json');

        $curl = curl_init();

        $fecha = $request->query('fecha');

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "http://contable.apploteka.com/api/finan/premios/{$fecha}/5",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_POSTFIELDS => '{
            "usuario": {
                "username": "fcolombo",
                "password": "RUHTe9t9ZEUzHsyT"
            }
            }',
            CURLOPT_HTTPHEADER => [
                'token: ZFozLWdBYyqERusVdTsW',
                'Content-Type: application/json',
                'Cookie: _orkapi_session=QkViaFBzMmJPTEU0U3YxWEEyd0k4eVZuR2RkTFV2bktWY0srZ2NyaWc1Y2J1eGhhdTRxZXZ3VDByTG9vT3VFL0ZpTlNvalgzK3dOcG5EZGNHTDAxbE5OMGU3dUFzaHYxYVlkSzhFc241eE52YXpaaHNOcmFtbUVPdnVTSUZ1L1A3UEVoSDhtV3QvUVZJUy9USU45WUU4OU03SUUxZ0JjQXNVUFBRY2Z6VlFRPS0tc1ZQNDA1NExkWldOTDluU2lLVzhLdz09--384f330e993c1c076f324f7ed51ee9439ccf2a85',
            ],
        ]);

        $response = curl_exec($curl);

        curl_close($curl);

        $items = json_decode($response, true);

        $data = $items['data']['result'];

        return response()->json(['premios' => $data, 'code' => $items['code'], 'message' => '']);
    }

    public function savePremiosLotonet(Request $request)
    {
        ini_set('memory_limit', '1G');
        ini_set('max_execution_time', 300); // 300 segundos = 5 minutos
        set_time_limit(300);                // alternativa equivalente
        header('Content-Type: application/json');

        $curl = curl_init();

        $fecha = $request->query('fecha');

        $existe = PremioNet::whereDate('fecha', $fecha)->exists();

        if ($existe) {
            return response()->json(['message' => 'Ya hay data guardada en la fecha: '.$fecha]);
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => "http://contable.apploteka.com/api/finan/premios/{$fecha}/5",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_POSTFIELDS => '{
            "usuario": {
                "username": "fcolombo",
                "password": "RUHTe9t9ZEUzHsyT"
            }
            }',
            CURLOPT_HTTPHEADER => [
                'token: ZFozLWdBYyqERusVdTsW',
                'Content-Type: application/json',
                'Cookie: _orkapi_session=QkViaFBzMmJPTEU0U3YxWEEyd0k4eVZuR2RkTFV2bktWY0srZ2NyaWc1Y2J1eGhhdTRxZXZ3VDByTG9vT3VFL0ZpTlNvalgzK3dOcG5EZGNHTDAxbE5OMGU3dUFzaHYxYVlkSzhFc241eE52YXpaaHNOcmFtbUVPdnVTSUZ1L1A3UEVoSDhtV3QvUVZJUy9USU45WUU4OU03SUUxZ0JjQXNVUFBRY2Z6VlFRPS0tc1ZQNDA1NExkWldOTDluU2lLVzhLdz09--384f330e993c1c076f324f7ed51ee9439ccf2a85',
            ],
        ]);

        $response = curl_exec($curl);

        curl_close($curl);

        $items = json_decode($response, true);

        $data = $items['data']['result'];

        if (! empty($data)) {
            foreach (array_chunk($data, 5000) as $chunk) {
                DB::table('premios_net')->insert($chunk);
            }
        }

        return response()->json([
            'message' => 'Datos guardados correctamente. Total insertados: '.count($data),
            'total' => count($data),
        ]);
    }

    public function deletePremiosLotonet(Request $request)
    {
        header('Content-Type: application/json');
        $fecha = $request->query('fecha');
        PremioNet::whereDate('fecha', $fecha)->delete();

        return response()->json([
            'message' => 'Datos eliminados correctamente',
        ]);
    }
}
