<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureViewAccess
{
    public function __construct(private readonly \App\Services\AccesoVistaService $access) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->hasRole('superadmin')) {
            return $next($request);
        }

        $path = '/'.trim($request->path(), '/');
        if ($path === '/favoritos/toggle' || str_starts_with($path, '/password/')) {
            return $next($request);
        }
        if ($path === '/' && ! $this->access->canView($user, '/')) {
            return response()->view('roles.access-home');
        }
        foreach ($this->access->modules() as $key => $module) {
            if ($module['url'] === $path) {
                abort_unless($this->access->canModule($user, $key), 403);

                return $next($request);
            }
        }

        if (str_starts_with($path, '/procesos/') && $request->method() !== 'GET') {
            $department = $request->input('departamento');
            if ($request->route('id')) {
                $process = \App\Models\ProcesoDepartamento::query()->findOrFail($request->route('id'));
                abort_unless($this->access->canView($user, '/procesos/'.$process->departamento), 403);
                $department ??= $process->departamento;
            }
            abort_unless(is_string($department) && $this->access->canView($user, '/procesos/'.$department), 403);

            return $next($request);
        }

        abort_unless($this->access->canPath($user, $path), 403);

        return $next($request);
    }
}
