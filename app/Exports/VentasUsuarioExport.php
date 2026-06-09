<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VentasUsuarioExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        protected $tipo = null,
        protected $fecha = null,
        protected $mes = null,
        protected $empresa = 'todos',
        protected $fechaInicio = null,
        protected $fechaFin = null
    ) {}

    public function query()
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

        if ($this->empresa !== 'todos') {
            $query->leftJoin('agencias as a', DB::raw("TRIM(CAST(v.agencia_id AS CHAR))"), '=', DB::raw("TRIM(CAST(a.terminal AS CHAR))"));

            if ($this->empresa === 'grupo_joselito') {
                $query->whereRaw('LOWER(COALESCE(a.empresa, "")) LIKE ?', ['%joselito%']);
            }

            if ($this->empresa === 'negosur') {
                $query->whereRaw('LOWER(COALESCE(a.empresa, "")) LIKE ?', ['%negosur%']);
            }
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

    public function map($row): array
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
