<?php

declare(strict_types=1);

namespace Moko\Satellite\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Vérifie la signature HMAC d'un webhook entrant.
 *
 * Utilise hash_equals (temps constant) pour éviter les timing attacks.
 * Par défaut, lit le secret depuis config('satellite.webhook_secret').
 *
 * Le reste du protocole est piloté par config('satellite.webhook.*') :
 *  - algo             : algorithme HMAC (défaut sha256)
 *  - signature_header : en-tête portant la signature (défaut X-Webhook-Signature)
 *  - signature_prefix : préfixe attendu sur la signature (ex: "sha256=", défaut "")
 *  - timestamp_header : en-tête portant l'horodatage (défaut X-Webhook-Timestamp)
 *  - tolerance        : fenêtre anti-rejeu en secondes (0 = désactivé, défaut 0)
 *
 * Anti-rejeu : si `tolerance > 0`, l'horodatage doit être présent et frais, et
 * la signature porte sur "{timestamp}.{body}" (schéma type Stripe). Sinon, la
 * signature porte sur le corps brut uniquement (rétro-compatible).
 *
 * Pour utiliser une clé de secret différente (ex: package privé) :
 *   Route::middleware(VerifyWebhookSignature::class.':api-si.webhook_secret')
 *
 * Ou via un alias dans bootstrap/app.php :
 *   $middleware->alias(['verify.webhook' => VerifyWebhookSignature::class]);
 */
final class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next, string $secretKey = 'satellite.webhook_secret'): mixed
    {
        $secret = (string) config($secretKey);

        if ($secret === '') {
            abort(500, "Webhook secret not configured ({$secretKey})");
        }

        $algo = (string) config('satellite.webhook.algo', 'sha256');
        $signatureHeader = (string) config('satellite.webhook.signature_header', 'X-Webhook-Signature');
        $signaturePrefix = (string) config('satellite.webhook.signature_prefix', '');
        $timestampHeader = (string) config('satellite.webhook.timestamp_header', 'X-Webhook-Timestamp');
        $tolerance = (int) config('satellite.webhook.tolerance', 0);

        $signature = (string) $request->header($signatureHeader);
        $payload = $request->getContent();

        if ($tolerance > 0) {
            $timestamp = (string) $request->header($timestampHeader);

            if ($timestamp === '' || ! ctype_digit($timestamp)) {
                abort(401, 'Missing or invalid webhook timestamp');
            }

            if (abs(time() - (int) $timestamp) > $tolerance) {
                abort(401, 'Webhook timestamp outside tolerance');
            }

            $payload = $timestamp.'.'.$payload;
        }

        $expected = $signaturePrefix.hash_hmac($algo, $payload, $secret);

        if (! hash_equals($expected, $signature)) {
            abort(401, 'Invalid webhook signature');
        }

        return $next($request);
    }
}
