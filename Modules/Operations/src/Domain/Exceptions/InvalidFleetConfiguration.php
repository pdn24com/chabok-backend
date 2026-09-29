<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Exceptions;

use DomainException;

final class InvalidFleetConfiguration extends DomainException
{
    /** @param array<string, list<string>> $fieldErrors */
    public function __construct(public readonly string $messageKey, public readonly array $fieldErrors = [])
    {
        parent::__construct($messageKey);
    }
}
