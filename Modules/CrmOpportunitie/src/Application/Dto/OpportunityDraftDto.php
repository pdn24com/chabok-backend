<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\Dto;

use DateTimeImmutable;

/**
 * A new opportunity as the form sends it. The starting step is not among these fields: the server puts
 * the opportunity on the first step of the funnel, so no client can open one halfway down the pipeline.
 */
final readonly class OpportunityDraftDto
{
    public function __construct(
        public string $customerId,
        public string $funnelId,
        public string $title,
        public string $assigneeId,
        public ?int $amount = null,
        /** A percentage between 0 and 100, kept as a decimal string so the stored precision is exact. */
        public ?string $probability = null,
        public ?DateTimeImmutable $expectedClose = null,
    ) {}
}
