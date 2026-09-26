<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Str;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*') || $request->expectsJson());

        // A duplicate key or constraint violation is bad input, not a server fault: surface it as
        // a 422 with a readable message instead of a 500 and a stack trace.
        $exceptions->render(function (QueryException $e, $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }
            if (! Str::contains(Str::lower($e->getMessage()), ['duplicate entry', 'integrity constraint', 'foreign key constraint'])) {
                return null;
            }

            $message = Str::contains(Str::lower($e->getMessage()), 'foreign key constraint')
                ? 'The selected record is linked to something that no longer exists.'
                : 'A record with these details already exists.';

            return response()->json(['message' => $message], 422);
        });
    })->create();
