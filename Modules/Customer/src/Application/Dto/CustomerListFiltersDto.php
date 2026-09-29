<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Dto;

use DateTimeImmutable;
use Modules\Customer\Domain\Enums\CustomerKind;
use Modules\Customer\Domain\Enums\CustomerLifecycle;
use Modules\Customer\Domain\Enums\CustomerPhase;

final readonly class CustomerListFiltersDto
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 25,
        public ?string $displayName = null,
        public ?string $customerCode = null,
        public ?CustomerPhase $phase = null,
        public ?CustomerKind $kind = null,
        public ?CustomerLifecycle $lifecycle = null,
        public ?string $assigneeId = null,
        public ?DateTimeImmutable $updatedAt = null,
        public ?DateTimeImmutable $updatedAtFrom = null,
        public ?DateTimeImmutable $updatedAtTo = null,
    ) {}
}
