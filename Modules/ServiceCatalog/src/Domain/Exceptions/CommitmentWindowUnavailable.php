<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\Exceptions;

use DomainException;
use Modules\ServiceCatalog\Domain\Enums\CommitmentWindowType;
use Modules\ServiceCatalog\Domain\Enums\CommitmentWindowUnavailability;

final class CommitmentWindowUnavailable extends DomainException
{
    public readonly string $messageKey;

    public function __construct(
        public readonly CommitmentWindowUnavailability $reason,
        public readonly CommitmentWindowType $windowType,
    ) {
        $this->messageKey = $reason->messageKey();
        parent::__construct($this->messageKey);
    }

    public function reasonCode(): string
    {
        return $this->reason->reasonCode($this->windowType);
    }
}
