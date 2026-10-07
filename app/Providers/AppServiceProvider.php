<?php

namespace App\Providers;

use App\Models\User;
use App\Services\FavoritoCatalogoService;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->hasRole('superadmin')) {
                return true;
            }

            if (! str_ends_with($ability, '.view')) {
                return null;
            }

            try {
                $user->loadMissing('viewPermissions');
                $override = $user->viewPermissions->firstWhere('name', $ability);

                if ($override !== null) {
                    return (bool) $override->pivot->allowed;
                }

                foreach (['recursos_humanos.view' => 'recursos_humanos.', 'reportes.view' => 'reportes.', 'servicios_generales.view' => 'servicios_generales.'] as $parent => $prefix) {
                    if ($ability === $parent) {
                        return $user->viewPermissions->contains(fn (Permission $permission): bool => (bool) $permission->pivot->allowed && str_starts_with($permission->name, $prefix)) ?: null;
                    }
                }
            } catch (QueryException) {
                return null;
            }

            return null;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
        Paginator::useBootstrapFive(); // o useBootstrapFour()

        View::composer('app', function ($view): void {
            /** @var User|null $usuario */
            $usuario = auth()->user();
            $catalogo = app(FavoritoCatalogoService::class);

            $view->with([
                'appFavoritos' => $usuario ? $catalogo->favoritos($usuario) : collect(),
                'appFavoritoActual' => $catalogo->actual($usuario, request()),
            ]);
        });
    }
}
