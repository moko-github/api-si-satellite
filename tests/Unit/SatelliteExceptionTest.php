<?php

declare(strict_types=1);

use Moko\Satellite\Services\SatelliteException;

it('exposes statusCode, endpoint and errors', function () {
    $exception = new SatelliteException(
        message: 'Something went wrong',
        statusCode: 422,
        endpoint: '/api/v1/users',
        errors: ['field' => ['required']],
    );

    expect($exception->statusCode)->toBe(422)
        ->and($exception->endpoint)->toBe('/api/v1/users')
        ->and($exception->errors)->toBe(['field' => ['required']])
        ->and($exception->getMessage())->toBe('Something went wrong')
        ->and($exception->getCode())->toBe(422);
});

it('defaults errors to empty array', function () {
    $exception = new SatelliteException(
        message: 'Not found',
        statusCode: 404,
        endpoint: '/api/v1/items/99',
    );

    expect($exception->errors)->toBe([]);
});

it('captures the raw body and defaults it to null', function () {
    $withBody = new SatelliteException(
        message: 'Bad gateway',
        statusCode: 502,
        endpoint: '/api/v1/items',
        body: '<html>Bad Gateway</html>',
    );

    $withoutBody = new SatelliteException(
        message: 'Not found',
        statusCode: 404,
        endpoint: '/api/v1/items/99',
    );

    expect($withBody->body)->toBe('<html>Bad Gateway</html>')
        ->and($withoutBody->body)->toBeNull();
});
