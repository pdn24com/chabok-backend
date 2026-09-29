<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Exceptions;

use Modules\Pricing\Domain\ValueObjects\PricingValidationResult;
use RuntimeException;

final class InvalidTariffMatrix extends RuntimeException
{
    public function __construct(public readonly PricingValidationResult $validation, public readonly string $messageKey)
    {
        parent::__construct($messageKey);
    }
}
