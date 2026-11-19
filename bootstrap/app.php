<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Participant text must be stored exactly as typed: never trim or null it.
        $middleware->trimStrings(except: ['body']);
        $middleware->convertEmptyStringsToNull(except: [fn (Request $request) => $request->is('api/*')]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('preview/*') || $request->is('view-as/*') || $request->is('admin-export/*')) {
                return route('filament.admin.auth.login');
            }

            return route('participant.no-access');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
