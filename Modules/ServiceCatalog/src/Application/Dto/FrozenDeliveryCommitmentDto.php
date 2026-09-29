<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

use Carbon\CarbonImmutable;
use Modules\ServiceCatalog\Application\Mappers\CommitmentInput;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentTimingPolicy;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentWindow;

/** The delivery policy frozen when the consignment was issued, awaiting an operational anchor. */
final readonly class FrozenDeliveryCommitmentDto
{
    /** @param list<CommitmentWindow> $windows */
    public function __construct(
        public CommitmentTimingPolicy $policy,
        public array $windows,
        public string $timezone,
        public bool $includeHolidays,
        public ?CarbonImmutable $acceptedAt,
        public ?string $requestedWindowCode,
    ) {}

    public static function fromSnapshot(?array $snapshot): ?self
    {
        if (empty($snapshot['policy']) || empty($snapshot['delivery']['awaiting_operation'])) {
            return null;
        }

        return new self(CommitmentInput::timingPolicy($snapshot['effective_delivery_policy']),
            array_map(CommitmentInput::window(...), $snapshot['windows_snapshot'] ?? []),
            $snapshot['timezone'], (bool) $snapshot['policy']['include_holidays'],
            isset($snapshot['accepted_at']) ? CarbonImmutable::parse($snapshot['accepted_at']) : null,
            $snapshot['requested_delivery_window_code'] ?? null);
    }
}
