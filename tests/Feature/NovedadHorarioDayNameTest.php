<?php

namespace Tests\Feature;

use Tests\TestCase;

class NovedadHorarioDayNameTest extends TestCase
{
    public function test_detail_modal_formats_the_date_with_its_spanish_day_name(): void
    {
        $this->view('recursos_humanos.novedades_de_horario.index', [
            'ciudades' => collect(),
            'rutas' => collect(),
        ])
            ->assertSee("new Intl.DateTimeFormat('es-DO'", false)
            ->assertSee("weekday: 'long'", false)
            ->assertSee('formatearFechaConDia(item.fecha)', false);
    }
}
