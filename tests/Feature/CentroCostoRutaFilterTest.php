<?php

namespace Tests\Feature;

use Tests\TestCase;

class CentroCostoRutaFilterTest extends TestCase
{
    public function test_route_filter_is_displayed_next_to_status_and_filters_route_column(): void
    {
        $response = $this->withoutMiddleware()->get(route('contabilidad.centro-costo'));

        $response->assertOk()
            ->assertSee('id="filtroRutaCentroCosto"', false)
            ->assertSee('centrosCostoTable.column(5).search(busquedaRuta, true, false);', false)
            ->assertSee('cargarOpcionesRutaCentroCosto(dataFiltrada);', false)
            ->assertSee("String(item.IdGrupo ?? '').trim()", false);

        $documento = new \DOMDocument;
        @$documento->loadHTML($response->getContent());
        $xpath = new \DOMXPath($documento);

        $this->assertSame(1, $xpath->query("//*[@id='filtroEstadoCentroCosto']/parent::div/following-sibling::div[1]//*[@id='filtroRutaCentroCosto']")->length);
    }
}
