<?php

namespace App\Http\Controllers;

use App\Exports\FaltantesExport;
use App\Exports\VentasUsuarioExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    function ventasUsuarioBet(Request $request)
    {
        return view('reportes.ventas-usuario-bet');
    }

    public function listVentasUsuarioBet(Request $request)
    {
        header('Content-Type: application/json');

        $mes = $request->input('mes');
        $page = $request->input('page', 1);

        $query = DB::table('vt_usuarios_bet')
            ->select('consorcio_id', 'agencia_id', 'cedula', 'tipo')
            ->whereNotIn('cedula', function ($sub) {
                $sub->select('cedula')->from('empleados')->whereNotNull('cedula');
            });

        if ($mes) {
            [$year, $month] = explode('-', $mes);
            $query->whereYear('fecha', $year)->whereMonth('fecha', $month);
        }

        $registros = $query
            ->groupBy('consorcio_id', 'agencia_id', 'cedula', 'tipo')
            ->orderBy('cedula', 'desc')
            ->paginate(50, ['*'], 'page', $page);

        return $registros->toJson();
    }

    public function excelVentasUsuarioBet(Request $request)
    {
        ini_set('memory_limit', '2G'); // Aumentar el límite de memoria
        ini_set('max_execution_time', 300); // Aumentar el tiempo máximo de entrada a 5 min

        $tipo = $request->input('tipo');
        $fecha = $request->input('fecha');
        $mes = $request->input('mes');

        $fileName = 'ventas_usuarioio_bet_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new VentasUsuarioExport($tipo, $fecha, $mes), $fileName);
    }

    function pdfVentasUsuarioBet(Request $request)
    {
        ini_set('memory_limit', '1G'); // Aumentar el límite de memoria a 512MB

        $mes = $request->input('mes');

        $query = DB::table('vt_usuarios_bet')
            ->select('consorcio_id', 'agencia_id', 'cedula', 'tipo')
            ->whereNotIn('cedula', function ($sub) {
                $sub->select('cedula')->from('empleados')->whereNotNull('cedula');
            });

        if ($mes) {
            [$year, $month] = explode('-', $mes);
            $query->whereYear('fecha', $year)->whereMonth('fecha', $month);
        }

        $registros = $query
            ->groupBy('consorcio_id', 'agencia_id', 'cedula', 'tipo')
            ->orderBy('cedula', 'desc')
            ->get();        

        // 🔹 Generar PDF usando una vista
        $pdf = Pdf::loadView('reportes.ventas-usuario-bet-pdf', compact('registros'))
            ->setPaper('A4', 'portrait');

        // 🔹 Descargar el archivo
        return $pdf->download('reporte_ventas_usuario.pdf');
    }

    // ========== INFORME FALTANTES BET ==========
    function faltantesBet(Request $request)
    {
        return view('reportes.faltantes-bet');
    }

    public function listFaltantesBet(Request $request)
    {
        header('Content-Type: application/json');

        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        $query = DB::table('faltantes_bet')
            ->leftJoin('empleados', 'faltantes_bet.identificacion', '=', 'empleados.cedula')
            ->select(
                'faltantes_bet.agencia_id',
                'faltantes_bet.identificacion',
                DB::raw("CONCAT(COALESCE(empleados.nombres, ''), ' ', COALESCE(empleados.apellidos, '')) as nombre_empleado"),
                DB::raw('COUNT(faltantes_bet.faltante_id) as cantidad_faltantes'),
                DB::raw('SUM(faltantes_bet.monto) as total_monto')
            )
            ->whereNotNull('faltantes_bet.identificacion')
            ->where('faltantes_bet.identificacion', '!=', '');

        if ($fechaInicio && $fechaFin) {
            $query->whereBetween('faltantes_bet.fecha', [$fechaInicio, $fechaFin]);
        }

        $registros = $query
            ->groupBy('faltantes_bet.agencia_id', 'faltantes_bet.identificacion', 'empleados.nombres', 'empleados.apellidos')
            ->orderBy('total_monto', 'desc')
            ->paginate(50);

        return $registros->toJson();
    }

    public function excelFaltantesBet(Request $request)
    {
        ini_set('memory_limit', '2G');
        ini_set('max_execution_time', 300);

        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        $query = DB::table('faltantes_bet')
            ->leftJoin('empleados', 'faltantes_bet.identificacion', '=', 'empleados.cedula')
            ->select(
                'faltantes_bet.identificacion',
                DB::raw("CONCAT(COALESCE(empleados.nombres, ''), ' ', COALESCE(empleados.apellidos, '')) as nombre_empleado"),
                DB::raw('COUNT(faltantes_bet.faltante_id) as cantidad_faltantes'),
                DB::raw('SUM(faltantes_bet.monto) as total_monto')
            )
            ->whereNotNull('faltantes_bet.identificacion')
            ->where('faltantes_bet.identificacion', '!=', '');

        if ($fechaInicio && $fechaFin) {
            $query->whereBetween('faltantes_bet.fecha', [$fechaInicio, $fechaFin]);
        }

        $registros = $query
            ->groupBy('faltantes_bet.identificacion', 'empleados.nombres', 'empleados.apellidos')
            ->orderBy('total_monto', 'desc')
            ->get();

        $fileName = 'faltantes_bet_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new \App\Exports\FaltantesBetExport($registros), $fileName);
    }

    public function pdfFaltantesBet(Request $request)
    {
        ini_set('memory_limit', '1G');

        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        $query = DB::table('faltantes_bet')
            ->leftJoin('empleados', 'faltantes_bet.identificacion', '=', 'empleados.cedula')
            ->select(
                'faltantes_bet.identificacion',
                DB::raw("CONCAT(COALESCE(empleados.nombres, ''), ' ', COALESCE(empleados.apellidos, '')) as nombre_empleado"),
                DB::raw('COUNT(faltantes_bet.faltante_id) as cantidad_faltantes'),
                DB::raw('SUM(faltantes_bet.monto) as total_monto')
            )
            ->whereNotNull('faltantes_bet.identificacion')
            ->where('faltantes_bet.identificacion', '!=', '');

        if ($fechaInicio && $fechaFin) {
            $query->whereBetween('faltantes_bet.fecha', [$fechaInicio, $fechaFin]);
        }

        $registros = $query
            ->groupBy('faltantes_bet.identificacion', 'empleados.nombres', 'empleados.apellidos')
            ->orderBy('total_monto', 'desc')
            ->get();

        $pdf = Pdf::loadView('reportes.faltantes-bet-pdf', compact('registros'))
            ->setPaper('A4', 'portrait');

        return $pdf->download('reporte_faltantes_bet.pdf');
    }
}
