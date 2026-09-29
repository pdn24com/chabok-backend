<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

use Carbon\CarbonImmutable;
use Modules\ServiceCatalog\Domain\Enums\CommitmentMode;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentWindowInstance;

final class LegacyCommitmentPromiseDto
{
    /** @param list<CommitmentWindowInstance> $windows */
    public function __construct(
        public CommitmentMode $mode,
        public array $windows = [],
        public ?CommitmentWindowInstance $selected = null,
        public bool $selectionRequired = false,
        public bool $flattenSelection = false,
        public ?string $anchor = null,
        public int|string|null $durationValue = null,
        public ?string $durationUnit = null,
        public ?CarbonImmutable $computedAt = null,
        public bool $awaitingOperation = false,
    ) {}
}
