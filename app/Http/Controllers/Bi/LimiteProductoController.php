<?php

namespace App\Http\Controllers\Bi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bi\GuardarLimiteProductoRequest;
use App\Models\BiLimiteProducto;
use Illuminate\Http\RedirectResponse;

class LimiteProductoController extends Controller
{
    public function guardar(GuardarLimiteProductoRequest $request): RedirectResponse
    {
        BiLimiteProducto::query()->updateOrCreate(
            ['producto_id' => $request->validated('producto_id'), 'terminal' => $request->validated('terminal') ?? ''],
            $request->safe()->only(['monto', 'activo']),
        );

        return back()->with('biLimiteMensaje', 'La alerta por producto se guardó.');
    }

    public function eliminar(BiLimiteProducto $limiteProducto): RedirectResponse
    {
        $limiteProducto->delete();

        return back()->with('biLimiteMensaje', 'La configuración del límite se eliminó.');
    }
}
