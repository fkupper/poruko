<?php

namespace App\Providers;

use App\Models\LedgerUser;
use App\Observers\LedgerUserObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
