<?php

declare(strict_types=1);

namespace Modules\Consignment\Application;

use Modules\Consignment\Application\Repositories\PricingImpactReader;
use Modules\Consignment\Domain\EditPricingPolicy;

final readonly class EditPricingImpact
{
    public function __construct(private PricingImpactReader $dependencies, private EditPricingPolicy $policy)
    {
    }
    /** Fields which do not affect the accepted tariff or its geographic dependencies. */

    public function contactFields(array $row): array
    {
        $safe = ['contact_name', 'mobile', 'phone'];
        $quote = $this->dependencies->acceptedZoneVersion($row['hq_id'], $row['active_pricing_snapshot_id'] ?? null);
        if (!$quote) {
            return $safe;
        }
        $safe[] = 'address_text';
        $group = $this->dependencies->zoneSetId($quote->zone_set_version_id);
        if (!$group) {
            return $safe;
        }
        $groups = [$group];
        if (!empty($row['commitment_schedule_version_id'])) {
            $schedule = $this->dependencies->commitmentScheduleId($row['commitment_schedule_version_id']);
            $policy = $this->dependencies->publishedCommitmentPolicy($schedule);
            $policy = json_decode($policy ?? '{}', true);
            if (!empty($policy['zone_set_id'])) {
                $groups[] = $policy['zone_set_id'];
            }
        }
        $types = $this->dependencies->geographicMemberTypes($row['hq_id'], $groups);
        if (!in_array('POLYGON', $types, true)) {
            $safe = [...$safe, 'latitude', 'longitude'];
        }
        if (!in_array('POSTAL_RANGE', $types, true)) {
            $safe[] = 'postal_code';
        }
        return $safe;
    }

    public function changed(array $before, array $after, array $safeContactFields): bool
    {
        return $this->policy->changed($before, $after, $safeContactFields);
    }
}
