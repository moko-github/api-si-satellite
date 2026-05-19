<?php

declare(strict_types=1);

namespace Moko\Satellite;

use Illuminate\Support\ServiceProvider;
use Moko\Satellite\Console\Commands\SatelliteInstallCommand;

class SatelliteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/satellite.php', 'satellite');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/satellite.php' => config_path('satellite.php'),
            ], 'satellite-config');

            $this->publishes([
                __DIR__.'/../stubs' => base_path('stubs/satellite'),
            ], 'satellite-stubs');

            $this->commands([
                SatelliteInstallCommand::class,
            ]);
        }
    }
}
