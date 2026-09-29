<?php

declare(strict_types=1);

namespace Modules\Consignment\Domain\Exceptions;

use DomainException;
use Modules\Foundation\Domain\Enums\ApiErrorCode;

final class InvalidNumberRange extends DomainException
{
    public function __construct(public readonly ApiErrorCode $errorCode, public readonly string $messageKey)
    {
        parent::__construct($messageKey);
    }
}
