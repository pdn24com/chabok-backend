<?php

declare(strict_types=1);

namespace Modules\Manifest\Domain\Exceptions;

use DomainException;
use Modules\Foundation\Domain\Enums\ApiErrorCode;

final class ManifestRuleViolation extends DomainException
{
    public function __construct(public readonly ApiErrorCode $errorCode, public readonly string $messageKey, public readonly array $fieldErrors = [], public readonly array $details = [])
    {
        parent::__construct($messageKey);
    }
}
