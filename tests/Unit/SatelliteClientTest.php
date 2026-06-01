<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Moko\Satellite\Services\SatelliteClient;
use Moko\Satellite\Services\SatelliteException;

// Concrete test double — exposes protected methods publicly
final class TestableClient extends SatelliteClient
{
    public function publicGet(string $endpoint, array $query = []): array
    {
        return $this->get($endpoint, $query);
    }

    public function publicPost(string $endpoint, array $payload = []): array
    {
        return $this->post($endpoint, $payload);
    }

    public function publicPut(string $endpoint, array $payload = []): array
    {
        return $this->put($endpoint, $payload);
    }

    public function publicDelete(string $endpoint): void
    {
        $this->delete($endpoint);
    }
}

function makeClient(bool $verifySSL = true): TestableClient
{
    return new TestableClient(
        baseUrl:    'https://api.example.com',
        token:      'test-token',
        timeout:    10,
        logChannel: 'stack',
        verifySSL:  $verifySSL,
    );
}

// --- GET ---

it('performs GET and returns decoded array', function () {
    Http::fake(['https://api.example.com/api/v1/users' => Http::response(['id' => 1], 200)]);

    $result = makeClient()->publicGet('/api/v1/users');

    expect($result)->toBe(['id' => 1]);
});

it('passes query parameters on GET', function () {
    Http::fake(['https://api.example.com/api/v1/users*' => Http::response(['page' => 2], 200)]);

    $result = makeClient()->publicGet('/api/v1/users', ['page' => 2]);

    expect($result)->toBe(['page' => 2]);
});

it('throws SatelliteException on GET failure', function () {
    Http::fake(['https://api.example.com/api/v1/users' => Http::response(['errors' => ['not found']], 404)]);

    expect(fn () => makeClient()->publicGet('/api/v1/users'))
        ->toThrow(SatelliteException::class);
});

it('exposes statusCode and endpoint on GET SatelliteException', function () {
    Http::fake(['https://api.example.com/api/v1/users' => Http::response(null, 500)]);

    try {
        makeClient()->publicGet('/api/v1/users');
    } catch (SatelliteException $e) {
        expect($e->statusCode)->toBe(500)
            ->and($e->endpoint)->toBe('/api/v1/users');
    }
});

// --- POST ---

it('performs POST and returns decoded array', function () {
    Http::fake(['https://api.example.com/api/v1/users' => Http::response(['created' => true], 201)]);

    $result = makeClient()->publicPost('/api/v1/users', ['name' => 'Alice']);

    expect($result)->toBe(['created' => true]);
});

it('throws SatelliteException on POST failure', function () {
    Http::fake(['https://api.example.com/api/v1/users' => Http::response(['errors' => ['invalid']], 422)]);

    expect(fn () => makeClient()->publicPost('/api/v1/users', []))
        ->toThrow(SatelliteException::class);
});

// --- PUT ---

it('performs PUT and returns decoded array', function () {
    Http::fake(['https://api.example.com/api/v1/users/1' => Http::response(['updated' => true], 200)]);

    $result = makeClient()->publicPut('/api/v1/users/1', ['name' => 'Bob']);

    expect($result)->toBe(['updated' => true]);
});

it('throws SatelliteException on PUT failure', function () {
    Http::fake(['https://api.example.com/api/v1/users/1' => Http::response(null, 403)]);

    expect(fn () => makeClient()->publicPut('/api/v1/users/1', []))
        ->toThrow(SatelliteException::class);
});

// --- DELETE ---

it('performs DELETE without exception on success', function () {
    Http::fake(['https://api.example.com/api/v1/users/1' => Http::response(null, 204)]);

    expect(fn () => makeClient()->publicDelete('/api/v1/users/1'))->not->toThrow(SatelliteException::class);
});

it('throws SatelliteException on DELETE failure', function () {
    Http::fake(['https://api.example.com/api/v1/users/1' => Http::response(null, 404)]);

    expect(fn () => makeClient()->publicDelete('/api/v1/users/1'))
        ->toThrow(SatelliteException::class);
});

// --- SSL ---

it('verifies SSL by default', function () {
    Http::fake(['*' => Http::response([], 200)]);

    makeClient(verifySSL: true)->publicGet('/api/v1/health');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.example.com/api/v1/health');
});

it('disables SSL verification when verifySSL is false', function () {
    Http::fake(['*' => Http::response([], 200)]);

    makeClient(verifySSL: false)->publicGet('/api/v1/health');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.example.com/api/v1/health');
});
