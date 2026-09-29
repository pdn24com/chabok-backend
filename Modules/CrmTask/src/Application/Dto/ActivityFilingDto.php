<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\Dto;

/**
 * Where an interaction is filed: the customer it concerns, the opportunity it advances and the task it
 * was performed for. As submitted any of them may be missing; once resolved they agree with each other.
 */
final readonly class ActivityFilingDto
{
    public function __construct(
        public ?string $customerId = null,
        public ?string $opportunityId = null,
        public ?string $taskId = null,
    ) {}
}
