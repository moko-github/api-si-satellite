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
use function Laravel\Prompts\warning;

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
        $file = config_path('logging.php');

        if (! File::exists($file)) {
            warning('config/logging.php introuvable : le canal de log « satellite » n\'a pas été configuré.');
            $this->printManualChannelInstructions();

            return;
        }

        $content = File::get($file);

        if (str_contains($content, "'satellite' =>")) {
            note("Canal de log 'satellite' déjà présent dans config/logging.php.");

            return;
        }

        $updated = self::buildLoggingChannelInsertion($content);

        if ($updated === null) {
            warning("Tableau 'channels' introuvable dans config/logging.php : le canal « satellite » n'a pas été ajouté.");
            $this->printManualChannelInstructions();

            return;
        }

        File::put($file, $updated);

        // Vérification post-écriture : on ne déclare le succès que s'il est réel.
        if (! str_contains(File::get($file), "'satellite' =>")) {
            warning("Échec de l'écriture du canal « satellite » dans config/logging.php.");
            $this->printManualChannelInstructions();

            return;
        }

        note("Canal de log 'satellite' ajouté dans config/logging.php.");
    }

    /**
     * Insère le canal de log 'satellite' juste après l'ouverture du tableau
     * 'channels' de config/logging.php.
     *
     * Retourne le nouveau contenu, ou null si l'ancre 'channels' => [ est absente
     * (fichier personnalisé) — auquel cas l'appelant avertit l'utilisateur plutôt
     * que de réécrire un fichier inchangé en affichant un faux succès.
     */
    public static function buildLoggingChannelInsertion(string $content): ?string
    {
        $anchor = "'channels' => [";
        $pos    = strpos($content, $anchor);

        if ($pos === false) {
            return null;
        }

        $insertAt = $pos + strlen($anchor);

        $block = "\n".self::satelliteChannelBlock();

        return substr($content, 0, $insertAt).$block.substr($content, $insertAt);
    }

    private function printManualChannelInstructions(): void
    {
        note(
            "Ajoute manuellement ce canal dans le tableau 'channels' de config/logging.php :\n\n"
            .self::satelliteChannelBlock()
        );
    }

    private static function satelliteChannelBlock(): string
    {
        return "        'satellite' => [\n"
            ."            'driver' => 'daily',\n"
            ."            'path'   => storage_path('logs/satellite.log'),\n"
            ."            'level'  => env('SATELLITE_LOG_LEVEL', 'debug'),\n"
            ."            'days'   => 14,\n"
            ."        ],\n";
    }

    private function appendEnvVariables(): void
    {
        $secret = Str::random(64);

        $block = "\n# Satellite API\n"
            ."SATELLITE_API_URL=\n"
            ."SATELLITE_API_TOKEN=\n"
            ."SATELLITE_API_TIMEOUT=10\n"
            ."SATELLITE_LOG_LEVEL=debug\n"
            ."SATELLITE_WEBHOOK_SECRET={$secret}\n"
            ."# Mettre à false uniquement si l'API utilise un certificat auto-signé (ex : qualification)\n"
            ."SATELLITE_VERIFY_SSL=true\n";

        $exampleBlock = "\n# Satellite API\n"
            ."SATELLITE_API_URL=\n"
            ."SATELLITE_API_TOKEN=\n"
            ."SATELLITE_API_TIMEOUT=10\n"
            ."SATELLITE_LOG_LEVEL=debug\n"
            ."SATELLITE_WEBHOOK_SECRET=\n"
            ."# Mettre à false uniquement si l'API utilise un certificat auto-signé (ex : qualification)\n"
            ."SATELLITE_VERIFY_SSL=true\n";

        $envFile = base_path('.env');
        if (! File::exists($envFile)) {
            warning('.env introuvable : ajoute manuellement les variables SATELLITE_* :'."\n".$block);
        } elseif (! str_contains(File::get($envFile), 'SATELLITE_API_URL')) {
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
