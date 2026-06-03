<?php

declare(strict_types=1);

namespace Moko\Satellite\Services;

use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Classe de base pour les clients HTTP satellites.
 *
 * Fournit get(), post(), put(), patch(), delete() et paginate() avec logging,
 * relances et gestion d'erreurs typée. Étendre cette classe dans le package
 * privé pour ajouter des méthodes typées retournant vos DTOs métier.
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
     * Canal de log effectivement utilisé (résolu depuis $logChannel ou,
     * à défaut, depuis config('satellite.log_channel')).
     */
    protected readonly string $channel;

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

    protected readonly int $timeout;

    protected readonly int $retries;

    protected readonly int $retryDelay;

    protected readonly int $connectTimeout;

    /**
     * Les paramètres int laissés à null sont résolus depuis config('satellite.*'),
     * afin que les variables SATELLITE_* documentées prennent effet par défaut.
     */
    public function __construct(
        protected readonly string $baseUrl,
        protected readonly string $token,
        ?int $timeout = null,
        string $logChannel = '',
        protected readonly bool $verifySSL = true,
        ?int $retries = null,
        ?int $retryDelay = null,
        ?int $connectTimeout = null,
    ) {
        $this->channel = $logChannel !== ''
            ? $logChannel
            : (string) config('satellite.log_channel', 'stack');

        $this->timeout = $timeout ?? (int) config('satellite.timeout', 10);
        $this->retries = $retries ?? (int) config('satellite.retries', 2);
        $this->retryDelay = $retryDelay ?? (int) config('satellite.retry_delay', 200);
        $this->connectTimeout = $connectTimeout ?? (int) config('satellite.connect_timeout', 10);

        $this->http = Http::baseUrl($this->baseUrl)
            ->withToken($this->token)
            ->timeout($this->timeout)
            ->when($this->connectTimeout > 0, fn ($http) => $http->connectTimeout($this->connectTimeout))
            ->when(! $this->verifySSL, fn ($http) => $http->withoutVerifying())
            ->when(
                $this->retries > 0,
                fn ($http) => $http->retry(
                    $this->retries + 1,
                    $this->retryDelay,
                    fn (Throwable $e) => $this->shouldRetry($e),
                    throw: false,
                ),
            )
            ->acceptJson();
    }

    /**
     * Décide si une tentative échouée doit être rejouée.
     *
     * On rejoue uniquement les échecs transitoires : erreurs de connexion
     * (DNS, refus, timeout réseau), throttling (429) et erreurs serveur (5xx).
     * Les erreurs 4xx déterministes (422, 404, 401…) ne sont jamais rejouées.
     */
    protected function shouldRetry(Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        if ($e instanceof RequestException) {
            $status = $e->response->status();

            return $status === 429 || $status >= 500;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws SatelliteException
     */
    protected function get(string $endpoint, array $query = []): array
    {
        return $this->request('GET', $endpoint, $query)->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws SatelliteException
     */
    protected function post(string $endpoint, array $payload = []): array
    {
        return $this->request('POST', $endpoint, $payload)->json() ?? [];
    }

    /**
     * Remplacement complet d'une ressource (le payload représente l'objet entier).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws SatelliteException
     */
    protected function put(string $endpoint, array $payload = []): array
    {
        return $this->request('PUT', $endpoint, $payload)->json() ?? [];
    }

    /**
     * Mise à jour partielle d'une ressource (seuls les champs fournis changent).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws SatelliteException
     */
    protected function patch(string $endpoint, array $payload = []): array
    {
        return $this->request('PATCH', $endpoint, $payload)->json() ?? [];
    }

    /**
     * @throws SatelliteException
     */
    protected function delete(string $endpoint): void
    {
        $this->request('DELETE', $endpoint);
    }

    /**
     * Itère sur un endpoint paginé par curseur et yield chaque item, en
     * suivant automatiquement le curseur de page en page.
     *
     * Les clés sont configurables pour s'adapter au format de l'API (notation
     * « point » supportée via data_get, ex. 'data.items' ou 'meta.next') :
     *  - $itemsKey    : où lire le tableau d'items dans chaque page
     *  - $cursorKey   : où lire le curseur de la page suivante
     *  - $cursorParam : nom du paramètre de query portant le curseur
     *
     * @param  array<string, mixed>  $query  Query initiale (filtres, tri…)
     * @return Generator<int, mixed>
     *
     * @throws SatelliteException
     */
    protected function paginate(
        string $endpoint,
        array $query = [],
        string $itemsKey = 'data',
        string $cursorKey = 'next_cursor',
        string $cursorParam = 'cursor',
    ): Generator {
        $cursor = null;

        do {
            $page = $this->get(
                $endpoint,
                $cursor === null ? $query : [...$query, $cursorParam => $cursor],
            );

            foreach ((array) data_get($page, $itemsKey, []) as $item) {
                yield $item;
            }

            $cursor = data_get($page, $cursorKey);
        } while ($cursor !== null && $cursor !== '');
    }

    /**
     * Point d'entrée unique : log → requête (avec relances) → log réponse →
     * contrôle d'échec → exception typée. Tout nouveau verbe en hérite.
     *
     * @param  array<string, mixed>  $data  Query (GET) ou payload JSON (autres verbes)
     *
     * @throws SatelliteException
     */
    protected function request(string $method, string $endpoint, array $data = []): Response
    {
        $method = strtoupper($method);

        $this->log($method, $endpoint, $this->logContext($method, $data));

        // POST/PUT/PATCH envoient toujours un corps JSON (donc Content-Type:
        // application/json), même vide, comme les helpers natifs de Laravel.
        // GET et DELETE n'ajoutent rien quand il n'y a pas de paramètres.
        $options = match ($method) {
            'GET' => $data === [] ? [] : ['query' => $data],
            'DELETE' => $data === [] ? [] : ['json' => $data],
            default => ['json' => $data],
        };

        $response = $this->http->send($method, $endpoint, $options);

        $this->logResponse($method, $endpoint, $response->status());

        if ($response->failed()) {
            throw $this->failure($method, $endpoint, $response);
        }

        return $response;
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
     * Contexte de log d'une requête, payload/query masqué le cas échéant.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function logContext(string $method, array $data): array
    {
        if ($data === []) {
            return [];
        }

        $key = $method === 'GET' ? 'query' : 'payload';

        return [$key => $this->redact($data)];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $verb, string $endpoint, array $context = []): void
    {
        Log::channel($this->channel)->debug("[SatelliteClient] {$verb}", [
            'endpoint' => $endpoint,
            ...$context,
        ]);
    }

    private function logResponse(string $verb, string $endpoint, int $status): void
    {
        Log::channel($this->channel)->debug("[SatelliteClient] {$verb} response", [
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
