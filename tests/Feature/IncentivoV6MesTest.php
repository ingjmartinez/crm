<?php

namespace Tests\Feature;

use App\Http\Requests\ReporteNuevoIncentivoV6Request;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IncentivoV6MesTest extends TestCase
{
    public function test_el_reporte_muestra_el_mes_actual_y_no_un_rango_editable(): void
    {
        Carbon::setTestNow('2026-10-12 10:00:00');

        try {
            $plantilla = file_get_contents(resource_path('views/incentivos/reporte-nuevo-incentivo-v6.blade.php'));
            $this->assertSame(1, preg_match('/<input type="month" id="ni_mes".+?<input type="hidden" id="ni_fecha_ini".+?<input type="hidden" id="ni_fecha_fin".+?\}\}">/su', $plantilla, $coincidencias));
            $vista = Blade::render($coincidencias[0]);

            $this->assertStringContainsString('type="month" id="ni_mes" class="form-control" value="2026-10"', $vista);
            $this->assertStringContainsString('type="hidden" id="ni_fecha_ini" value="2026-10-01"', $vista);
            $this->assertStringContainsString('type="hidden" id="ni_fecha_fin" value="2026-10-31"', $vista);
            $this->assertStringNotContainsString('type="date" id="ni_fecha_ini"', $vista);
            $this->assertStringNotContainsString('type="date" id="ni_fecha_fin"', $vista);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_el_mes_se_convierte_en_sus_fechas_completas_aunque_llegue_un_rango_distinto(): void
    {
        $this->actingAs(User::factory()->make());

        $request = ReporteNuevoIncentivoV6Request::create('/incentivos/reporte-nuevo-incentivo-v6', 'GET', [
            'mes' => '2024-02',
            'fecha_ini' => '2024-02-10',
            'fecha_fin' => '2024-02-12',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $this->assertSame('2024-02-01', $request->input('fecha_ini'));
        $this->assertSame('2024-02-29', $request->input('fecha_fin'));
    }

    public function test_el_reporte_rechaza_consultas_sin_mes(): void
    {
        $this->actingAs(User::factory()->make());

        $request = ReporteNuevoIncentivoV6Request::create('/incentivos/reporte-nuevo-incentivo-v6', 'GET', [
            'fecha_ini' => '2026-10-10',
            'fecha_fin' => '2026-10-12',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));

        $this->expectException(ValidationException::class);
        $request->validateResolved();
    }
}
