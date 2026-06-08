<?php

declare(strict_types=1);

use Moko\Satellite\Console\Commands\SatelliteInstallCommand;

$defaultLogging = <<<'PHP'
<?php

return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
        ],
    ],
];
PHP;

it('insère le canal satellite après le tableau channels', function () use ($defaultLogging) {
    $result = SatelliteInstallCommand::buildLoggingChannelInsertion($defaultLogging);

    expect($result)->not->toBeNull()
        ->and($result)->toContain("'satellite' =>")
        ->and($result)->toContain("storage_path('logs/satellite.log')")
        ->and($result)->toContain("env('SATELLITE_LOG_LEVEL', 'debug')");
});

it('insère le canal même sans canal emergency', function () {
    // Fichier sans 'emergency' => [ : l'ancienne implémentation échouait silencieusement.
    $content = <<<'PHP'
<?php

return [
    'channels' => [
        'stack' => [
            'driver' => 'stack',
        ],
    ],
];
PHP;

    $result = SatelliteInstallCommand::buildLoggingChannelInsertion($content);

    expect($result)->not->toBeNull()
        ->and($result)->toContain("'satellite' =>");
});

it('retourne null quand le tableau channels est absent', function () {
    $content = <<<'PHP'
<?php

return [
    'default' => env('LOG_CHANNEL', 'stack'),
];
PHP;

    expect(SatelliteInstallCommand::buildLoggingChannelInsertion($content))->toBeNull();
});
