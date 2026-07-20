<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Exceptions\UnauthorizedException;

return Application::configure(basePath: dirname(__DIR__))
  ->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
  )
  ->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
    ]);

    $middleware->web(append: [
        \Illuminate\Session\Middleware\AuthenticateSession::class,
        \App\Http\Middleware\ForcePasswordChange::class,
        \App\Http\Middleware\SetLocaleMiddleware::class,
    ]);
  })
  ->withExceptions(function (Exceptions $exceptions) {
    // Handle Spatie permission/role unauthorized exceptions
    $exceptions->render(function (UnauthorizedException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'You do not have the required authorization.',
            ], 403);
        }

        abort(403, 'You do not have permission to access this page.');
    });

    // Handle generic authorization exceptions
    $exceptions->render(function (AuthorizationException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        abort(403, $e->getMessage());
    });
  })->create();