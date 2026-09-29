<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingLegacyCommitmentInterface;
use Modules\ServiceCatalog\Application\Dto\OfferingCommitmentDto;
use Modules\ServiceCatalog\Domain\Enums\CommitmentEvidenceKind;
use Modules\ServiceCatalog\Domain\ValueObjects\OfferingSelectionContext;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;

final readonly class OfferingLegacyCommitment implements OfferingLegacyCommitmentInterface
{
    public function __construct(private ClockInterface $clock) {}

    public function commitment(ServiceOfferingVersionRecord $row, OfferingSelectionContext $context): OfferingCommitmentDto
    {
        $policy = $row->sla_policy ?? [];
        $start = CarbonImmutable::parse((string) ($context->acceptanceAt ?? CarbonImmutable::instance($this->clock->now())->utc()->toISOString()))->utc();
        $value = max(0, (int) ($policy['duration_value'] ?? 0));
        $end = match ($policy['duration_unit'] ?? 'HOUR') {
            'MINUTE' => $start->addMinutes($value),
            'DAY' => $start->addDays($value),
            default => $start->addHours($value),
        };

        return new OfferingCommitmentDto(CommitmentEvidenceKind::LegacyDuration, policy: $policy,
            legacyType: $policy['commitment_type'] ?? 'DURATION', legacyStartsAt: $start, legacyEndsAt: $end);
    }
}
