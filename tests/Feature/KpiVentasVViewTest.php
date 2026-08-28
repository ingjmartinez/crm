<?php

namespace Tests\Feature;

use Tests\TestCase;

class KpiVentasVViewTest extends TestCase
{
    public function test_muestra_un_titulo_amigable_en_la_tabla_de_ventas_comparativas(): void
    {
        $vista = file_get_contents(resource_path('views/comercial/kpi-ventas-v.blade.php'));

        $this->assertIsString($vista);
        $this->assertStringContainsString('Ventas comparativas', $vista);
        $this->assertStringNotContainsString('Tabla de Validación (vt_usuarios_bet)', $vista);
    }
}
