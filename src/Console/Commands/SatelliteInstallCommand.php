<?php

declare(strict_types=1);

namespace Moko\Satellite\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;

/**
 * Installe l'infrastructure satellite dans l'application hôte.
 *
 * Actions :
 *  - publication de config/satellite.php
 *  - publication des stubs dans stubs/satellite/
 *  - ajout du canal de log 'satellite' dans config/logging.php
 *  - ajout des variables SATELLITE_* dans .env et .env.example
 */
class SatelliteInstallCommand extends Command
{
    protected $signature = 'satellite:install
                            {--force : Écrase les fichiers existants}';

    protected $description = "Installe l'infrastructure satellite (config, stubs, logging, .env)";

    public function handle(): int
    {
        intro('Installation Satellite');

        $confirm = confirm(
            label: "Installer l'infrastructure satellite ?",
            default: true,
            hint: 'Publie config/satellite.php, les stubs, configure le logging et .env.',
        );

        if (! $confirm) {
            note('Installation annulée.');

            return self::SUCCESS;
        }

        $this->call('vendor:publish', [
            '--tag'   => 'satellite-config',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->call('vendor:publish', [
            '--tag'   => 'satellite-stubs',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->configureLoggingChannel();
        $this->appendEnvVariables();

        outro(
            "Satellite installé.\n"
            ."  1. Renseigne SATELLITE_API_URL, SATELLITE_API_TOKEN et SATELLITE_WEBHOOK_SECRET dans .env\n"
            .'  2. Installe ton package privé (ex: moko/api-si-client) pour les DTOs, events et jobs'
        );

        return self::SUCCESS;
    }

    private function configureLoggingChannel(): void
    {
        $file    = config_path('logging.php');
        $content = File::get($file);

        if (str_contains($content, "'satellite' =>")) {
            return;
        }

        $marker    = "        'emergency' => [";
        $insertion = "        'satellite' => [\n"
            ."            'driver' => 'daily',\n"
            ."            'path'   => storage_path('logs/satellite.log'),\n"
            ."            'level'  => env('SATELLITE_LOG_LEVEL', 'debug'),\n"
            ."            'days'   => 14,\n"
            ."        ],\n\n"
            ."        'emergency' => [";

        File::put($file, str_replace($marker, $insertion, $content));

        note("Canal de log 'satellite' ajouté dans config/logging.php.");
    }

    private function appendEnvVariables(): void
    {
        $secret = Str::random(64);

        $block = "\n# Satellite API\n"
            ."SATELLITE_API_URL=\n"
            ."SATELLITE_API_TOKEN=\n"
            ."SATELLITE_API_TIMEOUT=10\n"
            ."SATELLITE_LOG_LEVEL=debug\n"
            ."SATELLITE_WEBHOOK_SECRET={$secret}\n";

        $exampleBlock = "\n# Satellite API\n"
            ."SATELLITE_API_URL=\n"
            ."SATELLITE_API_TOKEN=\n"
            ."SATELLITE_API_TIMEOUT=10\n"
            ."SATELLITE_LOG_LEVEL=debug\n"
            ."SATELLITE_WEBHOOK_SECRET=\n";

        $envFile = base_path('.env');
        if (File::exists($envFile) && ! str_contains(File::get($envFile), 'SATELLITE_API_URL')) {
            File::append($envFile, $block);
            note('Variables SATELLITE_* ajoutées dans .env.');
        }

        $exampleFile = base_path('.env.example');
        if (File::exists($exampleFile) && ! str_contains(File::get($exampleFile), 'SATELLITE_API_URL')) {
            File::append($exampleFile, $exampleBlock);
            note('Variables SATELLITE_* ajoutées dans .env.example.');
        }
    }
}
