<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\Exceptions;

use DomainException;
use Modules\ServiceCatalog\Domain\Enums\CommitmentUnavailability;

final class OfferingCommitmentUnavailable extends DomainException
{
    public readonly string $messageKey;

    public function __construct(public readonly CommitmentUnavailability $reason)
    {
        $this->messageKey = $reason->messageKey();
        parent::__construct($this->messageKey);
    }
}
