<?php

namespace App\Http\Controllers;

use App\Models\MarVentas;
use SoapFault;
use SoapClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarController extends Controller
{
    public function getVentas(Request $request)
    {
        $wsdl = "http://joselito.ddns.net/mar-svr5/mar-export.asmx?WSDL";

        try {
            // Configurar cliente SOAP
            $client = new SoapClient($wsdl, [
                'trace' => true,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                ]),
            ]);

            $fechaLaravel = $request->fecha; // o $request->fecha
            $fechaFormatoSOAP = date('Y-m-d\TH:i:s', strtotime($fechaLaravel));

            // Parámetros del método ResumenContablePorDia
            $params = [
                'Llave' => 'MAR_25523341-2ED6-4A52-AD71-932166CAAC86',
                'FechaCierre' => $fechaFormatoSOAP,
                'PaginaNo' => 1,
                'FilasPorPagina' => 100000,
            ];

            // Llamada al método SOAP
            $response = $client->__soapCall('ResumenContablePorDia', [$params]);

            // Opcional: inspeccionar toda la estructura
            // dd($response);

            // Acceder al resultado principal
            $result = $response->ResumenContablePorDiaResult ?? null;

            $arrayDatos = [];
            if (isset($result->Datos->any)) {
                $rawXml = $result->Datos->any;

                // 💡 Limpiar caracteres especiales o espacios
                $rawXml = trim($rawXml);

                // 💡 Extraer solo el bloque principal si hay más de uno
                // (normalmente Microsoft devuelve dos secciones pegadas)
                if (substr_count($rawXml, '<?xml') > 1) {
                    // Si vienen múltiples XML juntos, tomar el último
                    $pos = strrpos($rawXml, '<?xml');
                    $rawXml = substr($rawXml, $pos);
                }

                // 💡 Si el XML tiene contenido duplicado antes del dataset
                if (strpos($rawXml, '<diffgr:diffgram') !== false) {
                    $pos = strpos($rawXml, '<diffgr:diffgram');
                    $rawXml = substr($rawXml, $pos);
                }

                // Intentar parsear nuevamente
                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($rawXml, "SimpleXMLElement", LIBXML_NOCDATA);
                if (!$xml) {
                    $errors = libxml_get_errors();
                    libxml_clear_errors();
                    return response()->json(['error' => 'XML inválido', 'detalle' => $errors]);
                }

                $json = json_encode($xml);
                $arrayDatos = json_decode($json, true);
            }

            return response()->json([
                'code' => 0,
                'msg' => $result->Mensaje ?? '',
                'pagina' => $result->PaginaNo ?? 0,
                'total_paginas' => $result->TotalPaginas ?? 0,
                'total_filas' => $result->TotalFilas ?? 0,
                'datos' => $arrayDatos['DocumentElement']['ResumenContablePorDia1'] ?? [],
            ]);
        } catch (SoapFault $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function saveVentas(Request $request)
    {
        ini_set('memory_limit', '1G'); // Aumentar límite de memoria si es necesario
        ini_set('max_execution_time', 300); // Aumentar tiempo máximo de ejecución si es necesario

        $wsdl = "http://joselito.ddns.net/mar-svr5/mar-export.asmx?WSDL";

        try {
            // Configurar cliente SOAP
            $client = new SoapClient($wsdl, [
                'trace' => true,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                ]),
            ]);

            $fechaLaravel = $request->fecha; // o $request->fecha
            $fechaFormatoSOAP = date('Y-m-d\TH:i:s', strtotime($fechaLaravel));

            // Parámetros del método ResumenContablePorDia
            $params = [
                'Llave' => 'MAR_25523341-2ED6-4A52-AD71-932166CAAC86',
                'FechaCierre' => $fechaFormatoSOAP,
                'PaginaNo' => 1,
                'FilasPorPagina' => 100000,
            ];

            // Llamada al método SOAP
            $response = $client->__soapCall('ResumenContablePorDia', [$params]);

            // Opcional: inspeccionar toda la estructura
            // dd($response);

            // Acceder al resultado principal
            $result = $response->ResumenContablePorDiaResult ?? null;

            $arrayDatos = [];
            if (isset($result->Datos->any)) {
                $rawXml = $result->Datos->any;

                // 💡 Limpiar caracteres especiales o espacios
                $rawXml = trim($rawXml);

                // 💡 Extraer solo el bloque principal si hay más de uno
                // (normalmente Microsoft devuelve dos secciones pegadas)
                if (substr_count($rawXml, '<?xml') > 1) {
                    // Si vienen múltiples XML juntos, tomar el último
                    $pos = strrpos($rawXml, '<?xml');
                    $rawXml = substr($rawXml, $pos);
                }

                // 💡 Si el XML tiene contenido duplicado antes del dataset
                if (strpos($rawXml, '<diffgr:diffgram') !== false) {
                    $pos = strpos($rawXml, '<diffgr:diffgram');
                    $rawXml = substr($rawXml, $pos);
                }

                // Intentar parsear nuevamente
                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($rawXml, "SimpleXMLElement", LIBXML_NOCDATA);
                if (!$xml) {
                    $errors = libxml_get_errors();
                    libxml_clear_errors();
                    return response()->json(['error' => 'XML inválido', 'detalle' => $errors]);
                }

                $json = json_encode($xml);
                $arrayDatos = json_decode($json, true);
            }

            $existe = MarVentas::whereDate('EDiFecha', $fechaLaravel)->exists();

            if ($existe) {
                return response()->json(['message' => 'Ya hay data guardada en la fecha: ' . $fechaLaravel]);
            }

            $data = $arrayDatos['DocumentElement']['ResumenContablePorDia1'] ?? [];

            if (!empty($data)) {
                foreach (array_chunk($data, 5000) as $chunk) {
                    DB::table('mar_ventas')->insert($chunk);
                }
            }

            return response()->json([
                'code' => 0,
                'message' => 'Datos guardados correctamente',
                'pagina' => $result->PaginaNo ?? 0,
                'total_paginas' => $result->TotalPaginas ?? 0,
                'total_filas' => $result->TotalFilas ?? 0,
                'total' => count($data),
            ]);
        } catch (SoapFault $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deleteVentas(Request $request)
    {
        header('Content-Type: application/json');

        $fecha = $request->query('fecha');

        MarVentas::whereDate('EDiFecha', $fecha)->delete();

        return response()->json([
            'message' => 'Datos eliminados correctamente',
        ]);
    }
}
