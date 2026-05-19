<?php

declare(strict_types=1);

namespace Moko\Satellite\Services;

use RuntimeException;

final class SatelliteException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string $endpoint,
        public readonly array $errors = [],
    ) {
        parent::__construct($message, $statusCode);
    }
}
