<?php

namespace Tests\Feature;

use Tests\TestCase;

class SidebarLayoutTest extends TestCase
{
    public function test_sidebar_uses_the_theme_scroll_without_a_competing_wheel_handler(): void
    {
        $layout = file_get_contents(resource_path('views/app.blade.php'));

        $this->assertStringContainsString('<div id="scrollbar">', $layout);
        $this->assertStringContainsString("asset('libs/simplebar/simplebar.min.js')", $layout);
        $this->assertStringContainsString("asset('js/app.js')", $layout);
        $this->assertStringNotContainsString("document.addEventListener('wheel'", $layout);
        $this->assertStringNotContainsString('getMenuScrollElement', $layout);
        $this->assertStringNotContainsString('--crm-sidebar-height', $layout);
    }
}
