<?php

declare(strict_types=1);

namespace Modules\Iam\Domain\Exceptions;

use DomainException;

final class WeakPassword extends DomainException
{
    public function __construct(public readonly string $messageKey = 'iam.password_does_not_meet_requirements')
    {
        parent::__construct($messageKey);
    }
}
