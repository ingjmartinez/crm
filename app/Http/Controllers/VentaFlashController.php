<?php

namespace App\Http\Controllers;

use App\Models\Token;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentaFlashController extends Controller
{
    public function ventasFlashLotobet()
    {
        return view('lotobet.ventas-flash');
    }

    public function ventasFlashLotonet()
    {
        return view('lotonet.ventas-flash');
    }

    public function getVentasLotobet(Request $request)
    {
        header('Content-Type: application/json');

        $curl = curl_init();
        $fecha = $request->query('fecha');
        $token = Token::find(2);

        if (!$token) {
            return response()->json(['error' => 'Genere un token'], 404);
        }

        $fechaActual = now();
        if ($fechaActual->greaterThan($token->fecha)) {
            return response()->json(['error' => 'El token ha expirado, genere uno nuevo'], 401);
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://bjoselitoadapi.lotobet.bet/api/v1/FALUhPLdFAD/{$token->token}/{$fecha}/{$fecha}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'AhfCC: VJgej8Mn2yFYNXEr',
                'AhfVB: tnusa4hPNsSbAVPQ'
            ),
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
        ));

        $response = curl_exec($curl);
        $ventas = json_decode($response, true);
        return response()->json(['ventas' => $ventas['Content'], 'code' => $ventas['code'], 'message' => $ventas['msg']]);
    }

    public function saveVentasLotobet(Request $request)
    {
        ini_set('memory_limit', '1G');
        ini_set('max_execution_time', 360); // 300 segundos = 5 minutos
        set_time_limit(360);                // alternativa equivalente
        header('Content-Type: application/json');

        $curl = curl_init();
        $fecha = $request->query('fecha');
        $token = Token::find(2);

        if (!$token) {
            return response()->json(['error' => 'Genere un token'], 404);
        }

        $fechaActual = now();
        if ($fechaActual->greaterThan($token->fecha)) {
            return response()->json(['error' => 'El token ha expirado, genere uno nuevo'], 401);
        }

        $existe = DB::table('ventas_flash_bet')->whereDate('fecha', $fecha)->exists();

        if ($existe) {
            return response()->json(['message' => 'Ya hay data guardada en la fecha: ' . $fecha]);
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://bjoselitoadapi.lotobet.bet/api/v1/FALUhPLdFAD/{$token->token}/{$fecha}/{$fecha}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'AhfCC: VJgej8Mn2yFYNXEr',
                'AhfVB: tnusa4hPNsSbAVPQ'
            ),
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
        ));

        $response = curl_exec($curl);
        $ventas = json_decode($response, true);
        $data = [];

        foreach ($ventas['Content'] as $v) {
            $data[] = [
                'fecha' => $fecha,
                'grupo' => $v['Grupo'],
                'banca' => $v['Banca'],
                'numero_externo' => $v['NumeroExterno'],
                'venta_loteria' => $v['VentaLoteria'],
                'comision_loteria' => $v['ComisionLoteria'],
                'premios_pagado' => $v['PremiosPagado'],
                'venta_recarga' => $v['VentaRecarga'],
                'comision_recarga' => $v['ComisionRecarga'],
                'ventas_no_tradicional' => $v['VentasNoTrad'],
                'premios_pagados_no_tradicional' => $v['PremiosPagadosNoTrad'],
                'comision_loterias_lot_no_tradicional' => $v['ComisionLoteriasLotNoTrad'],
                'comision_gobierno' => $v['ComisionGobierno'],
            ];
        }

        if (!empty($data)) {
            foreach (array_chunk($data, 5000) as $chunk) {
                DB::table('ventas_flash_bet')->insert($chunk);
            }
        }

        return response()->json([
            'message' => 'Datos guardados correctamente. Total insertados: ' . count($data),
            'total' => count($data),
        ]);
    }

    public function deleteVentasLotobet(Request $request)
    {
        header('Content-Type: application/json');

        $fecha = $request->query('fecha');

        DB::table('ventas_flash_bet')->whereDate('fecha', $fecha)->delete();

        return response()->json([
            'message' => 'Datos eliminados correctamente',
        ]);
    }
}
