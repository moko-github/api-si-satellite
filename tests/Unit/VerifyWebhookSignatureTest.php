<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Moko\Satellite\Http\Middleware\VerifyWebhookSignature;
use Symfony\Component\HttpKernel\Exception\HttpException;

function makeRequest(string $body, string $signature, string $header = 'X-Webhook-Signature', ?string $timestamp = null, string $timestampHeader = 'X-Webhook-Timestamp'): Request
{
    $request = Request::create('/webhook', 'POST', content: $body);
    $request->headers->set($header, $signature);

    if ($timestamp !== null) {
        $request->headers->set($timestampHeader, $timestamp);
    }

    return $request;
}

function validSignature(string $body, string $secret): string
{
    return hash_hmac('sha256', $body, $secret);
}

it('passes through when signature is valid', function () {
    $body = '{"event":"user.created"}';
    $secret = config('satellite.webhook_secret');
    $signature = validSignature($body, $secret);

    $request = makeRequest($body, $signature);
    $middleware = new VerifyWebhookSignature;

    $called = false;
    $response = $middleware->handle($request, function () use (&$called) {
        $called = true;

        return response('ok');
    });

    expect($called)->toBeTrue();
});

it('aborts 401 when signature is invalid', function () {
    $body = '{"event":"user.created"}';
    $request = makeRequest($body, 'bad-signature');

    $middleware = new VerifyWebhookSignature;

    $middleware->handle($request, fn () => response('ok'));
})->throws(HttpException::class);

it('aborts 500 when secret is not configured', function () {
    config()->set('satellite.webhook_secret', '');

    $request = makeRequest('{}', 'any');
    $middleware = new VerifyWebhookSignature;

    $middleware->handle($request, fn () => response('ok'));
})->throws(HttpException::class);

it('uses custom config key when provided', function () {
    config()->set('my-package.webhook_secret', 'custom-secret');

    $body = '{"event":"ping"}';
    $signature = validSignature($body, 'custom-secret');
    $request = makeRequest($body, $signature);

    $middleware = new VerifyWebhookSignature;

    $called = false;
    $middleware->handle($request, function () use (&$called) {
        $called = true;

        return response('ok');
    }, 'my-package.webhook_secret');

    expect($called)->toBeTrue();
});

// --- Signature header / prefix configurables ---

it('reads the signature from a custom header', function () {
    config()->set('satellite.webhook.signature_header', 'X-Hub-Signature');

    $body = '{"event":"ping"}';
    $signature = validSignature($body, config('satellite.webhook_secret'));
    $request = makeRequest($body, $signature, header: 'X-Hub-Signature');

    $called = false;
    (new VerifyWebhookSignature)->handle($request, function () use (&$called) {
        $called = true;

        return response('ok');
    });

    expect($called)->toBeTrue();
});

it('accepts a configured signature prefix', function () {
    config()->set('satellite.webhook.signature_prefix', 'sha256=');

    $body = '{"event":"ping"}';
    $signature = 'sha256='.validSignature($body, config('satellite.webhook_secret'));
    $request = makeRequest($body, $signature);

    $called = false;
    (new VerifyWebhookSignature)->handle($request, function () use (&$called) {
        $called = true;

        return response('ok');
    });

    expect($called)->toBeTrue();
});

it('rejects a signature missing the configured prefix', function () {
    config()->set('satellite.webhook.signature_prefix', 'sha256=');

    $body = '{"event":"ping"}';
    $signature = validSignature($body, config('satellite.webhook_secret')); // sans préfixe
    $request = makeRequest($body, $signature);

    (new VerifyWebhookSignature)->handle($request, fn () => response('ok'));
})->throws(HttpException::class);

// --- Anti-rejeu (tolerance > 0) ---

it('passes when timestamp is fresh and signature covers timestamp.body', function () {
    config()->set('satellite.webhook.tolerance', 300);

    $body = '{"event":"user.created"}';
    $timestamp = (string) time();
    $secret = config('satellite.webhook_secret');
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    $request = makeRequest($body, $signature, timestamp: $timestamp);

    $called = false;
    (new VerifyWebhookSignature)->handle($request, function () use (&$called) {
        $called = true;

        return response('ok');
    });

    expect($called)->toBeTrue();
});

it('aborts when the timestamp is outside the tolerance (replay)', function () {
    config()->set('satellite.webhook.tolerance', 300);

    $body = '{"event":"user.created"}';
    $timestamp = (string) (time() - 3600); // 1h dans le passé
    $secret = config('satellite.webhook_secret');
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    $request = makeRequest($body, $signature, timestamp: $timestamp);

    (new VerifyWebhookSignature)->handle($request, fn () => response('ok'));
})->throws(HttpException::class);

it('aborts when the timestamp header is missing and tolerance is enabled', function () {
    config()->set('satellite.webhook.tolerance', 300);

    $body = '{"event":"user.created"}';
    $signature = hash_hmac('sha256', time().'.'.$body, config('satellite.webhook_secret'));
    $request = makeRequest($body, $signature); // pas d'horodatage

    (new VerifyWebhookSignature)->handle($request, fn () => response('ok'));
})->throws(HttpException::class);
