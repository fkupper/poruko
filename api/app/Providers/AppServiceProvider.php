<?php

namespace App\Providers;

use App\Models\LedgerUser;
use App\Observers\LedgerUserObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        LedgerUser::observe(LedgerUserObserver::class);

        Route::bind('mcpToken', function (string $value): PersonalAccessToken {
            $token = PersonalAccessToken::query()->find($value);

            if (!$token instanceof PersonalAccessToken) {
                abort(404, 'MCP key not found.');
            }

            return $token;
        });

        RateLimiter::for('api', function (Request $request): Limit {
            return $this->app->environment('testing')
                ? Limit::none()
                : Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $email = (string) $request->input('email', '');

            return Limit::perMinute(5)->by(mb_strtolower($email) . '|' . $request->ip());
        });

        RateLimiter::for('two-factor', function (Request $request): Limit {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(5)->by((string) $key);
        });
    }
}
