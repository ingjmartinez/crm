<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleBiInertiaRequests extends Middleware
{
    protected $rootView = 'bi';

    public function handle(Request $request, Closure $next): Response
    {
        config()->set('inertia.use_script_element_for_initial_page', true);

        return parent::handle($request, $next);
    }
}
