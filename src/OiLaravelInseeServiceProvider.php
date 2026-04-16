<?php

namespace OiLab\OiLaravelInsee;

use Illuminate\Support\ServiceProvider;

class OiLaravelInseeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/oi-laravel-insee.php',
            'oi-laravel-insee'
        );

        $this->app->singleton(Client::class, function ($app) {
            return new Client(
                clientSecret: config('oi-laravel-insee.client_secret'),
                clientId: config('oi-laravel-insee.client_id'),
                baseUrl: config('oi-laravel-insee.base_url'),
                cacheDuration: config('oi-laravel-insee.cache_duration')
            );
        });

        $this->app->alias(Client::class, 'insee');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/oi-laravel-insee.php' => config_path('oi-laravel-insee.php'),
            ], 'oi-laravel-insee-config');
        }
    }
}
