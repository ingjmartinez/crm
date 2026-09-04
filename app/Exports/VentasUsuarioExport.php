<?php

namespace App\Exports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VentasUsuarioExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        protected ?string $tipo = null,
        protected ?string $fecha = null,
        protected ?string $mes = null,
        protected string $empresa = 'todos',
        protected ?string $fechaInicio = null,
        protected ?string $fechaFin = null,
        protected ?int $coordinadorId = null
    ) {}

    public function query(): Builder
    {
        ini_set('memory_limit', '2G');

        $query = DB::table('vt_usuarios_bet as v')
            ->leftJoin('empleados as e', DB::raw("REPLACE(REPLACE(COALESCE(v.cedula, ''), '-', ''), ' ', '')"), '=', DB::raw("REPLACE(REPLACE(COALESCE(e.cedula, ''), '-', ''), ' ', '')"))
            ->selectRaw("
                REPLACE(REPLACE(COALESCE(v.cedula, ''), '-', ''), ' ', '') AS cedula,
                COALESCE(
                    NULLIF(TRIM(CONCAT(COALESCE(MAX(e.nombres), ''), ' ', COALESCE(MAX(e.apellidos), ''))), ''),
                    'Actualizar en la maestra de empleado'
                ) AS nombre,
                ROUND(SUM(CASE WHEN LOWER(TRIM(v.tipo)) = 'tradicional' THEN COALESCE(v.monto, 0) ELSE 0 END), 2) AS tradicional,
                ROUND(SUM(CASE WHEN LOWER(TRIM(v.tipo)) IN ('no tradicional', 'no_tradicional') THEN COALESCE(v.monto, 0) ELSE 0 END), 2) AS no_tradicional,
                ROUND(SUM(CASE WHEN LOWER(TRIM(v.tipo)) IN ('recarga', 'recargas') THEN COALESCE(v.monto, 0) ELSE 0 END), 2) AS recargas,
                ROUND(SUM(COALESCE(v.monto, 0)), 2) AS total
            ");

        if ($this->tipo) {
            $query->where('v.tipo', $this->tipo);
        }

        if ($this->fecha) {
            $query->whereDate('v.fecha', $this->fecha);
        }

        if ($this->fechaInicio && $this->fechaFin) {
            $query->whereDate('v.fecha', '>=', $this->fechaInicio)
                ->whereDate('v.fecha', '<=', $this->fechaFin);
        }

        if ($this->mes) {
            [$year, $month] = explode('-', $this->mes);
            $query->whereYear('v.fecha', $year)->whereMonth('v.fecha', $month);
        }

        if ($this->empresa !== 'todos' || $this->coordinadorId !== null) {
            $query->leftJoin('agencias as a', DB::raw('TRIM(CAST(v.agencia_id AS CHAR))'), '=', DB::raw('TRIM(CAST(a.terminal AS CHAR))'));
        }

        if ($this->empresa !== 'todos') {
            if ($this->empresa === 'grupo_joselito') {
                $query->whereRaw('LOWER(COALESCE(a.empresa, "")) LIKE ?', ['%joselito%']);
            }

            if ($this->empresa === 'negosur') {
                $query->whereRaw('LOWER(COALESCE(a.empresa, "")) LIKE ?', ['%negosur%']);
            }
        }

        if ($this->coordinadorId !== null) {
            $query->whereExists(function ($subQuery): void {
                $subQuery->selectRaw('1')
                    ->from('coordinador_operador_agencia as coa_filter')
                    ->join('coordinador_operador as co_filter', 'co_filter.id', '=', 'coa_filter.coordinador_operador_id')
                    ->whereColumn('coa_filter.agencia_id', 'a.id')
                    ->where('co_filter.puesto', 'coordinador')
                    ->where('co_filter.id', $this->coordinadorId);
            });
        }

        return $query
            ->whereRaw("NULLIF(REPLACE(REPLACE(COALESCE(v.cedula, ''), '-', ''), ' ', ''), '') IS NOT NULL")
            ->groupByRaw("REPLACE(REPLACE(COALESCE(v.cedula, ''), '-', ''), ' ', '')")
            ->orderByRaw("REPLACE(REPLACE(COALESCE(v.cedula, ''), '-', ''), ' ', '') DESC");
    }

    public function headings(): array
    {
        return [
            'Cedula',
            'Nombre',
            'Tradicional',
            'No tradicional',
            'Recargas',
            'Total',
        ];
    }

    public function map(mixed $row): array
    {
        return [
            $row->cedula,
            $row->nombre,
            $row->tradicional,
            $row->no_tradicional,
            $row->recargas,
            $row->total,
        ];
    }
}
