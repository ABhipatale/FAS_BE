<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        // Render terminates TLS at its edge and forwards to the container over
        // plain HTTP, so Laravel would otherwise generate http:// links and
        // redirects — which browsers block as mixed content on an https:// page.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
