<?php

use App\Modules\Ledger\Exceptions\CannotUpdateClosedRecurringTransactionException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Prefer env() here: config() is unavailable during early artisan bootstrap (e.g. package:discover / PHPStan).
        $trusted = env('TRUSTED_PROXIES', '*');

        if ($trusted === '*' || $trusted === true) {
            $middleware->trustProxies(at: '*');
        } elseif (is_string($trusted) && $trusted !== '') {
            $proxies = array_values(array_filter(array_map(
                static fn (string $proxy): string => trim($proxy),
                explode(',', $trusted),
            )));

            if ($proxies !== []) {
                $middleware->trustProxies(at: $proxies);
            }
        }

        $middleware->alias([
            'abilities' => Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (CannotUpdateClosedRecurringTransactionException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        });
    })->create();
