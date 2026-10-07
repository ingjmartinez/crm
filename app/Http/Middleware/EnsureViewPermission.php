<?php

namespace App\Http\Middleware;

use App\ViewPermissionCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureViewPermission
{
    public function __construct(private readonly ViewPermissionCatalog $catalog) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = '/'.trim($request->path(), '/');
        $matches = $this->catalog->items()
            ->filter(fn (array $item): bool => $item['path'] === $path || ($item['path'] !== '/' && str_starts_with($path, $item['path'].'/')));

        if ($matches->isNotEmpty()) {
            $length = $matches->max(fn (array $item): int => strlen($item['path']));
            $user = $request->user();
            abort_unless($user && $matches->contains(fn (array $item): bool => strlen($item['path']) === $length
                && $user->can($item['permission'])
                && ($item['role'] === null || $user->hasRole($item['role']))), 403);
        }

        return $next($request);
    }
}
