<?php

declare(strict_types=1);

namespace Modules\Foundation\Domain;

use RuntimeException;

final class ApiException extends RuntimeException
{
    /**
     * @param array<string, list<string>> $fieldErrors
     * @param array<string, mixed> $details
     */
    public function __construct(
        public readonly ApiErrorCode $errorCode,
        public readonly int $httpStatus,
        string $publicMessage,
        public readonly array $fieldErrors = [],
        public readonly array $details = [],
    ) {
        parent::__construct($publicMessage);
    }
}
