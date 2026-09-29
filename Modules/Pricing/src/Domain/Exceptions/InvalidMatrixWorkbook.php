<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Exceptions;

use RuntimeException;

final class InvalidMatrixWorkbook extends RuntimeException
{
    /** @param array<string, scalar> $messageParams */
    public function __construct(
        public readonly string $messageKey,
        public readonly array $messageParams = [],
    ) {
        parent::__construct($messageKey);
    }
}
