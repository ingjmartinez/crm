<?php

namespace App\Http\Controllers\Tecnologia;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class MonitoreoAgenciaPlazaController extends Controller
{
    public function __invoke(): View
    {
        return view('tecnologia.monitoreo-agencias-plaza');
    }
}
