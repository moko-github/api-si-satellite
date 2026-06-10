<?php

declare(strict_types=1);

use Moko\Satellite\Services\SatelliteException;

it('exposes statusCode, endpoint and errors', function () {
    $exception = new SatelliteException(
        message:    'Something went wrong',
        statusCode: 422,
        endpoint:   '/api/v1/users',
        errors:     ['field' => ['required']],
    );

    expect($exception->statusCode)->toBe(422)
        ->and($exception->endpoint)->toBe('/api/v1/users')
        ->and($exception->errors)->toBe(['field' => ['required']])
        ->and($exception->getMessage())->toBe('Something went wrong')
        ->and($exception->getCode())->toBe(422);
});

it('defaults errors to empty array', function () {
    $exception = new SatelliteException(
        message:    'Not found',
        statusCode: 404,
        endpoint:   '/api/v1/items/99',
    );

    expect($exception->errors)->toBe([]);
});

it('can be extended by satellite-specific exceptions', function () {
    $child = new class('Boom', 500, '/api/v1/health') extends SatelliteException {};

    expect($child)->toBeInstanceOf(SatelliteException::class)
        ->and($child->statusCode)->toBe(500)
        ->and($child->endpoint)->toBe('/api/v1/health');
});
