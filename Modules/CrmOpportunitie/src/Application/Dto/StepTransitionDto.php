<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\Dto;

use DateTimeImmutable;

/**
 * A move of one opportunity from one step to another. fromStepId is where the operator believed the
 * opportunity stood; the record carries no version column, so that belief is what guards the move
 * against a second operator who moved it first.
 */
final readonly class StepTransitionDto
{
    public function __construct(
        public string $fromStepId,
        public string $toStepId,
        public ?string $reason = null,
        public ?DateTimeImmutable $occurredAt = null,
        /** The interaction that proves the move, demanded when the opportunity is being won. */
        public ?string $evidenceActivityId = null,
        public ?string $closeReason = null,
    ) {}
}
