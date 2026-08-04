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
SATELLITE_LOG_LEVEL=debug
SATELLITE_LOG_CHANNEL=satellite
# Mettre à false uniquement si l’API utilise un certificat auto-signé (ex : qualification)
SATELLITE_VERIFY_SSL=true
```

| Variable | Défaut | Rôle |
|---|---|---|
| `SATELLITE_API_URL` | — | URL de base de l’API distante |
| `SATELLITE_API_TOKEN` | — | Token Bearer pour l’authentification |
| `SATELLITE_API_TIMEOUT` | `10` | Timeout HTTP en secondes |
| `SATELLITE_WEBHOOK_SECRET` | — | Secret HMAC SHA-256 pour vérifier les webhooks (généré automatiquement par `satellite:install`) |
| `SATELLITE_LOG_LEVEL` | `debug` | Niveau de log du canal `satellite` dans `config/logging.php` |
| `SATELLITE_LOG_CHANNEL` | `satellite` | Canal Laravel à utiliser pour les logs du client HTTP |
| `SATELLITE_VERIFY_SSL` | `true` | Vérification du certificat SSL. Mettre à `false` pour les environnements avec certificats auto-signés (ex : qualification) |

> `SATELLITE_LOG_LEVEL` contrôle **à quel niveau** on logue (debug, info, warning…).
> `SATELLITE_LOG_CHANNEL` contrôle **dans quel canal** on logue.
> Les deux sont complémentaires : le canal `satellite` est créé dans `config/logging.php` par `satellite:install`, avec `SATELLITE_LOG_LEVEL` comme niveau minimum.

### Dépannage : le canal de log `satellite` n’a pas été créé

`satellite:install` insère le canal `satellite` dans `config/logging.php` en
l’ajoutant au tableau `'channels'`. Si votre fichier `config/logging.php` est
fortement personnalisé (tableau `'channels'` absent, par exemple), la commande
**ne modifie pas le fichier en silence** : elle affiche un avertissement et le
snippet à coller manuellement.

La commande est **idempotente** : vous pouvez la relancer sans risque.

```bash
php artisan satellite:install
```

- Si le canal existe déjà : `Canal de log 'satellite' déjà présent…`
- Si le canal manque et peut être ajouté : il est inséré et vérifié après écriture.
- Si la commande ne peut pas l’ajouter (fichier atypique, droits d’écriture) :
  un **avertissement** s’affiche avec les instructions ci-dessous.

Pour l’ajouter manuellement, collez ce bloc dans le tableau `'channels'` de
`config/logging.php` :

```php
'satellite' => [
    'driver' => 'daily',
    'path'   => storage_path('logs/satellite.log'),
    'level'  => env('SATELLITE_LOG_LEVEL', 'debug'),
    'days'   => 14,
],
```

> Si vous préférez réutiliser un canal existant plutôt que d’en créer un, pointez
> simplement `SATELLITE_LOG_CHANNEL` vers ce canal (ex : `SATELLITE_LOG_CHANNEL=stack`).

Vérifiez ensuite que tout est en place avec `php artisan satellite:ping`
(voir [Tester la connectivité](#tester-la-connectivité)).

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

### API injoignable

Une panne de transport — DNS introuvable, connexion refusée, délai dépassé — ne produit
**aucune réponse HTTP**. Le client la traduit en `SatelliteConnectionException`, qui hérite de
`SatelliteException` : le `catch` ci-dessus la couvre déjà, sans rien changer.

C'est délibéré. Un satellite qui rattrape `SatelliteException` le fait presque toujours pour se
dégrader quand l'API est indisponible ; laisser filer la `ConnectionException` casserait l'écran
précisément dans ce cas-là.

`statusCode` vaut alors `0` — l'API n'a rien répondu, il n'y a pas de code à rapporter, et `0` ne
peut pas être confondu avec un 503 émis par le serveur. Le motif réel reste dans `getPrevious()`.

```php
use Moko\\Satellite\\Services\\SatelliteConnectionException;

try {
    $data = $client->getResource(42);
} catch (SatelliteConnectionException $e) {
    // API injoignable — dégrader l'affichage, réessayer plus tard
    Log::warning('API unreachable', ['reason' => $e->getPrevious()?->getMessage()]);
} catch (SatelliteException $e) {
    // L'API a répondu, mais en erreur
}
```

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
    ├── SatelliteConnectionException.php
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
