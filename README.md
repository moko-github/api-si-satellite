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
SATELLITE_API_CONNECT_TIMEOUT=10
SATELLITE_API_RETRIES=2
SATELLITE_WEBHOOK_SECRET=un-secret-long-et-aléatoire
SATELLITE_LOG_LEVEL=debug
SATELLITE_LOG_CHANNEL=satellite
# Mettre à false uniquement si l’API utilise un certificat auto-signé (ex : qualification)
SATELLITE_VERIFY_SSL=true
```

| Variable | Défaut | Rôle |
|---|---|---|
| `SATELLITE_API_URL` | — | URL de base de l’API distante |
| `SATELLITE_API_TOKEN` | — | Token Bearer pour l’authentification |
| `SATELLITE_API_TIMEOUT` | `10` | Timeout HTTP global en secondes |
| `SATELLITE_API_CONNECT_TIMEOUT` | `10` | Timeout d’établissement de la connexion TCP/TLS (`0` = pas de limite) |
| `SATELLITE_API_RETRIES` | `2` | Nombre de relances en cas d’échec de connexion (`0` = aucune). Avec backoff. |
| `SATELLITE_API_RETRY_DELAY` | `200` | Délai initial entre tentatives (ms) |
| `SATELLITE_WEBHOOK_SECRET` | — | Secret HMAC SHA-256 pour vérifier les webhooks (généré automatiquement par `satellite:install`) |
| `SATELLITE_LOG_LEVEL` | `debug` | Niveau de log du canal `satellite` dans `config/logging.php` |
| `SATELLITE_LOG_CHANNEL` | `satellite` | Canal Laravel à utiliser pour les logs du client HTTP |
| `SATELLITE_VERIFY_SSL` | `true` | Vérification du certificat SSL. Mettre à `false` pour les environnements avec certificats auto-signés (ex : qualification) |

> `SATELLITE_LOG_LEVEL` contrôle **à quel niveau** on logue (debug, info, warning…).
> `SATELLITE_LOG_CHANNEL` contrôle **dans quel canal** on logue.
> Les deux sont complémentaires : le canal `satellite` est créé dans `config/logging.php` par `satellite:install`, avec `SATELLITE_LOG_LEVEL` comme niveau minimum.

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
            verifySSL:  (bool)   config('my-api.verify_ssl', true),
            retries:    (int)    config('my-api.retries', 2),
        );
    }

    public function getResource(int $id): MyResourceDTO
    {
        return MyResourceDTO::fromArray($this->get("/api/v1/resources/{$id}"));
    }
}
```

#### Verbes disponibles

`get()`, `post()`, `put()`, `patch()`, `delete()` partagent tous le même socle
(relances, masquage des logs, gestion d’erreur typée) via un `request()` interne.

- **`put()`** = remplacement **complet** de la ressource (le payload représente l’objet entier).
- **`patch()`** = mise à jour **partielle** (seuls les champs fournis changent) :

```php
public function renameResource(int $id, string $name): MyResourceDTO
{
    return MyResourceDTO::fromArray(
        $this->patch("/api/v1/resources/{$id}", ['name' => $name])
    );
}
```

#### Pagination par curseur

`paginate()` suit automatiquement le curseur et `yield` chaque item (itération
paresseuse, adaptée aux gros volumes). Les clés sont configurables selon le
format de l’API (notation « point » supportée) :

