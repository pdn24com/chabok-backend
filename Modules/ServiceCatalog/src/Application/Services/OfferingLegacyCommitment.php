<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Carbon\CarbonImmutable;

final readonly class OfferingLegacyCommitment
{
    public function __construct(private \Modules\Foundation\Application\Contracts\Clock $clock)
    {
    }

    public function commitment(array $row, array $context): array
    {
        $policy = is_string($row['sla_policy'] ?? null) ? json_decode($row['sla_policy'], true) : (array) ($row['sla_policy'] ?? []);
        $start = CarbonImmutable::parse((string) ($context['acceptance_at'] ?? CarbonImmutable::instance($this->clock->now())->utc()->toISOString()))->utc();
        $value = max(0, (int) ($policy['duration_value'] ?? 0));
        $end = match ($policy['duration_unit'] ?? 'HOUR') {
            'MINUTE' => $start->addMinutes($value),
            'DAY' => $start->addDays($value),
            default => $start->addHours($value),
        };
        return [
            'commitment_type' => $policy['commitment_type'] ?? 'DURATION',
            'starts_at' => $start->toISOString(),
            'delivery_commitment_at' => $end->toISOString(),
            'policy' => $policy,
            'pickup' => ['mode' => 'NONE'],
            'delivery' => ['mode' => 'COMPUTED', 'computed_at' => $end->toISOString()],
        ];
    }
}
