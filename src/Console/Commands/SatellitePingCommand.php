<?php

declare(strict_types=1);

namespace Moko\Satellite\Console\Commands;

use Illuminate\Console\Command;
use Moko\Satellite\Services\SatelliteClient;
use Moko\Satellite\Services\SatelliteException;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\error;
use function Laravel\Prompts\note;

final class SatellitePingCommand extends Command
{
    protected $signature = 'satellite:ping
                            {--endpoint=/health : Endpoint à appeler}
                            {--url= : Surcharge SATELLITE_API_URL}
                            {--no-token : Appel sans token Bearer (endpoint public)}';

    protected $description = "Teste la connectivité vers l'API distante";

    public function handle(): int
    {
        intro('Satellite Ping');

        $endpoint  = (string) $this->option('endpoint');
        $baseUrl   = (string) ($this->option('url') ?: config('satellite.url'));
        $token     = $this->option('no-token') ? '' : (string) config('satellite.token');
        $timeout   = (int) config('satellite.timeout', 10);
        $verifySSL = (bool) config('satellite.verify_ssl', true);

        $client = new class($baseUrl, $token, $timeout, 'stack', $verifySSL) extends SatelliteClient {
            public function call(string $endpoint): array
            {
                return $this->get($endpoint);
            }
        };

        $start = microtime(true);

        try {
            $data    = $client->call($endpoint);
            $elapsed = (int) round((microtime(true) - $start) * 1000);

            note("GET {$baseUrl}{$endpoint} … 200 OK ({$elapsed}ms)");

            if (! empty($data)) {
                $this->line('');
                $this->line('  '.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            return self::SUCCESS;
        } catch (SatelliteException $e) {
            $elapsed = (int) round((microtime(true) - $start) * 1000);
            error("GET {$baseUrl}{$endpoint} … {$e->statusCode} ({$elapsed}ms)");

            if (! empty($e->errors)) {
                $this->line('  '.json_encode($e->errors, JSON_UNESCAPED_UNICODE));
            }

            return self::FAILURE;
        } catch (\Exception $e) {
            $elapsed = (int) round((microtime(true) - $start) * 1000);
            error("GET {$baseUrl}{$endpoint} … {$e->getMessage()} ({$elapsed}ms)");

            return self::FAILURE;
        }
    }
}
