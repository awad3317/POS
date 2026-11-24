<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Schema::defaultStringLength(191);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        if (
            $this->app->isProduction()
            && (config('app.force_https') || str_starts_with((string) config('app.url'), 'https://'))
        ) {
            URL::forceScheme('https');
        }
    }
}
