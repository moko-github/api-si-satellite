<?php

declare(strict_types=1);

namespace Moko\Satellite\Services;

use RuntimeException;
use Throwable;

final class SatelliteException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $errors  Erreurs structurées extraites de la réponse JSON (clé `errors`).
     * @param  string|null  $body  Corps brut de la réponse, utile pour les erreurs non-JSON (502 HTML, timeouts…).
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string $endpoint,
        public readonly array $errors = [],
        public readonly ?string $body = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
