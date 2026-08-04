<?php

declare(strict_types=1);

namespace Moko\Satellite\Services;

use Closure;
use Illuminate\Http\Client\ConnectionException;
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

    public function __construct(
        protected readonly string $baseUrl,
        protected readonly string $token,
        protected readonly int $timeout = 10,
        protected readonly string $logChannel = 'stack',
        protected readonly bool $verifySSL = true,
    ) {
        $this->http = Http::baseUrl($this->baseUrl)
            ->withToken($this->token)
            ->timeout($this->timeout)
            ->when(! $this->verifySSL, fn ($http) => $http->withoutVerifying())
            ->acceptJson();
    }

    /**
     * Exécute l'appel HTTP en traduisant une panne de transport en {@see SatelliteException}.
     *
     * Sans cette traduction, une API injoignable — DNS introuvable, connexion refusée, délai
     * dépassé — remonte en `ConnectionException` et traverse intacte les `catch
     * (SatelliteException)` des satellites. Les écrans écrits pour se dégrader proprement quand
     * l'API est indisponible cassent alors précisément dans ce cas-là, le seul pour lequel ils
     * avaient été écrits.
     *
     * @param  Closure(): Response  $call
     *
     * @throws SatelliteConnectionException
     */
    private function send(string $endpoint, Closure $call): Response
    {
        try {
            return $call();
        } catch (ConnectionException $e) {
            Log::channel($this->logChannel)->warning('[SatelliteClient] API injoignable', [
                'endpoint' => $endpoint,
                'reason' => $e->getMessage(),
            ]);

            throw new SatelliteConnectionException($endpoint, $e);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws SatelliteException
     */
    protected function get(string $endpoint, array $query = []): array
    {
        Log::channel($this->logChannel)->debug('[SatelliteClient] GET', [
            'endpoint' => $endpoint,
            'query'    => $query,
        ]);

        $response = $this->send($endpoint, fn (): Response => $this->http->get($endpoint, $query));

        Log::channel($this->logChannel)->debug('[SatelliteClient] GET response', [
            'endpoint' => $endpoint,
            'status'   => $response->status(),
        ]);

        if ($response->failed()) {
            throw new SatelliteException(
                message:    "Satellite client error on GET {$endpoint}: {$response->status()}",
                statusCode: $response->status(),
                endpoint:   $endpoint,
                errors:     $response->json('errors') ?? [],
            );
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
        Log::channel($this->logChannel)->debug('[SatelliteClient] POST', [
            'endpoint' => $endpoint,
            'payload'  => $payload,
        ]);

        $response = $this->send($endpoint, fn (): Response => $this->http->post($endpoint, $payload));

        Log::channel($this->logChannel)->debug('[SatelliteClient] POST response', [
            'endpoint' => $endpoint,
            'status'   => $response->status(),
        ]);

        if ($response->failed()) {
            throw new SatelliteException(
                message:    "Satellite client error on POST {$endpoint}: {$response->status()}",
                statusCode: $response->status(),
                endpoint:   $endpoint,
                errors:     $response->json('errors') ?? [],
            );
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
        Log::channel($this->logChannel)->debug('[SatelliteClient] PUT', [
            'endpoint' => $endpoint,
            'payload'  => $payload,
        ]);

        $response = $this->send($endpoint, fn (): Response => $this->http->put($endpoint, $payload));

        Log::channel($this->logChannel)->debug('[SatelliteClient] PUT response', [
            'endpoint' => $endpoint,
            'status'   => $response->status(),
        ]);

        if ($response->failed()) {
            throw new SatelliteException(
                message:    "Satellite client error on PUT {$endpoint}: {$response->status()}",
                statusCode: $response->status(),
                endpoint:   $endpoint,
                errors:     $response->json('errors') ?? [],
            );
        }

        return $response->json() ?? [];
    }

    /**
     * @throws SatelliteException
     */
    protected function delete(string $endpoint): void
    {
        Log::channel($this->logChannel)->debug('[SatelliteClient] DELETE', [
            'endpoint' => $endpoint,
        ]);

        $response = $this->send($endpoint, fn (): Response => $this->http->delete($endpoint));

        if ($response->failed()) {
            throw new SatelliteException(
                message:    "Satellite client error on DELETE {$endpoint}: {$response->status()}",
                statusCode: $response->status(),
                endpoint:   $endpoint,
                errors:     $response->json('errors') ?? [],
            );
        }
    }
}
