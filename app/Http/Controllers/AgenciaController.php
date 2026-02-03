<?php

namespace App\Http\Controllers;

use App\Models\Agencia;
use Illuminate\Http\Request;
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
            'Pertenece_A' => 'nullable|string|max:55',
            'ciudad' => 'nullable|string|max:55',
            'ruta' => 'nullable|string|max:55',
            'operador' => 'nullable|string|max:55',
            'coordinador' => 'nullable|string|max:55',
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
            'agencia' => 'required|string|max:255',            'nombre_agencia' => 'nullable|string|max:255',            'terminal' => 'nullable|string|max:255',
            'sistema' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:255',
            'ruta' => 'nullable|string|max:255',
            'operador' => 'nullable|string|max:255',
            'coordinador' => 'nullable|string|max:255',
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
                  ->orWhere('sistema', 'like', "%{$search}%")
                  ->orWhere('ciudad', 'like', "%{$search}%")
                  ->orWhere('ruta', 'like', "%{$search}%")
                  ->orWhere('operador', 'like', "%{$search}%")
                  ->orWhere('coordinador', 'like', "%{$search}%");
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
            'Nombre Agencia',
            'Sistema',
            'Ciudad',
            'Ruta',
            'Operador',
            'Coordinador',
        ];

        $data = [
            $headers,
            ['20907', '5546', 'Agencia Ejemplo', 'Lotobet', 'San Pedro', 'Ruta 0501', 'Jose Ruby', 'Aramis'],
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
