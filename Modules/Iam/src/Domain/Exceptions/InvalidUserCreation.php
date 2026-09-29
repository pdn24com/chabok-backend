<?php

declare(strict_types=1);

namespace Modules\Iam\Domain\Exceptions;

use DomainException;

final class InvalidUserCreation extends DomainException
{
    public function __construct(public readonly string $messageKey = 'iam.creation_mode_requirements_not_satisfied')
    {
        parent::__construct($messageKey);
    }
}
