<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            \URL::forceScheme('https');
        }

        // Ensure Livewire temporary upload directory exists
        $livewireTmpPath = storage_path('app/livewire-tmp');
        if (! is_dir($livewireTmpPath)) {
            mkdir($livewireTmpPath, 0755, true);
        }
    }
}
