# Roadmap d'amélioration — `moko-github/api-si-satellite`

Suivi des chantiers identifiés lors de la revue du package. Les cases cochées
sont livrées ; les autres restent à planifier.

> Légende priorité : 🔴 haute · 🟠 moyenne · 🟢 basse

---

## Lot 1 — « le plus rentable » ✅ livré

- [x] 🔴 **CI GitHub Actions** — matrice PHP 8.2/8.3/8.4 × Testbench 9/10 (Laravel 11/12) pour les tests + job qualité (Pint + PHPStan). Voir `.github/workflows/ci.yml`.
- [x] 🔴 **Fichier `LICENSE`** — MIT.
- [x] 🔴 **Analyse statique + style** — Larastan (PHPStan niveau 6, `phpstan.neon.dist`) et Laravel Pint (`pint.json`), scripts Composer `test`/`analyse`/`lint`.
- [x] 🔴 **Redaction des logs** — `SatelliteClient::redact()` masque récursivement les clés sensibles (`password`, `token`, `secret`, `authorization`, `api_key`, `access_token`…). Liste surchargeable via `$redactKeys`.
- [x] 🟠 **Retry HTTP** — `->retry()` sur erreurs de connexion, configurable via `SATELLITE_API_RETRIES` (défaut 2) et `SATELLITE_API_RETRY_DELAY` (défaut 200 ms).
- [x] 🟠 **Corps de réponse brut dans `SatelliteException`** — propriété `body` (+ `previous`), capturée lors des échecs. *(remontée du backlog, livrée avec ce lot.)*

---

## Robustesse / résilience ✅ livré

- [x] 🟠 **Factoriser `request()`** — point d'entrée unique (log → requête → log réponse → check `failed()` → throw) ; tous les verbes en héritent.
- [x] 🟢 **Méthode `PATCH`** — ajoutée (mise à jour partielle).
- [x] 🟢 **`connectTimeout` distinct** — `SATELLITE_API_CONNECT_TIMEOUT` (défaut 10 s).
- [ ] 🟢 **`DELETE` renvoyant un corps** — actuellement `void`, jette la réponse ; gérer les API qui répondent du contenu. *(non retenu pour l'instant : éviterait de casser la signature `: void` ; à rouvrir si un besoin concret apparaît.)*

---

## Sécurité

- [x] 🟠 **Anti-rejeu webhook** — `SATELLITE_WEBHOOK_TOLERANCE` (opt-in) : horodatage obligatoire + fraîcheur vérifiée, signature sur `{timestamp}.{body}` (schéma Stripe).
- [x] 🟢 **En-tête et schéma de signature configurables** — `config('satellite.webhook')` : `signature_header`, `signature_prefix` (ex : `sha256=`), `timestamp_header`, `algo`.

---

## Cohérence / qualité ✅ livré

- [x] 🟠 **Utiliser `config('satellite.log_channel')`** — `SatelliteClient` résout son canal depuis la config quand `logChannel` est omis ; le ping en hérite.
- [x] 🟢 **`SATELLITE_LOG_CHANNEL` dans l'install** — ajouté aux blocs `.env` / `.env.example`.
- [x] 🟢 **`configureLoggingChannel()` plus robuste** — avertit si `config/logging.php` est absent ou si le marqueur `'emergency' => [` est introuvable.
- [x] 🟢 **Tests de `SatelliteInstallCommand`** — happy path, marqueur absent, annulation.
- [x] 🟢 **Scripts Composer** — `test`, `lint`, `analyse` (livré au lot 1).
- [x] 🟢 **`CHANGELOG.md`** — créé (format Keep a Changelog).
- [x] 🟢 **`.editorconfig`** — créé.

---

## Idées / évolutions

- [x] 🟢 **Helper de pagination cursor-based** — `SatelliteClient::paginate()` suit le curseur et yield chaque item (clés `itemsKey`/`cursorKey`/`cursorParam` configurables, notation « point »). Stub `SyncJob` mis à jour.
