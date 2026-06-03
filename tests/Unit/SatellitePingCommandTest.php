<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

it('affiche 200 OK sur un endpoint qui répond', function () {
    Http::fake(['https://api.example.com/health' => Http::response(['status' => 'ok'], 200)]);

    $this->artisan('satellite:ping')
        ->assertSuccessful()
        ->expectsOutputToContain('200 OK');
});

it('affiche le contenu JSON de la réponse', function () {
    Http::fake(['https://api.example.com/health' => Http::response(['status' => 'ok', 'version' => '1.2.3'], 200)]);

    $this->artisan('satellite:ping')
        ->assertSuccessful()
        ->expectsOutputToContain('status');
});

it('retourne un code d\'échec sur une réponse 500', function () {
    Http::fake(['https://api.example.com/health' => Http::response(null, 500)]);

    $this->artisan('satellite:ping')
        ->assertFailed();
});

it('appelle le bon endpoint avec --endpoint', function () {
    Http::fake(['https://api.example.com/api/v1/health' => Http::response(['status' => 'ok'], 200)]);

    $this->artisan('satellite:ping', ['--endpoint' => '/api/v1/health'])
        ->assertSuccessful()
        ->expectsOutputToContain('/api/v1/health');
});

it('surcharge l\'URL avec --url', function () {
    Http::fake(['https://api-qualification.example.com/health' => Http::response(['status' => 'ok'], 200)]);

    $this->artisan('satellite:ping', ['--url' => 'https://api-qualification.example.com'])
        ->assertSuccessful()
        ->expectsOutputToContain('api-qualification.example.com');
});

it('appelle sans token avec --no-token', function () {
    Http::fake(['https://api.example.com/health' => Http::response(['status' => 'ok'], 200)]);

    $this->artisan('satellite:ping', ['--no-token' => true])
        ->assertSuccessful();

    Http::assertSent(fn ($request) => $request->url() === 'https://api.example.com/health');
});

it('affiche un message d\'erreur sur exception réseau', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('Connection refused')]);

    $this->artisan('satellite:ping')
        ->assertFailed();
});
