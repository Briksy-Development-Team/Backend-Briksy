<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserHasPermission;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'role' => EnsureUserHasRole::class,
            'permission' => EnsureUserHasPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $exception->errors(),
            ], 422);
        });

        $exceptions->render(function (ModelNotFoundException $exception, $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
            ], 404);
        });
    })->create();
