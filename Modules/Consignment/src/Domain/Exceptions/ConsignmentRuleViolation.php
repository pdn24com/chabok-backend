<?php

declare(strict_types=1);

namespace Modules\Consignment\Domain\Exceptions;

use DomainException;
use Modules\Foundation\Domain\Enums\ApiErrorCode;

final class ConsignmentRuleViolation extends DomainException
{
    /** @param array<string, list<string>> $fieldErrors */
    public function __construct(
        public readonly ApiErrorCode $errorCode,
        public readonly string $messageKey,
        public readonly array $fieldErrors = [],
        public readonly ?string $reasonCode = null,
    ) {
        parent::__construct($messageKey);
    }
}
