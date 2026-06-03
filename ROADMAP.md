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

## Robustesse / résilience

- [ ] 🟠 **Factoriser `request()`** — centraliser le pattern requête → check `failed()` → throw, partagé par toutes les méthodes (le logging est déjà factorisé via `log()`/`logResponse()`).
- [ ] 🟢 **Méthode `PATCH`** — absente (GET/POST/PUT/DELETE seulement).
- [ ] 🟢 **`DELETE` renvoyant un corps** — actuellement `void`, jette la réponse ; gérer les API qui répondent du contenu.
- [ ] 🟢 **`connectTimeout` distinct** du timeout global.

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

- [ ] 🟢 **Helper de pagination cursor-based** — le stub `SyncJob` y fait référence mais le client n'offre aucun helper.
