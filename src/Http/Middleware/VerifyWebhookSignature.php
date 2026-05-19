<?php

declare(strict_types=1);

namespace Moko\Satellite\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Vérifie la signature HMAC SHA-256 d'un webhook entrant.
 *
 * Utilise hash_equals (temps constant) pour éviter les timing attacks.
 * Par défaut, lit le secret depuis config('satellite.webhook_secret').
 *
 * Pour utiliser une clé de config différente (ex: package privé) :
 *   Route::middleware(VerifyWebhookSignature::class.':api-si.webhook_secret')
 *
 * Ou via un alias dans bootstrap/app.php :
 *   $middleware->alias(['verify.webhook' => VerifyWebhookSignature::class]);
 */
final class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next, string $configKey = 'satellite.webhook_secret'): mixed
    {
        $signature = (string) $request->header('X-Webhook-Signature');
        $secret    = (string) config($configKey);

        if ($secret === '') {
            abort(500, "Webhook secret not configured ({$configKey})");
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            abort(401, 'Invalid webhook signature');
        }

        return $next($request);
    }
}
