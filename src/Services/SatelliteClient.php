<?php

declare(strict_types=1);

namespace Moko\Satellite\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Classe de base pour les clients HTTP satellites.
 *
 * Fournit get(), post(), put(), delete() avec logging et gestion d'erreurs.
 * Étendre cette classe dans le package privé pour ajouter des méthodes typées
 * retournant vos DTOs métier.
 *
 * Exemple :
 *   final class ApiSiClient extends SatelliteClient
 *   {
 *       public function __construct()
 *       {
 *           parent::__construct(
 *               baseUrl:    (string) config('api-si.url'),
 *               token:      (string) config('api-si.token'),
 *               timeout:    (int)    config('api-si.timeout', 10),
 *               logChannel: 'api-si',
 *               verifySSL:  (bool)   config('api-si.verify_ssl', true),
 *               retries:    (int)    config('api-si.retries', 2),
 *           );
 *       }
 *
 *       public function getUser(string $kerberos): SiUserDTO
 *       {
 *           return SiUserDTO::fromArray($this->get("/api/v1/users/{$kerberos}"));
 *       }
 *   }
 */
abstract class SatelliteClient
{
    protected PendingRequest $http;

    /**
     * Clés (insensibles à la casse, comparées par sous-chaîne) dont la valeur
     * est masquée dans les logs. Surchargeable dans la sous-classe.
     *
     * @var list<string>
     */
    protected array $redactKeys = [
        'password',
        'token',
        'secret',
        'authorization',
        'api_key',
        'apikey',
        'access_token',
        'refresh_token',
    ];

    public function __construct(
        protected readonly string $baseUrl,
        protected readonly string $token,
        protected readonly int $timeout = 10,
        protected readonly string $logChannel = 'stack',
        protected readonly bool $verifySSL = true,
        protected readonly int $retries = 2,
        protected readonly int $retryDelay = 200,
    ) {
        $this->http = Http::baseUrl($this->baseUrl)
            ->withToken($this->token)
            ->timeout($this->timeout)
            ->when(! $this->verifySSL, fn ($http) => $http->withoutVerifying())
            ->when(
                $this->retries > 0,
                fn ($http) => $http->retry($this->retries + 1, $this->retryDelay, throw: false),
            )
            ->acceptJson();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws SatelliteException
     */
    protected function get(string $endpoint, array $query = []): array
    {
        $this->log('GET', $endpoint, ['query' => $this->redact($query)]);

        $response = $this->http->get($endpoint, $query);

        $this->logResponse('GET', $endpoint, $response->status());

        if ($response->failed()) {
            throw $this->failure('GET', $endpoint, $response);
        }

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws SatelliteException
     */
    protected function post(string $endpoint, array $payload = []): array
    {
        $this->log('POST', $endpoint, ['payload' => $this->redact($payload)]);

        $response = $this->http->post($endpoint, $payload);

        $this->logResponse('POST', $endpoint, $response->status());

        if ($response->failed()) {
            throw $this->failure('POST', $endpoint, $response);
        }

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws SatelliteException
     */
    protected function put(string $endpoint, array $payload = []): array
    {
        $this->log('PUT', $endpoint, ['payload' => $this->redact($payload)]);

        $response = $this->http->put($endpoint, $payload);

        $this->logResponse('PUT', $endpoint, $response->status());

        if ($response->failed()) {
            throw $this->failure('PUT', $endpoint, $response);
        }

        return $response->json() ?? [];
    }

    /**
     * @throws SatelliteException
     */
    protected function delete(string $endpoint): void
    {
        $this->log('DELETE', $endpoint);

        $response = $this->http->delete($endpoint);

        $this->logResponse('DELETE', $endpoint, $response->status());

        if ($response->failed()) {
            throw $this->failure('DELETE', $endpoint, $response);
        }
    }

    /**
     * Construit l'exception typée à partir d'une réponse en échec, en
     * conservant le corps brut pour les erreurs non-JSON (502 HTML, etc.).
     */
    protected function failure(string $verb, string $endpoint, Response $response): SatelliteException
    {
        return new SatelliteException(
            message: "Satellite client error on {$verb} {$endpoint}: {$response->status()}",
            statusCode: $response->status(),
            endpoint: $endpoint,
            errors: $response->json('errors') ?? [],
            body: $response->body(),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $verb, string $endpoint, array $context = []): void
    {
        Log::channel($this->logChannel)->debug("[SatelliteClient] {$verb}", [
            'endpoint' => $endpoint,
            ...$context,
        ]);
    }

    private function logResponse(string $verb, string $endpoint, int $status): void
    {
        Log::channel($this->logChannel)->debug("[SatelliteClient] {$verb} response", [
            'endpoint' => $endpoint,
            'status' => $status,
        ]);
    }

    /**
     * Masque récursivement la valeur des clés sensibles avant logging.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    protected function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->redact($value);

                continue;
            }

            if (is_string($key) && $this->isSensitive($key)) {
                $data[$key] = '[REDACTED]';
            }
        }

        return $data;
    }

    private function isSensitive(string $key): bool
    {
        $key = strtolower($key);

        foreach ($this->redactKeys as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
