<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

use Modules\ServiceCatalog\Domain\Enums\CommitmentUnavailability;

/** The outcome of inspecting one Offering: a promise, no bound schedule, or a documented reason it is unavailable. */
final readonly class OfferingCommitmentResolutionDto
{
    private function __construct(
        public ?OfferingCommitmentDto $commitment,
        public ?CommitmentUnavailability $unavailable,
    ) {}

    public static function promised(?OfferingCommitmentDto $commitment): self
    {
        return new self($commitment, null);
    }

    public static function unavailable(CommitmentUnavailability $reason): self
    {
        return new self(null, $reason);
    }

    public function available(): bool
    {
        return $this->unavailable === null;
    }
}
