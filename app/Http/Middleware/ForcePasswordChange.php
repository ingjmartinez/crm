<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (bool) ($user->must_change_password ?? false) && !$request->routeIs('password.force.*')) {
            return redirect()->route('password.force.form');
        }

        return $next($request);
    }
}
