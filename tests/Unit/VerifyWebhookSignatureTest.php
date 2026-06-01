<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Moko\Satellite\Http\Middleware\VerifyWebhookSignature;

function makeRequest(string $body, string $signature): Request
{
    $request = Request::create('/webhook', 'POST', content: $body);
    $request->headers->set('X-Webhook-Signature', $signature);

    return $request;
}

function validSignature(string $body, string $secret): string
{
    return hash_hmac('sha256', $body, $secret);
}

it('passes through when signature is valid', function () {
    $body      = '{"event":"user.created"}';
    $secret    = config('satellite.webhook_secret');
    $signature = validSignature($body, $secret);

    $request    = makeRequest($body, $signature);
    $middleware = new VerifyWebhookSignature;

    $called   = false;
    $response = $middleware->handle($request, function () use (&$called) {
        $called = true;

        return response('ok');
    });

    expect($called)->toBeTrue();
});

it('aborts 401 when signature is invalid', function () {
    $body    = '{"event":"user.created"}';
    $request = makeRequest($body, 'bad-signature');

    $middleware = new VerifyWebhookSignature;

    $middleware->handle($request, fn () => response('ok'));
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);

it('aborts 500 when secret is not configured', function () {
    config()->set('satellite.webhook_secret', '');

    $request    = makeRequest('{}', 'any');
    $middleware = new VerifyWebhookSignature;

    $middleware->handle($request, fn () => response('ok'));
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);

it('uses custom config key when provided', function () {
    config()->set('my-package.webhook_secret', 'custom-secret');

    $body      = '{"event":"ping"}';
    $signature = validSignature($body, 'custom-secret');
    $request   = makeRequest($body, $signature);

    $middleware = new VerifyWebhookSignature;

    $called   = false;
    $middleware->handle($request, function () use (&$called) {
        $called = true;

        return response('ok');
    }, 'my-package.webhook_secret');

    expect($called)->toBeTrue();
});
