<?php

namespace Tests\Feature;

use Tests\TestCase;

class TareasTableLayoutTest extends TestCase
{
    public function test_table_initializes_when_list_tab_becomes_visible(): void
    {
        $view = file_get_contents(resource_path('views/tareas/index.blade.php'));

        $this->assertIsString($view);
        $this->assertMatchesRegularExpression(
            '/shown\\.bs\\.tab[\\s\\S]*?if \(!dataTable\) \{[\\s\\S]*?initDataTable\(\);[\\s\\S]*?\}[\\s\\S]*?dataTable\.columns\.adjust\(\)\.responsive\.recalc\(\)/',
            $view
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\$\(document\)\.ready\(function\(\) \{\s*cargarGantt\(\);\s*initDataTable\(\);/',
            $view
        );
    }
}
