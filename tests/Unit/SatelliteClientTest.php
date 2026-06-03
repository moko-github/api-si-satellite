<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
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

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function publicRedact(array $data): array
    {
        return $this->redact($data);
    }
}

function makeClient(bool $verifySSL = true, int $retries = 0): TestableClient
{
    return new TestableClient(
        baseUrl: 'https://api.example.com',
        token: 'test-token',
        timeout: 10,
        logChannel: 'stack',
        verifySSL: $verifySSL,
        retries: $retries,
        retryDelay: 1,
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

// --- Redaction ---

it('redacts sensitive keys in nested arrays', function () {
    $redacted = makeClient()->publicRedact([
        'username' => 'alice',
        'password' => 's3cr3t',
        'profile' => [
            'api_token' => 'abc123',
            'first_name' => 'Alice',
        ],
    ]);

    expect($redacted)->toBe([
        'username' => 'alice',
        'password' => '[REDACTED]',
        'profile' => [
            'api_token' => '[REDACTED]',
            'first_name' => 'Alice',
        ],
    ]);
});

it('matches sensitive keys case-insensitively and by substring', function () {
    $redacted = makeClient()->publicRedact([
        'Authorization' => 'Bearer x',
        'user_secret' => 'y',
        'name' => 'keep',
    ]);

    expect($redacted)->toBe([
        'Authorization' => '[REDACTED]',
        'user_secret' => '[REDACTED]',
        'name' => 'keep',
    ]);
});

// --- Exception body ---

it('captures the raw response body on failure', function () {
    Http::fake(['https://api.example.com/api/v1/users' => Http::response('<html>Bad Gateway</html>', 502)]);

    try {
        makeClient()->publicGet('/api/v1/users');
    } catch (SatelliteException $e) {
        expect($e->statusCode)->toBe(502)
            ->and($e->body)->toBe('<html>Bad Gateway</html>')
            ->and($e->errors)->toBe([]);
    }
});

// --- Retry ---

it('retries on connection failure then succeeds', function () {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        if ($attempts < 2) {
            throw new ConnectionException('Connection refused');
        }

        return Http::response(['ok' => true], 200);
    });

    $result = makeClient(retries: 2)->publicGet('/api/v1/health');

    expect($result)->toBe(['ok' => true])
        ->and($attempts)->toBe(2);
});

it('does not retry when retries is zero', function () {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('Connection refused');
    });

    expect(fn () => makeClient(retries: 0)->publicGet('/api/v1/health'))
        ->toThrow(ConnectionException::class);

    expect($attempts)->toBe(1);
});
