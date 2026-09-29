<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Exceptions;

use DomainException;
use Modules\Foundation\Domain\Enums\ApiErrorCode;

final class InvalidPricingZone extends DomainException
{
    /** @param array<string, list<string>> $fieldErrors */
    public function __construct(
        public readonly ApiErrorCode $errorCode,
        public readonly string $messageKey,
        public readonly ?string $reasonCode = null,
        public readonly array $fieldErrors = [],
    ) {
        parent::__construct($messageKey);
    }
}
