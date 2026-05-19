# moko-github/api-si-satellite

Infrastructure générique pour satellites Laravel connectés à une API externe.

Fournit :
- **`SatelliteClient`** — classe abstraite HTTP avec `get()`, `post()`, `put()`, `delete()`, logging intégré et gestion d’erreurs typée
- **`VerifyWebhookSignature`** — middleware HMAC SHA-256 timing-safe pour les webhooks entrants
- **`SatelliteInstallCommand`** — commande `satellite:install` pour publier config, stubs et configurer le canal de log

---

## Prérequis

- PHP 8.2+
- Laravel 11 ou 12

---

## Installation

```bash
composer require moko-github/api-si-satellite
```

### Publier les fichiers

```bash
php artisan satellite:install
```

Ou manuellement :

```bash
php artisan vendor:publish --tag=satellite-config
php artisan vendor:publish --tag=satellite-stubs
```

### Variables d’environnement

```dotenv
SATELLITE_API_URL=https://api.example.com
SATELLITE_API_TOKEN=ton-token
SATELLITE_API_TIMEOUT=10
SATELLITE_WEBHOOK_SECRET=un-secret-long-et-aléatoire
SATELLITE_LOG_CHANNEL=satellite
```

---

## Utilisation

### 1. Créer un client HTTP spécifique

Étendre `SatelliteClient` dans le package privé de l’application :

```php
use Moko\\Satellite\\Services\\SatelliteClient;

final class MyApiClient extends SatelliteClient
{
    public function __construct()
    {
        parent::__construct(
            baseUrl:    (string) config('my-api.url'),
            token:      (string) config('my-api.token'),
            timeout:    (int)    config('my-api.timeout', 10),
            logChannel: 'my-api',
        );
    }

    public function getResource(int $id): MyResourceDTO
    {
        return MyResourceDTO::fromArray($this->get("/api/v1/resources/{$id}"));
    }
}
```

### 2. Protéger une route webhook

```php
use Moko\\Satellite\\Http\\Middleware\\VerifyWebhookSignature;

// Clé par défaut : config('satellite.webhook_secret')
Route::post('/webhooks/my-api', MyWebhookController::class)
    ->middleware(VerifyWebhookSignature::class);

// Clé personnalisée (package privé avec sa propre config) :
Route::post('/webhooks/my-api', MyWebhookController::class)
    ->middleware(VerifyWebhookSignature::class.':my-api.webhook_secret');
```

Le middleware lit l’en-tête `X-Webhook-Signature` et la compare via `hash_equals` (temps constant).

### 3. Utiliser les stubs publiés

Après `satellite:install`, deux stubs sont disponibles dans `stubs/satellite/` :

| Stub | Usage |
|---|---|
| `WebhookController.stub` | Contrôleur de réception des webhooks |
| `SyncJob.stub` | Job de synchronisation cursor-based |

---

## Gérer les erreurs

`SatelliteException` expose `statusCode`, `endpoint` et `errors` :

```php
use Moko\\Satellite\\Services\\SatelliteException;

try {
    $data = $client->getResource(42);
} catch (SatelliteException $e) {
    Log::error('API error', [
        'status'   => $e->statusCode,
        'endpoint' => $e->endpoint,
        'errors'   => $e->errors,
    ]);
}
```

---

## Structure

```
src/
├── SatelliteServiceProvider.php
├── Console/Commands/
│   └── SatelliteInstallCommand.php
├── Http/Middleware/
│   └── VerifyWebhookSignature.php
└── Services/
    ├── SatelliteClient.php
    └── SatelliteException.php
config/
└── satellite.php
stubs/
├── SyncJob.stub
└── WebhookController.stub
```

---

## Licence

MIT