```php
/** @return iterable<MyResourceDTO> */
public function allResources(): iterable
{
    foreach ($this->paginate('/api/v1/resources', itemsKey: 'data', cursorKey: 'meta.next_cursor') as $item) {
        yield MyResourceDTO::fromArray($item);
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

#### Personnaliser le protocole

Le protocole de vérification est piloté par `config('satellite.webhook')` :

| Clé / env | Défaut | Rôle |
|---|---|---|
| `SATELLITE_WEBHOOK_ALGO` | `sha256` | Algorithme HMAC |
| `SATELLITE_WEBHOOK_SIGNATURE_HEADER` | `X-Webhook-Signature` | En-tête portant la signature |
| `SATELLITE_WEBHOOK_SIGNATURE_PREFIX` | _(vide)_ | Préfixe attendu sur la signature (ex : `sha256=` à la GitHub) |
| `SATELLITE_WEBHOOK_TIMESTAMP_HEADER` | `X-Webhook-Timestamp` | En-tête portant l’horodatage (anti-rejeu) |
| `SATELLITE_WEBHOOK_TOLERANCE` | `0` | Fenêtre anti-rejeu en secondes (`0` = désactivé) |

**Anti-rejeu (replay).** Tant que `SATELLITE_WEBHOOK_TOLERANCE` vaut `0`, la
signature porte sur le corps brut (comportement par défaut). Dès que la
tolérance est positive, l’en-tête d’horodatage devient **obligatoire**, sa
fraîcheur est vérifiée (`|now − timestamp| ≤ tolérance`) et la signature doit
porter sur `"{timestamp}.{body}"` (schéma type Stripe) :

```php
$signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
```

### 3. Utiliser les stubs publiés

Après `satellite:install`, deux stubs sont disponibles dans `stubs/satellite/` :

| Stub | Usage |
|---|---|
| `WebhookController.stub` | Contrôleur de réception des webhooks |
| `SyncJob.stub` | Job de synchronisation cursor-based |

---

## Gérer les erreurs

`SatelliteException` expose `statusCode`, `endpoint`, `errors` et `body` (corps
brut de la réponse, utile pour les erreurs non-JSON : 502 HTML, timeouts…) :

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

> ⚠️ `$e->body` contient la réponse **brute, non masquée**. Contrairement aux
> `query`/`payload` des requêtes (qui passent par la redaction), un corps de
> réponse peut contenir des secrets/PII : ne le loggue pas tel quel sur un canal
> non maîtrisé.

---

## Logs & données sensibles

Le client logue les requêtes (`query`, `payload`) sur le canal configuré, au
niveau `debug`. Les clés sensibles sont **masquées automatiquement**
(`[REDACTED]`) avant écriture : `password`, `token`, `secret`, `authorization`,
`api_key`, `access_token`, `refresh_token`… (comparaison insensible à la casse
et par sous-chaîne, récursive).

Pour ajuster la liste, surcharge `$redactKeys` dans ta sous-classe :

```php
final class MyApiClient extends SatelliteClient
{
    protected array $redactKeys = ['password', 'token', 'ssn', 'iban'];
}
```

---

## Relances (retry)

Les requêtes sont automatiquement relancées sur les échecs **transitoires**,
avec backoff. Par défaut : 2 relances, délai initial 200 ms. Configurable via
`SATELLITE_API_RETRIES` / `SATELLITE_API_RETRY_DELAY` (ou les paramètres
`retries` / `retryDelay` du constructeur). Mettre `SATELLITE_API_RETRIES=0`
désactive les relances.

Sont rejoués :
- les **erreurs de connexion** (DNS, refus, timeout réseau) ;
- le **throttling** (`429`) ;
- les **erreurs serveur** (`5xx`).

Ne sont **jamais** rejoués : les `4xx` déterministes (`422`, `404`, `401`…),
qui échoueraient à l’identique.

> ⚠️ Les `5xx` étant rejoués, un `POST`/`PUT` **non idempotent** peut être
> exécuté plusieurs fois si le serveur a traité la requête avant de renvoyer
> une erreur. Pour ces écritures, mettez `retries: 0` ou prévoyez une clé
> d’idempotence côté API.

---

## Tester la connectivité

La commande `satellite:ping` vérifie que l'application peut joindre l'API distante.

```bash
# Endpoint par défaut (/health)
php artisan satellite:ping

# Endpoint personnalisé (ex : api-si)
php artisan satellite:ping --endpoint=/api/v1/health

# Surcharger l'URL (tester un environnement sans modifier .env)
php artisan satellite:ping --endpoint=/api/v1/health --url=https://api-qualification.example.com

# Appel sans token Bearer (endpoint vraiment public)
php artisan satellite:ping --endpoint=/api/v1/health --no-token
```

Exemple de sortie en succès :

```
Satellite Ping

  ● GET https://api.example.com/api/v1/health … 200 OK (42ms)

  {"status":"ok","version":"1.2.3"}
```

Exemple de sortie en erreur :

```
Satellite Ping

  ● GET https://api.example.com/api/v1/health … 500 (120ms)
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
