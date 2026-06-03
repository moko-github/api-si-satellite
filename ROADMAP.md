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

- [ ] 🟠 **Anti-rejeu webhook** — signer/valider un timestamp avec fenêtre de tolérance (à la Stripe/GitHub), le HMAC seul ne protège pas du replay.
- [ ] 🟢 **En-tête et schéma de signature configurables** — `X-Webhook-Signature` est codé en dur ; supporter d'autres en-têtes et le préfixe `sha256=…`.

---

## Cohérence / qualité

- [ ] 🟠 **Utiliser `config('satellite.log_channel')`** — la config existe mais n'est jamais lue ; `SatelliteClient` et la commande `ping` codent `'stack'` en dur.
- [ ] 🟢 **`SATELLITE_LOG_CHANNEL` dans l'install** — documenté dans le README mais non écrit par `appendEnvVariables()`.
- [ ] 🟢 **`configureLoggingChannel()` plus robuste** — avertir si le marqueur `'emergency' => [` est introuvable (sinon le canal n'est pas ajouté silencieusement).
- [ ] 🟢 **Tests de `SatelliteInstallCommand`** — non couverte aujourd'hui.
- [ ] 🟢 **Scripts Composer** — `test`, `lint`, `analyse` pour le confort de maintenance.
- [ ] 🟢 **`CHANGELOG.md`** — historique des versions.
- [ ] 🟢 **`.editorconfig`** — cohérence d'édition.

---

## Idées / évolutions

- [ ] 🟢 **Helper de pagination cursor-based** — le stub `SyncJob` y fait référence mais le client n'offre aucun helper.
