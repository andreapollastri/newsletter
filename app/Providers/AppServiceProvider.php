<?php

namespace App\Providers;

use App\Http\Controllers\L5SwaggerController;
use Illuminate\Support\ServiceProvider;
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
        if (! $this->app->environment('local')) {
            \URL::forceScheme('https');
        }
    }
}
