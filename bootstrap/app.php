<?php

use App\Http\Middlewares\AuthenticateServiceToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            AuthenticateServiceToken::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->create();

$app->useAppPath(dirname(__DIR__).'/src/App');

return $app;
