<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

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
        if($this->app->environment('production') || env('APP_URL')) {
            URL::forceScheme('https');
        }
        // Log slow queries (queries taking more than 1 second)
        \Illuminate\Support\Facades\DB::listen(function ($query) {
            if ($query->time > 1000) { // More than 1 second
                \Illuminate\Support\Facades\Log::warning('Slow Query Detected', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time . 'ms'
                ]);
            }
        });
    }
}
