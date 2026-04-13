<?php

namespace App\Http\Controllers;

use App\Models\ContabilidadElectricidad;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ContabilidadElectricidadController extends Controller
{
    public function index()
    {
        return view('contabilidad.electricidad');
    }

    public function data(Request $request)
    {
        $validated = $request->validate([
            'mes' => ['nullable', 'date_format:Y-m'],
            'empresa' => ['nullable', 'string', 'max:80'],
            'pagado' => ['nullable', 'in:todos,si,no'],
        ]);

        $query = ContabilidadElectricidad::query();

        if (!empty($validated['mes'])) {
            $inicioMes = Carbon::createFromFormat('Y-m', $validated['mes'])->startOfMonth();
            $finMes = $inicioMes->copy()->endOfMonth();
            $query->whereBetween('fecha_factura', [$inicioMes->toDateString(), $finMes->toDateString()]);
        }

        if (!empty($validated['empresa'])) {
            $query->where('empresa', trim((string) $validated['empresa']));
        }

        if (($validated['pagado'] ?? 'todos') === 'si') {
            $query->where('pagado', true);
        }

        if (($validated['pagado'] ?? 'todos') === 'no') {
            $query->where('pagado', false);
        }

        $items = $query
            ->orderByDesc('fecha_factura')
            ->orderByDesc('id')
            ->get();

        $rows = $items->map(function (ContabilidadElectricidad $item) {
            return [
                'id' => $item->id,
                'fecha_factura' => optional($item->fecha_factura)->format('Y-m-d'),
                'empresa' => (string) $item->empresa,
                'sucursal' => (string) $item->sucursal,
                'contrato' => (string) ($item->contrato ?? ''),
                'medidor' => (string) ($item->medidor ?? ''),
                'lectura_anterior' => (float) $item->lectura_anterior,
                'lectura_actual' => (float) $item->lectura_actual,
                'ajuste_kwh' => (float) $item->ajuste_kwh,
                'tarifa_kwh' => (float) $item->tarifa_kwh,
                'consumo_kwh' => (float) $item->consumo_kwh,
                'subtotal_energia' => (float) $item->subtotal_energia,
                'otros_cargos' => (float) $item->otros_cargos,
                'impuestos' => (float) $item->impuestos,
                'total_factura' => (float) $item->total_factura,
                'pagado' => (bool) $item->pagado,
                'fecha_pago' => optional($item->fecha_pago)->format('Y-m-d'),
                'referencia_pago' => (string) ($item->referencia_pago ?? ''),
                'observacion' => (string) ($item->observacion ?? ''),
            ];
        })->values();

        return response()->json([
            'data' => $rows,
            'resumen' => [
                'registros' => $rows->count(),
                'kwh_total' => round((float) $rows->sum('consumo_kwh'), 3),
                'monto_total' => round((float) $rows->sum('total_factura'), 2),
                'monto_pendiente' => round((float) $rows->where('pagado', false)->sum('total_factura'), 2),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validarPayload($request);

        $registro = ContabilidadElectricidad::create($validated);

        return response()->json([
            'message' => 'Registro de electricidad creado correctamente.',
            'id' => $registro->id,
        ]);
    }

    public function update(Request $request, ContabilidadElectricidad $electricidad)
    {
        $validated = $this->validarPayload($request);

        $electricidad->update($validated);

        return response()->json([
            'message' => 'Registro de electricidad actualizado correctamente.',
        ]);
    }

    public function destroy(ContabilidadElectricidad $electricidad)
    {
        $electricidad->delete();

        return response()->json([
            'message' => 'Registro eliminado correctamente.',
        ]);
    }

    private function validarPayload(Request $request): array
    {
        $validated = $request->validate([
            'fecha_factura' => ['required', 'date_format:Y-m-d'],
            'empresa' => ['required', 'string', 'max:80'],
            'sucursal' => ['required', 'string', 'max:120'],
            'contrato' => ['nullable', 'string', 'max:50'],
            'medidor' => ['nullable', 'string', 'max:50'],
            'lectura_anterior' => ['required', 'numeric', 'min:0'],
            'lectura_actual' => ['required', 'numeric', 'min:0'],
            'ajuste_kwh' => ['nullable', 'numeric', 'min:-999999.999', 'max:999999.999'],
            'tarifa_kwh' => ['required', 'numeric', 'min:0'],
            'otros_cargos' => ['nullable', 'numeric', 'min:0'],
            'impuestos' => ['nullable', 'numeric', 'min:0'],
            'pagado' => ['nullable', 'boolean'],
            'fecha_pago' => ['nullable', 'date_format:Y-m-d'],
            'referencia_pago' => ['nullable', 'string', 'max:120'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $lecturaAnterior = (float) $validated['lectura_anterior'];
        $lecturaActual = (float) $validated['lectura_actual'];
        $ajuste = (float) ($validated['ajuste_kwh'] ?? 0);

        if (($lecturaActual - $lecturaAnterior + $ajuste) < 0) {
            throw ValidationException::withMessages([
                'lectura_actual' => 'El consumo calculado no puede ser negativo. Verifica lecturas y ajuste.',
            ]);
        }

        $validated['ajuste_kwh'] = (float) ($validated['ajuste_kwh'] ?? 0);
        $validated['otros_cargos'] = (float) ($validated['otros_cargos'] ?? 0);
        $validated['impuestos'] = (float) ($validated['impuestos'] ?? 0);
        $validated['pagado'] = (bool) ($validated['pagado'] ?? false);

        if (!$validated['pagado']) {
            $validated['fecha_pago'] = null;
            $validated['referencia_pago'] = null;
        }

        return $validated;
    }
}
