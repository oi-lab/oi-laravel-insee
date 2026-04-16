<?php

namespace OiLab\OiLaravelInsee\Tests;

use OiLab\OiLaravelInsee\Facades\Insee;
use OiLab\OiLaravelInsee\OiLaravelInseeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            OiLaravelInseeServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Insee' => Insee::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('oi-laravel-insee.client_secret', 'test-secret-key');
        $app['config']->set('oi-laravel-insee.client_id', 'test-client-id');
        $app['config']->set('oi-laravel-insee.base_url', 'https://api.insee.fr/api-sirene/3.11');
        $app['config']->set('oi-laravel-insee.cache_duration', 23);
    }
}
