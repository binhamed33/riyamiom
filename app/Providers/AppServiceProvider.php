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
        config(['app.timezone' => 'Asia/Muscat']);
        date_default_timezone_set('Asia/Muscat');

        if ($this->app->environment('production') || request()->server('HTTP_X_FORWARDED_PROTO') === 'https' || request()->secure()) {
            URL::forceScheme('https');
        }
    }
}
