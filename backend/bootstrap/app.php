<?php

use App\Http\Controllers\HealthController;
use App\Http\Exceptions\DomainExceptionRenderer;
use App\Http\Responses\ApiErrorResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Registered outside the web group on purpose: monitoring polls
            // this endpoint continuously, and the session middleware would
            // start and persist a session on every single poll.
            Route::get('/health', HealthController::class);
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Wired by hand because this file is the composition root: the
        // renderer takes its collaborator through the constructor, so no
        // service lookup happens while a request is being handled. The class
        // stays autowirable all the same, and the test resolves it from the
        // container to prove it.
        $exceptions->render(new DomainExceptionRenderer(new ApiErrorResponse));
    })->create();
