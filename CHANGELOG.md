# Changelog

Toutes les évolutions notables de ce package sont documentées ici.

Le format s'inspire de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/)
et le projet suit le [versionnage sémantique](https://semver.org/lang/fr/).

## [Non publié]

### Ajouté
- Intégration continue GitHub Actions : matrice PHP 8.2/8.3/8.4 × Testbench 9/10
  (Laravel 11/12) pour les tests, et job qualité (Pint + PHPStan).
- Analyse statique Larastan (PHPStan niveau 6) et style Laravel Pint, avec
  scripts Composer `test`, `analyse`, `lint`.
- Fichier `LICENSE` (MIT), `CHANGELOG.md`, `.editorconfig` et `ROADMAP.md`.
- `SatelliteClient` : relances HTTP sur erreurs de connexion, configurables via
  `SATELLITE_API_RETRIES` (défaut 2) et `SATELLITE_API_RETRY_DELAY` (défaut 200 ms).
- `SatelliteClient` : masquage automatique des clés sensibles dans les logs
  (`password`, `token`, `secret`, `authorization`…), surchargeable via `$redactKeys`.
- `SatelliteException` : nouvelle propriété `body` (corps brut de la réponse) et
  prise en charge d'une exception précédente (`previous`).
- Middleware webhook : en-tête, préfixe (`sha256=`…), algorithme et en-tête
  d'horodatage configurables via `config('satellite.webhook')`.
- Middleware webhook : protection anti-rejeu optionnelle via
  `SATELLITE_WEBHOOK_TOLERANCE` (horodatage requis et signature sur
  `{timestamp}.{body}`, schéma type Stripe).
- Variables `SATELLITE_LOG_CHANNEL` ajoutées aux blocs `.env` de `satellite:install`.
- `SatelliteClient::patch()` pour les mises à jour partielles de ressource.
- `SatelliteClient::paginate()` : itération paresseuse sur un endpoint paginé par
  curseur (clés `itemsKey`/`cursorKey`/`cursorParam` configurables).
- Timeout de connexion distinct via `SATELLITE_API_CONNECT_TIMEOUT` (défaut 10 s).

### Modifié
- `SatelliteClient` : tous les verbes (`get`/`post`/`put`/`patch`/`delete`)
  passent par un `request()` interne unique (log, relances, gestion d'erreur).
- `SatelliteClient` résout désormais son canal de log depuis
  `config('satellite.log_channel')` lorsque `logChannel` n'est pas fourni.
- `satellite:install` avertit explicitement lorsque `config/logging.php` est
  absent ou que le marqueur d'insertion est introuvable, au lieu d'échouer
  silencieusement.

### Sécurité
- Les secrets et données sensibles ne sont plus écrits en clair dans les logs du
  client HTTP.
