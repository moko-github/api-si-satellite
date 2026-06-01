<?php

declare(strict_types=1);

namespace Moko\Satellite\Tests;

use Moko\Satellite\SatelliteServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [SatelliteServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('satellite.url', 'https://api.example.com');
        $app['config']->set('satellite.token', 'test-token');
        $app['config']->set('satellite.timeout', 10);
        $app['config']->set('satellite.webhook_secret', 'test-secret-64-chars-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
        $app['config']->set('satellite.verify_ssl', true);
    }
}
