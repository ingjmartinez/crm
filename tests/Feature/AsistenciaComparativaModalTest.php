<?php

namespace Tests\Feature;

use Tests\TestCase;

class AsistenciaComparativaModalTest extends TestCase
{
    public function test_status_configuration_modal_is_moved_to_body_and_uses_bootstrap_instance(): void
    {
        $this->view('agencias.asistencia_comparativa', [
            'coordinadores' => collect(),
        ])
            ->assertSee('document.body.appendChild(modalConfigEstadosElement)', false)
            ->assertSee('bootstrap.Modal.getOrCreateInstance(modalConfigEstadosElement)', false)
            ->assertSee('modalConfigEstados.show()', false)
            ->assertSee('modalConfigEstados.hide()', false)
            ->assertDontSee("$('#modalConfigEstadosInc').modal('show')", false);
    }
}
