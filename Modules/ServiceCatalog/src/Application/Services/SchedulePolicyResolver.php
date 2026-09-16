<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Carbon\CarbonImmutable;
use Modules\ServiceCatalog\Application\CommitmentClock;

final readonly class SchedulePolicyResolver
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\ScheduleReader $scheduleReader,
        private \Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepository $schedules,
        private \Modules\ServiceCatalog\Application\Contracts\CommitmentZoneResolver $zones,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
    )
    {
    }

    public function resolvePolicy(object $version, array $context, bool $requireSelection, string $hqId): array
    {
        $policy = json_decode($version->commitment_policy, true, 512, JSON_THROW_ON_ERROR);
        $windows = array_map(fn($w) => $this->scheduleReader->decodeWindow((array) $w), $this->schedules->windows($version->commitment_schedule_version_id));
        $selectedRule = null;
        $zone = null;
        $delivery = $policy['delivery'];
        if (!empty($policy['zone_set_id'])) {
            $zone = $this->zones->destination($hqId, $policy['zone_set_id'], (array) ($context['receiver'] ?? $context['destination'] ?? []));
            foreach ($policy['destination_rules'] as $rule) {
                if (($zone['zone']['code'] ?? null) === $rule['destination_zone_code']) {
                    $selectedRule = $rule['id'];
                    $delivery = $rule['policy'];
                }
            }
        }
        if ($delivery === null) {
            return [
                'eligible' => false,
                'reason_code' => 'DELIVERY_COMMITMENT_UNCONFIGURED',
                'schedule_version_id' => $version->commitment_schedule_version_id,
                'destination_zone' => $zone,
            ];
        }
        $clock = new CommitmentClock();
        $timezone = (string) $version->timezone;
        $pickup = $clock->resolve($policy['pickup'], $windows, $context, $timezone, (bool) $policy['include_holidays'], $requireSelection, 'PICKUP');
        $context['pickup_starts_at'] = $pickup['starts_at'] ?? $pickup['computed_at'] ?? null;
        $context['pickup_ends_at'] = $pickup['ends_at'] ?? $pickup['computed_at'] ?? null;
        $result = $clock->resolve($delivery, $windows, $context, $timezone, (bool) $policy['include_holidays'], $requireSelection, 'DELIVERY');
        return [
            'eligible' => true,
            'reason_code' => null,
            'schedule_version_id' => $version->commitment_schedule_version_id,
            'timezone' => $timezone,
            'accepted_at' => $context['acceptance_at'] ?? CarbonImmutable::instance($this->clock->now())->utc()->toISOString(),
            'requested_delivery_window_code' => $context['delivery_window_code'] ?? null,
            'policy' => $policy,
            'effective_delivery_policy' => $delivery,
            'selected_rule_id' => $selectedRule,
            'destination_zone' => $zone,
            'pickup' => $pickup,
            'delivery' => $result,
            'windows_snapshot' => $windows,
        ];
    }
}
