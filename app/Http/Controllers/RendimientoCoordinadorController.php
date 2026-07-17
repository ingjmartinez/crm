<?php

namespace App\Http\Controllers;

use App\Models\RendimientoCoordinador;
use App\Services\Incentivos\RendimientoCoordinadorReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RendimientoCoordinadorController extends Controller
{
    public function index(Request $request, RendimientoCoordinador $reporte)
    {
        $fechaInicioDefault = now()->startOfMonth()->toDateString();
        $fechaFinDefault = now()->toDateString();
        $filtrosAplicados = $request->boolean('aplicar');

        $validated = $request->validate([
            'aplicar' => ['nullable', 'boolean'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'sistema' => ['nullable', 'in:Todos,Lotobet,Lotonet'],
        ]);

        $filtros = [
            'fecha_inicio' => Carbon::parse($validated['fecha_inicio'] ?? $fechaInicioDefault)->toDateString(),
            'fecha_fin' => Carbon::parse($validated['fecha_fin'] ?? $fechaFinDefault)->toDateString(),
            'sistema' => $validated['sistema'] ?? 'Todos',
        ];

        $resultado = $filtrosAplicados
            ? $reporte->generar($filtros)
            : [
                'coordinadores' => collect(),
                'agencias_sin_coordinador' => collect(),
                'resumen' => [],
            ];

        return view('incentivos.rendimiento-coordinador', [
            'coordinadores' => $resultado['coordinadores'],
            'agenciasSinCoordinador' => $resultado['agencias_sin_coordinador'],
            'resumen' => $resultado['resumen'],
            'filtros' => $filtros,
            'filtrosAplicados' => $filtrosAplicados,
        ]);
    }

    public function detalle(
        Request $request,
        int $coordinador,
        RendimientoCoordinadorReportService $reporte
    ) {
        return response()->json($reporte->generar($coordinador, $this->validarFiltrosDetalle($request)))
            ->header('Cache-Control', 'private, max-age=300');
    }

    public function pdf(
        Request $request,
        int $coordinador,
        RendimientoCoordinadorReportService $reporte
    ) {
        $data = $reporte->generar($coordinador, $this->validarFiltrosDetalle($request));
        $nombre = preg_replace('/[^A-Za-z0-9_-]+/', '_', $data['meta']['coordinador']);

        return Pdf::loadView('incentivos.rendimiento-coordinador-pdf', $data)
            ->setPaper('letter', 'landscape')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ])
            ->download("rendimiento_{$nombre}_{$data['meta']['fecha_inicio']}_{$data['meta']['fecha_fin']}.pdf");
    }

    public function excel(
        Request $request,
        int $coordinador,
        RendimientoCoordinadorReportService $reporte
    ) {
        $data = $reporte->generar($coordinador, $this->validarFiltrosDetalle($request));
        $nombre = preg_replace('/[^A-Za-z0-9_-]+/', '_', $data['meta']['coordinador']);
        $filename = "rendimiento_{$nombre}_{$data['meta']['fecha_inicio']}_{$data['meta']['fecha_fin']}.xls";

        return response()->streamDownload(function () use ($data) {
            echo "\xEF\xBB\xBF";
            echo '<html><head><meta charset="UTF-8"><style>th{background:#002d72;color:#fff}td,th{border:1px solid #bbb;padding:5px}table{border-collapse:collapse;margin-bottom:18px}</style></head><body>';
            echo '<h1>Rendimiento integral de coordinador</h1>';
            echo '<p><strong>Coordinador:</strong> ' . e($data['meta']['coordinador']) . '</p>';
            echo '<p><strong>Periodo:</strong> ' . e($data['meta']['periodo']) . ' | <strong>Sistema:</strong> ' . e($data['meta']['sistema']) . '</p>';

            echo '<h2>Resumen</h2><table><tbody>';
            foreach ($data['resumen'] as $campo => $valor) {
                echo '<tr><th>' . e(str_replace('_', ' ', ucfirst($campo))) . '</th><td>' . e((string) $valor) . '</td></tr>';
            }
            echo '</tbody></table>';

            echo '<h2>Ranking de agencias</h2><table><thead><tr>';
            foreach (['Ranking', 'Terminal', 'Agencia', 'Empresa', 'Sistema', 'Estado', 'Venta total', 'Usuarios', 'Usuarios meta', '% cumplimiento', 'Promedio usuario', 'Mejor usuario', 'Venta mejor usuario'] as $heading) {
                echo '<th>' . e($heading) . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ($data['agencias'] as $row) {
                echo '<tr><td>' . $row['ranking'] . '</td><td style="mso-number-format:\'@\';">' . e($row['terminal']) . '</td><td>' . e($row['agencia']) . '</td><td>' . e($row['empresa']) . '</td><td>' . e($row['sistema']) . '</td><td>' . ($row['activa'] ? 'Activa' : 'Inactiva') . '</td><td>' . $row['venta_total'] . '</td><td>' . $row['usuarios'] . '</td><td>' . $row['usuarios_cumplieron'] . '</td><td>' . $row['cumplimiento_usuarios_pct'] . '</td><td>' . $row['promedio_usuario'] . '</td><td>' . e($row['mejor_usuario']) . '</td><td>' . $row['mejor_usuario_venta_agencia'] . '</td></tr>';
            }
            echo '</tbody></table>';

            echo '<h2>Ranking de usuarios</h2><table><thead><tr>';
            foreach (['Ranking', 'Cedula', 'Nombre', 'Agencia principal', 'Terminal', 'Venta total', 'Avance %', 'Faltante', 'Clasificacion', 'Incentivo'] as $heading) {
                echo '<th>' . e($heading) . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ($data['usuarios'] as $row) {
                echo '<tr><td>' . $row['ranking'] . '</td><td style="mso-number-format:\'@\';">' . e($row['cedula']) . '</td><td>' . e($row['nombre']) . '</td><td>' . e($row['agencia_principal']) . '</td><td style="mso-number-format:\'@\';">' . e($row['terminal_principal']) . '</td><td>' . $row['venta_total'] . '</td><td>' . $row['avance_pct'] . '</td><td>' . $row['faltante'] . '</td><td>' . e($row['clasificacion']) . '</td><td>' . $row['incentivo'] . '</td></tr>';
            }
            echo '</tbody></table>';

            echo '<h2>Tendencia</h2><table><thead><tr><th>Fecha actual</th><th>Venta actual</th><th>Fecha comparable</th><th>Venta anterior</th></tr></thead><tbody>';
            foreach ($data['tendencia'] as $row) {
                echo '<tr><td>' . e($row['fecha']) . '</td><td>' . $row['venta_actual'] . '</td><td>' . e((string) $row['fecha_anterior']) . '</td><td>' . $row['venta_anterior'] . '</td></tr>';
            }
            echo '</tbody></table></body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    private function validarFiltrosDetalle(Request $request): array
    {
        $validated = $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'sistema' => ['nullable', 'in:Todos,Lotobet,Lotonet'],
        ]);

        $inicio = Carbon::parse($validated['fecha_inicio']);
        $fin = Carbon::parse($validated['fecha_fin']);
        if ($inicio->diffInDays($fin) > 366) {
            throw ValidationException::withMessages([
                'fecha_fin' => 'El reporte integral permite un máximo de 367 días por consulta.',
            ]);
        }

        return [
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin' => $fin->toDateString(),
            'sistema' => $validated['sistema'] ?? 'Todos',
        ];
    }
}
