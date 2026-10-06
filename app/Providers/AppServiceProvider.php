<?php

namespace App\Providers;

use App\Http\Controllers\L5SwaggerController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use L5Swagger\Http\Controllers\SwaggerController;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SwaggerController::class, L5SwaggerController::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn (): Password => Password::min(8)
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised(),
        );

        // Every public subscribe request may send a confirmation email: cap per client and per address.
        RateLimiter::for('subscribe', fn (Request $request): array => [
            Limit::perMinute(10)->by('ip:'.$request->ip()),
            Limit::perHour(5)->by('email:'.Str::lower((string) $request->input('email'))),
        ]);

        if (! $this->app->environment('local')) {
            URL::forceScheme('https');
        }
    }
}
