<?php

use App\Http\Middleware\UseRequestRootUrl;
use App\Http\Middleware\EnsurePasswordWasChanged;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\FinishRequestDebug;
use App\Http\Middleware\LoadUserThemeSettings;
use App\Http\Middleware\StartRequestDebug;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'password.changed' => EnsurePasswordWasChanged::class,
            'user.active' => EnsureUserIsActive::class,
        ]);

        $middleware->web(prepend: [
            UseRequestRootUrl::class,
            StartRequestDebug::class,
        ]);

        $middleware->web(append: [
            LoadUserThemeSettings::class,
            FinishRequestDebug::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
