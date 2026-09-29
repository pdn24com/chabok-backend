<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\Exceptions;

use DomainException;
use Modules\ServiceCatalog\Domain\Enums\CommitmentFailure;

final class CommitmentRuleViolation extends DomainException
{
    public readonly string $messageKey;

    public function __construct(public readonly CommitmentFailure $reason)
    {
        $this->messageKey = $reason->messageKey();
        parent::__construct($this->messageKey);
    }
}
