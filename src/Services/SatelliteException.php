<?php

declare(strict_types=1);

namespace Moko\Satellite\Services;

use RuntimeException;
use Throwable;

class SatelliteException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string $endpoint,
        public readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
