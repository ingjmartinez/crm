<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\ExpireInactiveSession::class,
        ]);

        $middleware->alias([
            'role' => Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'force.password.change' => \App\Http\Middleware\ForcePasswordChange::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if ($request->is('reportes-gestion-agencias/procesar')) {
                return response()->view('reportes.gestion-agencias', [
                    'filas' => collect(),
                    'resumen' => null,
                    'archivos' => [],
                    'agenciasSinVentas' => collect(),
                    'errores' => [
                        'Los archivos superan el limite actual de PHP. Valores detectados: post_max_size=' . ini_get('post_max_size') . ', upload_max_filesize=' . ini_get('upload_max_filesize') . '. Reinicia el servidor con composer serve para usar 64M.',
                    ],
                ], 413);
            }

            if ($request->is('comercial/gestion-usuarios/*')) {
                return response()->view('comercial.gestion_usuarios', [
                    'archivoNombre' => null,
                    'periodoReporte' => null,
                    'filas' => [],
                    'totalFilas' => 0,
                    'totalCedulas' => 0,
                    'limiteVista' => 500,
                    'errores' => [
                        'El archivo supera el limite actual de PHP. Valores detectados: post_max_size=' . ini_get('post_max_size') . ', upload_max_filesize=' . ini_get('upload_max_filesize') . '. Reinicia el servidor con composer serve para usar 64M.',
                    ],
                ], 413);
            }

            return null;
        });
    })->create();
