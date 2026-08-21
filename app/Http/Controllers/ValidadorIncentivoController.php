<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarValidadorIncentivoRequest;
use App\Models\IncentivoPeriodo;
use App\Models\IncentivoPeriodoDetalle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ValidadorIncentivoController extends Controller
{
    public function index(ConsultarValidadorIncentivoRequest $request): View
    {
        $filtros = $request->validated();
        $periodos = $this->periods();
        $periodo = $this->selectedPeriod($periodos, $filtros['periodo_id'] ?? null);
        $consultado = $request->boolean('consultar');

        if ($periodo === null || ! $consultado) {
            return view('recursos_humanos.validador_incentivos', [
                'periodos' => $periodos,
                'periodo' => $periodo,
                'detalles' => null,
                'empresas' => collect(),
                'resumen' => $this->emptySummary(),
                'filtros' => $filtros,
                'consultado' => $consultado,
            ]);
        }

        $query = $this->filteredDetails($periodo, $filtros);
        $resumen = (clone $query)
            ->selectRaw('COUNT(*) AS registros')
            ->selectRaw("SUM(CASE WHEN estado <> 'no_califica' THEN 1 ELSE 0 END) AS califican")
            ->selectRaw("SUM(CASE WHEN estado = 'pagado' THEN 1 ELSE 0 END) AS pagados")
            ->selectRaw("SUM(CASE WHEN estado = 'pagado_parcial' THEN 1 ELSE 0 END) AS pagados_parciales")
            ->selectRaw("SUM(CASE WHEN estado = 'no_pagado' THEN 1 ELSE 0 END) AS no_pagados")
            ->selectRaw("SUM(CASE WHEN estado = 'no_califica' THEN 1 ELSE 0 END) AS no_califican")
            ->selectRaw("SUM(CASE WHEN TRIM(COALESCE(empleadoid, '')) = '' AND monto_pagado > 0 THEN 1 ELSE 0 END) AS sin_idempleado")
            ->selectRaw('COALESCE(SUM(incentivo_generado), 0) AS incentivo_generado')
            ->selectRaw('COALESCE(SUM(monto_pagado), 0) AS monto_pagado')
            ->selectRaw('COALESCE(SUM(monto_no_pagado), 0) AS monto_no_pagado')
            ->first()
            ?->toArray() ?? $this->emptySummary();
        $detalles = $query
            ->orderByRaw("CASE WHEN TRIM(COALESCE(empleadoid, '')) = '' AND monto_pagado > 0 THEN 0 ELSE 1 END")
            ->orderByRaw("CASE estado WHEN 'no_pagado' THEN 1 WHEN 'pagado_parcial' THEN 2 WHEN 'no_califica' THEN 3 ELSE 4 END")
            ->orderBy('nombre')
            ->paginate((int) ($filtros['por_pagina'] ?? 25))
            ->withQueryString();
        $empresas = $periodo->detalles()
            ->select('empresa')
            ->distinct()
            ->orderBy('empresa')
            ->pluck('empresa');

        return view('recursos_humanos.validador_incentivos', compact(
            'periodos',
            'periodo',
            'detalles',
            'empresas',
            'resumen',
            'filtros',
            'consultado'
        ));
    }

    public function export(ConsultarValidadorIncentivoRequest $request): StreamedResponse
    {
        $filtros = $request->validated();
        $periodo = $this->selectedPeriod($this->periods(), $filtros['periodo_id'] ?? null);

        abort_if($periodo === null, 404, 'No hay un periodo de incentivos guardado.');

        $query = $this->filteredDetails($periodo, $filtros)->orderBy('nombre');
        $filename = sprintf('validador-incentivos-%d-%02d.csv', $periodo->anio, $periodo->mes);

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Cedula', 'Id empleado', 'Nombre', 'Empresa', 'Terminal', 'Agencia',
                'Ventas mes actual', 'Dias', 'Horas', 'Incentivo generado',
                'Monto pagado', 'Monto no pagado', 'Estado', 'Motivos',
            ]);

            foreach ($query->cursor() as $detalle) {
                fputcsv($output, [
                    "\t{$detalle->cedula}",
                    $this->safeCsvValue($detalle->empleadoid),
                    $this->safeCsvValue($detalle->nombre),
                    $this->safeCsvValue($detalle->empresa),
                    $this->safeCsvValue($detalle->ultima_terminal),
                    $this->safeCsvValue($detalle->ultima_agencia_nombre),
                    $detalle->ventas_mes_actual,
                    $detalle->dias_ventas,
                    $detalle->horas_total,
                    $detalle->incentivo_generado,
                    $detalle->monto_pagado,
                    $detalle->monto_no_pagado,
                    $this->stateLabel(
                        $detalle->estado,
                        $this->isMissingEmployeeId($detalle->empleadoid, $detalle->monto_pagado)
                    ),
                    collect($detalle->motivos)->map($this->reasonLabel(...))->implode(', '),
                ]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function periods(): Collection
    {
        return IncentivoPeriodo::query()
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->get();
    }

    /** @param array<string, mixed> $filters */
    private function filteredDetails(IncentivoPeriodo $periodo, array $filters): Builder
    {
        return IncentivoPeriodoDetalle::query()
            ->where('incentivo_periodo_id', $periodo->id)
            ->when($filters['buscar'] ?? null, function (Builder $query, string $search): void {
                $term = '%'.trim($search).'%';
                $identity = preg_replace('/\D+/', '', $search);

                $query->where(function (Builder $query) use ($term, $identity): void {
                    $query->where('nombre', 'like', $term)
                        ->orWhere('empleadoid', 'like', $term)
                        ->orWhere('cedula', 'like', $identity !== '' ? "%{$identity}%" : $term);
                });
            })
            ->when($filters['estado'] ?? null, function (Builder $query, string $state): void {
                if ($state === 'sin_idempleado') {
                    $query->whereRaw("TRIM(COALESCE(empleadoid, '')) = ''")
                        ->where('monto_pagado', '>', 0);

                    return;
                }

                $query->where('estado', $state);
            })
            ->when($filters['motivo'] ?? null, fn (Builder $query, string $reason): Builder => $query->whereJsonContains('motivos', $reason))
            ->when($filters['empresa'] ?? null, fn (Builder $query, string $company): Builder => $query->where('empresa', $company));
    }

    private function selectedPeriod(Collection $periods, mixed $periodId): ?IncentivoPeriodo
    {
        if ($periodId !== null) {
            return $periods->firstWhere('id', (int) $periodId);
        }

        return $periods->first();
    }

    /** @return array<string, int> */
    private function emptySummary(): array
    {
        return [
            'registros' => 0,
            'califican' => 0,
            'pagados' => 0,
            'pagados_parciales' => 0,
            'no_pagados' => 0,
            'no_califican' => 0,
            'sin_idempleado' => 0,
            'incentivo_generado' => 0,
            'monto_pagado' => 0,
            'monto_no_pagado' => 0,
        ];
    }

    private function stateLabel(string $state, bool $missingEmployeeId = false): string
    {
        $paymentState = match ($state) {
            'pagado' => 'Pagado',
            'pagado_parcial' => 'Pagado parcial',
            'no_califica' => 'No calificó',
            default => 'No pagado',
        };

        return $missingEmployeeId ? "Sin IdEmpleado / {$paymentState}" : $paymentState;
    }

    private function isMissingEmployeeId(?string $employeeId, int|float|string $paidAmount): bool
    {
        return trim((string) $employeeId) === '' && (float) $paidAmount > 0;
    }

    private function reasonLabel(string $reason): string
    {
        return match ($reason) {
            'faltante' => 'Faltante',
            'desvinculado' => 'Desvinculado',
            'agencia_excluida' => 'Agencia excluida',
            'meta_no_alcanzada' => 'Meta no alcanzada',
            default => $reason,
        };
    }

    private function safeCsvValue(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'{$value}" : $value;
    }
}
