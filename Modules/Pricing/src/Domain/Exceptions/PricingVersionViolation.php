<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Exceptions;

use DomainException;

final class PricingVersionViolation extends DomainException
{
    public function __construct(public readonly string $messageKey, public readonly ?int $currentVersion = null)
    {
        parent::__construct($messageKey);
    }
}
