<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventDeletionForAdmin2
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('admin2') && $this->isDeletionRequest($request)) {
            abort(403, 'El rol admin2 no tiene permiso para eliminar contenido.');
        }

        return $next($request);
    }

    private function isDeletionRequest(Request $request): bool
    {
        if ($request->isMethod('DELETE')) {
            return true;
        }

        $routeAction = strtolower((string) $request->route()?->getActionMethod());
        $routeName = strtolower((string) $request->route()?->getName());
        $path = strtolower($request->path());

        return preg_match('/(destroy|delete|eliminar|borrar)/', $routeAction.' '.$routeName.' '.$path) === 1;
    }
}
