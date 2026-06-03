<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

$confirmLabel = "Installer l'infrastructure satellite ?";

// Sauvegarde puis restaure les fichiers que la commande peut écrire, afin de
// ne pas polluer le squelette Testbench entre les tests.
beforeEach(function () {
    $this->touched = [
        config_path('logging.php'),
        config_path('satellite.php'),
        base_path('.env'),
        base_path('.env.example'),
    ];

    $this->originals = [];
    foreach ($this->touched as $path) {
        $this->originals[$path] = File::exists($path) ? File::get($path) : null;
    }
});

afterEach(function () {
    foreach ($this->originals as $path => $content) {
        if ($content === null) {
            File::delete($path);
        } else {
            File::put($path, $content);
        }
    }

    File::deleteDirectory(base_path('stubs/satellite'));
});

it('ajoute le canal de log et les variables d\'environnement', function () use ($confirmLabel) {
    File::ensureDirectoryExists(config_path());
    File::put(config_path('logging.php'), <<<'PHP'
<?php

return [
    'channels' => [
        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
    ],
];
PHP);
    File::put(base_path('.env'), "APP_NAME=Testing\n");
    File::put(base_path('.env.example'), "APP_NAME=Testing\n");

    $this->artisan('satellite:install')
        ->expectsConfirmation($confirmLabel, 'yes')
        ->assertSuccessful();

    expect(File::get(config_path('logging.php')))->toContain("'satellite' =>");

    expect(File::get(base_path('.env')))
        ->toContain('SATELLITE_API_URL')
        ->toContain('SATELLITE_LOG_CHANNEL=satellite')
        ->toContain('SATELLITE_API_RETRIES=2');

    expect(File::get(base_path('.env.example')))->toContain('SATELLITE_API_URL');
});

it('avertit et n\'ajoute rien quand le marqueur de logging est absent', function () use ($confirmLabel) {
    File::ensureDirectoryExists(config_path());
    File::put(config_path('logging.php'), "<?php\n\nreturn ['channels' => []];\n");

    $this->artisan('satellite:install')
        ->expectsConfirmation($confirmLabel, 'yes')
        ->assertSuccessful();

    expect(File::get(config_path('logging.php')))->not->toContain("'satellite' =>");
});

it('est annulable et ne touche à rien', function () use ($confirmLabel) {
    File::ensureDirectoryExists(config_path());
    File::put(config_path('logging.php'), "<?php\n\nreturn ['channels' => ['emergency' => []]];\n");

    $this->artisan('satellite:install')
        ->expectsConfirmation($confirmLabel, 'no')
        ->assertSuccessful();

    expect(File::get(config_path('logging.php')))->not->toContain("'satellite' =>");
});
