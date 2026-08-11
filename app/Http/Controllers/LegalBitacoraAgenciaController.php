<?php

namespace App\Http\Controllers;

use App\Http\Requests\Legal\GuardarContratoLegalRequest;
use App\Http\Requests\Legal\GuardarObligacionLegalRequest;
use App\Http\Requests\Legal\ListarBitacoraAgenciasRequest;
use App\Models\Agencia;
use App\Models\LegalContrato;
use App\Models\LegalObligacion;
use App\Models\LegalPagoProgramado;
use App\Services\Legal\GeneradorPagosProgramados;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Throwable;

class LegalBitacoraAgenciaController extends Controller
{
    public function __construct(private readonly GeneradorPagosProgramados $generadorPagos) {}

    public function index(ListarBitacoraAgenciasRequest $request): View
    {
        $buscar = trim((string) $request->validated('buscar', ''));
        $agencias = Agencia::query()
            ->withCount('legalContratos')
            ->when($buscar !== '', function ($query) use ($buscar): void {
                $query->where(function ($query) use ($buscar): void {
                    $query->where('terminal', 'like', "%{$buscar}%")
                        ->orWhere('agencia', 'like', "%{$buscar}%")
                        ->orWhere('nombre_agencia', 'like', "%{$buscar}%")
                        ->orWhere('empresa', 'like', "%{$buscar}%")
                        ->orWhere('ciudad', 'like', "%{$buscar}%")
                        ->orWhere('ruta', 'like', "%{$buscar}%");
                });
            })
            ->orderByRaw("CASE WHEN terminal IS NULL OR terminal = '' THEN 1 ELSE 0 END")
            ->orderBy('terminal')
            ->paginate(30)
            ->withQueryString();

        return view('legal.bitacora-agencias.index', [
            'agencias' => $agencias,
            'buscar' => $buscar,
            'resumen' => [
                'agencias' => Agencia::query()->count(),
                'contratos_activos' => LegalContrato::query()->where('estado', 'activo')->count(),
                'pagos_pendientes' => LegalPagoProgramado::query()->where('estado', 'pendiente')->count(),
                'pagos_vencidos' => LegalPagoProgramado::query()
                    ->where('estado', 'pendiente')
                    ->whereDate('fecha_vencimiento', '<', today())
                    ->count(),
            ],
        ]);
    }

    public function show(Agencia $agencia): View
    {
        $agencia->load([
            'legalContratos' => fn ($query) => $query->latest('fecha_inicio'),
            'legalContratos.obligaciones' => fn ($query) => $query->orderBy('tipo'),
            'legalContratos.obligaciones.pagosProgramados' => fn ($query) => $query->orderBy('fecha_vencimiento'),
        ]);

        $pagos = $agencia->legalContratos
            ->flatMap->obligaciones
            ->flatMap->pagosProgramados
            ->sortBy('fecha_vencimiento')
            ->values();

        return view('legal.bitacora-agencias.show', [
            'agencia' => $agencia,
            'pagos' => $pagos,
            'tiposObligacion' => LegalObligacion::TIPOS,
            'frecuencias' => LegalObligacion::FRECUENCIAS,
        ]);
    }

    public function storeContrato(GuardarContratoLegalRequest $request, Agencia $agencia): RedirectResponse
    {
        $validated = $request->validated();
        /** @var UploadedFile $documento */
        $documento = $validated['documento_pdf'];
        $documentoPath = $documento->store("legal/contratos/{$agencia->id}", 'local');

        try {
            DB::transaction(function () use ($agencia, $documento, $documentoPath, $request, $validated): void {
                $contrato = $agencia->legalContratos()->create([
                    'titulo' => trim($validated['titulo']),
                    'numero_contrato' => filled($validated['numero_contrato'] ?? null) ? trim($validated['numero_contrato']) : null,
                    'contraparte' => filled($validated['contraparte'] ?? null) ? trim($validated['contraparte']) : null,
                    'fecha_inicio' => $validated['fecha_inicio'],
                    'fecha_fin' => $validated['fecha_fin'] ?? null,
                    'estado' => $validated['estado'],
                    'renovacion_automatica' => $request->boolean('renovacion_automatica'),
                    'documento_path' => $documentoPath,
                    'documento_nombre_original' => $documento->getClientOriginalName(),
                    'observaciones' => $validated['observaciones'] ?? null,
                    'creado_por' => $request->user()?->id,
                ]);
                $obligacion = $contrato->obligaciones()->create([
                    'tipo' => $validated['obligacion_tipo'],
                    'descripcion' => $validated['obligacion_descripcion'] ?? null,
                    'monto' => $validated['monto'],
                    'frecuencia' => $validated['frecuencia'],
                    'fecha_primer_pago' => $validated['fecha_primer_pago'],
                    'fecha_fin' => $validated['fecha_fin_pagos'] ?? null,
                    'activa' => true,
                    'creado_por' => $request->user()?->id,
                ]);

                $this->generadorPagos->generar($obligacion);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($documentoPath);

            throw $exception;
        }

        return redirect()->route('legal.bitacora-agencias.show', $agencia)
            ->with('success', 'Contrato, documento y calendario de pagos registrados correctamente.');
    }

    public function storeObligacion(
        GuardarObligacionLegalRequest $request,
        LegalContrato $contrato,
    ): RedirectResponse {
        $validated = $request->validated();
        $obligacion = DB::transaction(function () use ($contrato, $request, $validated): LegalObligacion {
            $obligacion = $contrato->obligaciones()->create([
                ...$validated,
                'activa' => true,
                'creado_por' => $request->user()?->id,
            ]);

            $this->generadorPagos->generar($obligacion);

            return $obligacion;
        });

        return redirect()->route('legal.bitacora-agencias.show', $contrato->agencia_id)
            ->with('success', 'Obligación agregada con '.$obligacion->pagosProgramados()->count().' pago(s) programado(s).');
    }

    public function documento(LegalContrato $contrato): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists($contrato->documento_path), 404, 'El documento no está disponible.');

        return response()->file(
            Storage::disk('local')->path($contrato->documento_path),
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    HeaderUtils::DISPOSITION_INLINE,
                    basename($contrato->documento_nombre_original),
                    'contrato.pdf',
                ),
            ],
        );
    }
}
