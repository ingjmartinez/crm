<?php

namespace App\Http\Controllers;

use App\Http\Requests\Operaciones\ProcesarMovimientosRutasRequest;
use App\Services\Operaciones\MovimientosRutasAgenciaService;
use App\Services\Operaciones\MovimientosRutasCsvService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class OperacionesMovimientosRutasController extends Controller
{
    public function __construct(
        private readonly MovimientosRutasCsvService $movimientosRutasCsvService,
        private readonly MovimientosRutasAgenciaService $movimientosRutasAgenciaService
    ) {}

    public function index(): View
    {
        return view('operaciones.movimientos-rutas', $this->datosVacios());
    }

    public function procesar(ProcesarMovimientosRutasRequest $request): View
    {
        /** @var UploadedFile $archivo */
        $archivo = $request->validated('archivo_csv');
        $resultado = $this->movimientosRutasCsvService->procesar($archivo);
        $resultado['transacciones'] = $this->movimientosRutasAgenciaService->enriquecer(
            $resultado['transacciones']
        );

        return view('operaciones.movimientos-rutas', [
            ...$resultado,
            'nombreArchivo' => $archivo->getClientOriginalName(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosVacios(): array
    {
        return [
            'resumen' => null,
            'rutas' => [],
            'transacciones' => [],
            'grafico_rutas' => [],
            'tendencia_diaria' => [],
            'control' => null,
            'nombreArchivo' => null,
        ];
    }
}
