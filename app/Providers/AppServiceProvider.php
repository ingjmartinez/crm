<?php

namespace App\Providers;

use App\Models\User;
use App\Services\FavoritoCatalogoService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Gate::before(function ($user, string $ability) {
            return $user->hasRole('superadmin') ? true : null;
        });

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
