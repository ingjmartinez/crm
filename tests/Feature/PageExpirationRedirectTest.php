<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PageExpirationRedirectTest extends TestCase
{
    public function test_expired_browser_page_redirects_to_inicio(): void
    {
        Route::middleware('web')->get('/test-page-expired', function (): void {
            abort(419);
        });

        $this->get('/test-page-expired')->assertRedirect(route('inicio.index'));
    }

    public function test_expired_json_request_preserves_419_response(): void
    {
        Route::middleware('web')->get('/test-page-expired-json', function (): void {
            abort(419);
        });

        $this->getJson('/test-page-expired-json')->assertStatus(419);
    }
}
