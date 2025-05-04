<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Http\Middleware\HandleCors;

class Kernel extends HttpKernel
{
    /**
     * Middlewares globales
     */
    protected $middleware = [
        \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
        HandleCors::class,
    ];

    /**
     * Grupos de middleware
     */
    protected $middlewareGroups = [
        'web' => [
            // \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
        ],

        'api' => [
            'throttle:60,1',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * Middleware por ruta
     */
    protected $routeMiddleware = [
        'apiauth' => \App\Http\Middleware\ApiAuthMiddleware::class,
    ];
}
