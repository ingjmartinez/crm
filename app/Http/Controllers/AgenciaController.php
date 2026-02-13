<?php

namespace App\Http\Controllers;

use App\Models\Agencia;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AgenciasExport;
use App\Imports\AgenciasImport;

class AgenciaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('agencias.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('agencias.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'agencia' => 'required|string|max:25',
            'terminal' => 'nullable|string|max:25',
            'nombre_agencia' => 'nullable|string|max:55',
            'horario_am' => ['nullable', 'string', 'max:35', 'regex:/^([1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM)\s*\/\s*([1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM)$/i'],
            'horario_pm' => ['nullable', 'string', 'max:35', 'regex:/^([1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM)\s*\/\s*([1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM)$/i'],
            'ciudad' => 'nullable|string|max:55',
            'ruta' => 'nullable|string|max:55',
            'operador' => 'nullable|string|max:55',
            'coordinador' => 'nullable|string|max:55',
            'aplica_incentivo' => 'required|boolean',
        ]);

        Agencia::create($validated);

        return redirect()->route('agencias.index')
            ->with('success', 'Agencia creada exitosamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Agencia $agencia)
    {
        return view('agencias.show', compact('agencia'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Agencia $agencia)
    {
        return view('agencias.edit', compact('agencia'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Agencia $agencia)
    {
        $validated = $request->validate([
            'agencia' => 'required|string|max:255',
            'nombre_agencia' => 'nullable|string|max:255',
            'terminal' => 'nullable|string|max:255',
            'horario_am' => ['nullable', 'string', 'max:35', 'regex:/^([1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM)\s*\/\s*([1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM)$/i'],
            'horario_pm' => ['nullable', 'string', 'max:35', 'regex:/^([1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM)\s*\/\s*([1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM)$/i'],
            'sistema' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:255',
            'ruta' => 'nullable|string|max:255',
            'operador' => 'nullable|string|max:255',
            'coordinador' => 'nullable|string|max:255',
            'aplica_incentivo' => 'required|boolean',
        ]);

        $agencia->update($validated);

        return redirect()->route('agencias.index')
            ->with('success', 'Agencia actualizada exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Agencia $agencia)
    {
        $agencia->delete();

        return redirect()->route('agencias.index')
            ->with('success', 'Agencia eliminada exitosamente.');
    }

    /**
     * Get list of agencias for DataTable.
     */
    public function list(Request $request)
    {
        $query = Agencia::query();

        // Si hay búsqueda
        if ($request->has('search') && $request->search['value']) {
            $search = $request->search['value'];
            $query->where(function($q) use ($search) {
                $q->where('agencia', 'like', "%{$search}%")
                  ->orWhere('nombre_agencia', 'like', "%{$search}%")
                  ->orWhere('terminal', 'like', "%{$search}%")
                                    ->orWhere('horario_am', 'like', "%{$search}%")
                                    ->orWhere('horario_pm', 'like', "%{$search}%")
                  ->orWhere('sistema', 'like', "%{$search}%")
                  ->orWhere('ciudad', 'like', "%{$search}%")
                  ->orWhere('ruta', 'like', "%{$search}%")
                  ->orWhere('operador', 'like', "%{$search}%")
                                    ->orWhere('coordinador', 'like', "%{$search}%")
                                    ->orWhere('aplica_incentivo', 'like', "%{$search}%");
            });
        }

        // Total de registros
        $totalRecords = Agencia::count();
        $filteredRecords = $query->count();

        // Paginación
        $start = $request->input('start', 0);
        $length = $request->input('length', 10);

        $agencias = $query->orderBy('created_at', 'desc')
                          ->skip($start)
                          ->take($length)
                          ->get();

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $agencias
        ]);
    }

    /**
     * Vista: Agencias con incumplimiento de horario.
     */
    public function incumplimientosHorario()
    {
        return view('agencias.incumplimientos');
    }

    /**
     * API: Listado de incumplimiento (entrada/salida) por agencia.
     */
    public function listIncumplimientosHorario(Request $request)
    {
        $fecha = $request->input('fecha', now()->toDateString());
        $soloIncumplidas = $request->input('solo_incumplidas', '1') === '1';

        $agencias = Agencia::query()
            ->select('id', 'agencia', 'nombre_agencia', 'terminal', 'horario_am', 'horario_pm')
            ->whereNotNull('terminal')
            ->where(function ($q) {
                $q->whereNotNull('horario_am')
                  ->orWhereNotNull('horario_pm');
            })
            ->get();

        $mapAsistencia = $this->consolidarAsistenciasPorTerminal($fecha);

        $rows = [];

        foreach ($agencias as $agencia) {
            $terminalKey = $this->normalizarTerminal($agencia->terminal);
            $asistencia = $mapAsistencia[$terminalKey] ?? null;

            $entradaProgramada = $this->extraerHoraInicio($agencia->horario_am);
            $salidaProgramada = $this->extraerHoraFin($agencia->horario_pm);

            $entradaProgramadaDateTime = $this->parseFechaHora($fecha, $entradaProgramada);
            $salidaProgramadaDateTime = $this->parseFechaHora($fecha, $salidaProgramada);

            $entradaReal = isset($asistencia['entrada']) && $asistencia['entrada'] ? Carbon::parse($asistencia['entrada']) : null;
            $salidaReal = isset($asistencia['salida']) && $asistencia['salida'] ? Carbon::parse($asistencia['salida']) : null;

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
                'entrada_programada' => $entradaProgramada,
                'salida_programada' => $salidaProgramada,
                'entrada_real' => $entradaReal ? $entradaReal->format('h:i A') : '-',
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
    }

    private function consolidarAsistenciasPorTerminal(string $fecha): array
    {
        $bet = DB::table('asistencias_bet')
            ->selectRaw("COALESCE(NULLIF(TRIM(LEADING '0' FROM agencia_id), ''), '0') as terminal_key")
            ->selectRaw('MIN(primer_login) as entrada')
            ->selectRaw('MAX(ultimo_login) as salida')
            ->whereDate('fecha', $fecha)
            ->groupBy('terminal_key')
            ->get();

        $net = DB::table('asistencias_net')
            ->selectRaw("COALESCE(NULLIF(TRIM(LEADING '0' FROM agencia), ''), NULLIF(TRIM(LEADING '0' FROM terminal), ''), '0') as terminal_key")
            ->selectRaw('MIN(entrada) as entrada')
            ->selectRaw('MAX(salida) as salida')
            ->where(function ($q) use ($fecha) {
                $q->whereDate('entrada', $fecha)
                  ->orWhereDate('salida', $fecha);
            })
            ->groupBy('terminal_key')
            ->get();

        $map = [];

        foreach ($bet as $row) {
            $map[$row->terminal_key] = [
                'entrada' => $row->entrada,
                'salida' => $row->salida,
                'fuente' => 'BET',
            ];
        }

        foreach ($net as $row) {
            if (!isset($map[$row->terminal_key])) {
                $map[$row->terminal_key] = [
                    'entrada' => $row->entrada,
                    'salida' => $row->salida,
                    'fuente' => 'NET',
                ];
                continue;
            }

            if ($row->entrada && (!$map[$row->terminal_key]['entrada'] || Carbon::parse($row->entrada)->lessThan(Carbon::parse($map[$row->terminal_key]['entrada'])))) {
                $map[$row->terminal_key]['entrada'] = $row->entrada;
            }

            if ($row->salida && (!$map[$row->terminal_key]['salida'] || Carbon::parse($row->salida)->greaterThan(Carbon::parse($map[$row->terminal_key]['salida'])))) {
                $map[$row->terminal_key]['salida'] = $row->salida;
            }

            $map[$row->terminal_key]['fuente'] = 'BET/NET';
        }

        return $map;
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
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Export agencias to Excel
     */
    public function export()
    {
        return Excel::download(new AgenciasExport, 'agencias_' . date('Y-m-d_His') . '.xlsx');
    }

    /**
     * Import agencias from Excel
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048',
        ]);

        try {
            Excel::import(new AgenciasImport, $request->file('file'));

            return redirect()->route('agencias.index')
                ->with('success', 'Agencias importadas exitosamente.');
        } catch (\Exception $e) {
            return redirect()->route('agencias.index')
                ->with('error', 'Error al importar: ' . $e->getMessage());
        }
    }

    /**
     * Download import template
     */
    public function template()
    {
        $headers = [
            'Agencia',
            'Terminal',
            'Horario AM',
            'Horario PM',
            'Nombre Agencia',
            'Sistema',
            'Ciudad',
            'Ruta',
            'Operador',
            'Coordinador',
            'Aplica Incentivo',
        ];

        $data = [
            $headers,
            ['20907', '5546', '7:00 AM / 2:00 PM', '2:00 PM / 9:00 PM', 'Agencia Ejemplo', 'Lotobet', 'San Pedro', 'Ruta 0501', 'Jose Ruby', 'Aramis', 'SI'],
        ];

        $filename = 'plantilla_agencias.xlsx';

        return Excel::download(new class($data) implements 
            \Maatwebsite\Excel\Concerns\FromArray,
            \Maatwebsite\Excel\Concerns\WithStyles,
            \Maatwebsite\Excel\Concerns\ShouldAutoSize
        {
            protected $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function array(): array
            {
                return $this->data;
            }

            public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
            {
                return [
                    1 => ['font' => ['bold' => true]],
                ];
            }
        }, $filename);
    }
}
