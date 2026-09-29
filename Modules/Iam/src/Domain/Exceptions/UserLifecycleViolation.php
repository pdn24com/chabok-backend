<?php

declare(strict_types=1);

namespace Modules\Iam\Domain\Exceptions;

use DomainException;
use Modules\Iam\Domain\Enums\UserStatus;

final class UserLifecycleViolation extends DomainException
{
    public readonly string $messageKey;

    public function __construct(public readonly UserStatus $from, public readonly UserStatus $to)
    {
        $this->messageKey = 'iam.user_lifecycle_transition_is_not_allowed';
        parent::__construct($this->messageKey);
    }
}
