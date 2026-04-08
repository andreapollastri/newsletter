<?php

namespace App\Providers;

use App\Http\Controllers\L5SwaggerController;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
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

        if (! $this->app->environment('local')) {
            URL::forceScheme('https');
        }
    }
}
