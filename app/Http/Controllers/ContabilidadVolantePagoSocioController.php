<?php

namespace App\Http\Controllers;

use App\Http\Requests\Contabilidad\CargarVolantesPagoSociosRequest;
use App\Http\Requests\Contabilidad\ConsultarVolantesPagoSociosRequest;
use App\Http\Requests\Contabilidad\EnviarVolantePagoSocioRequest;
use App\Mail\VolantePagoSocioMail;
use App\Models\VolantePagoSocioCarga;
use App\Models\VolantePagoSocioDetalle;
use App\Services\Contabilidad\VolantePagoSocioCsvService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class ContabilidadVolantePagoSocioController extends Controller
{
    public function __construct(private readonly VolantePagoSocioCsvService $csvService) {}

    public function index(ConsultarVolantesPagoSociosRequest $request): View
    {
        $datos = $request->validated();
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $fechaDesde = (string) ($datos['fecha_desde'] ?? '');
        $fechaHasta = (string) ($datos['fecha_hasta'] ?? '');
        $consultaRealizada = $nombre !== '' || $fechaDesde !== '' || $fechaHasta !== '';
        $historial = VolantePagoSocioDetalle::query()
            ->with('carga')
            ->when(! $consultaRealizada, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($nombre !== '', fn ($query) => $query->where('nombre', 'like', "%{$nombre}%"))
            ->when(
                $fechaDesde !== '' || $fechaHasta !== '',
                fn ($query) => $query->whereHas('carga', function ($cargaQuery) use ($fechaDesde, $fechaHasta): void {
                    $cargaQuery
                        ->when($fechaDesde !== '', fn ($query) => $query->whereDate('fecha_correspondiente', '>=', $fechaDesde))
                        ->when($fechaHasta !== '', fn ($query) => $query->whereDate('fecha_correspondiente', '<=', $fechaHasta));
                })
            )
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('contabilidad.volantes-pago-socios', [
            'historial' => $historial,
            'nombreBuscado' => $nombre,
            'fechaDesdeBuscada' => $fechaDesde,
            'fechaHastaBuscada' => $fechaHasta,
            'consultaRealizada' => $consultaRealizada,
        ]);
    }

    public function procesar(CargarVolantesPagoSociosRequest $request): RedirectResponse
    {
        /** @var UploadedFile $archivo */
        $archivo = $request->validated('archivo_csv');
        $resultado = $this->csvService->procesar($archivo);
        $ruta = $archivo->getRealPath();

        $carga = DB::transaction(function () use ($archivo, $resultado, $request, $ruta): VolantePagoSocioCarga {
            $carga = VolantePagoSocioCarga::query()->create([
                ...$resultado['carga'],
                'nombre_archivo' => $archivo->getClientOriginalName(),
                'hash_archivo' => $ruta !== false ? hash_file('sha256', $ruta) : null,
                'banco' => $request->validated('banco'),
                'fecha_correspondiente' => $request->validated('fecha_correspondiente'),
                'usuario_id' => $request->user()?->getAuthIdentifier(),
            ]);
            $carga->detalles()->createMany($resultado['detalles']);

            return $carga;
        });

        return redirect()->route('contabilidad.volantes-pago-socios')
            ->with('success', "Se guardaron {$carga->cantidad_transacciones} volantes correctamente.");
    }

    public function vistaPrevia(VolantePagoSocioDetalle $detalle): View
    {
        $detalle->loadMissing('carga');

        return view('contabilidad.volantes-pago-socios-pdf', ['detalle' => $detalle]);
    }

    public function descargar(VolantePagoSocioDetalle $detalle): Response
    {
        return $this->respuestaPdf($detalle);
    }

    public function enviar(
        EnviarVolantePagoSocioRequest $request,
        VolantePagoSocioDetalle $detalle
    ): RedirectResponse {
        $detalle->loadMissing('carga');
        Mail::to($request->validated('correo'))->send(new VolantePagoSocioMail($detalle, $this->pdf($detalle)));

        return back()->with('success', "El volante de {$detalle->nombre} fue enviado correctamente.");
    }

    private function respuestaPdf(VolantePagoSocioDetalle $detalle): Response
    {
        $detalle->loadMissing('carga');
        $documento = Pdf::loadView('contabilidad.volantes-pago-socios-pdf', ['detalle' => $detalle])
            ->setPaper('letter', 'portrait');
        $nombre = $this->nombreArchivo($detalle);

        return $documento->download($nombre);
    }

    private function pdf(VolantePagoSocioDetalle $detalle): string
    {
        return Pdf::loadView('contabilidad.volantes-pago-socios-pdf', ['detalle' => $detalle])
            ->setPaper('letter', 'portrait')
            ->output();
    }

    private function nombreArchivo(VolantePagoSocioDetalle $detalle): string
    {
        return 'volante_pago_'.Str::slug($detalle->nombre, '_').'_'.$detalle->id.'.pdf';
    }
}
