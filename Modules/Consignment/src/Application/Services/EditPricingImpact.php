<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Services;

use Modules\Consignment\Application\Contracts\EditPricingImpactInterface;
use Modules\Consignment\Application\Dto\ConsignmentDraftDto;
use Modules\Consignment\Application\Repositories\ConsignmentPricingRepositoryInterface;
use Modules\Consignment\Domain\Policies\EditPricingPolicy;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;

final readonly class EditPricingImpact implements EditPricingImpactInterface
{
    public function __construct(
        private EditPricingPolicy $editPricingPolicy,
        private ConsignmentPricingRepositoryInterface $consignmentPricingRepository,
    ) {}

    /** Fields which do not affect the accepted tariff or its geographic dependencies. */
    public function contactFields(ConsignmentRecord $row): array
    {
        $safe = ['contact_name', 'mobile', 'phone'];
        $quote = $this->consignmentPricingRepository->findQuoteForActiveSnapshot($row);
        if (! $quote) {
            return $safe;
        }
        $safe[] = 'address_text';
        $group = $quote->zoneVersion?->pricing_zone_set_id;
        if (! $group) {
            return $safe;
        }
        $groups = [$group];
        if (! empty($row['commitment_schedule_version_id'])) {
            $policy = $this->consignmentPricingRepository->publishedSchedulePolicy($row->commitment_schedule_version_id);
            if (! empty($policy['zone_set_id'])) {
                $groups[] = $policy['zone_set_id'];
            }
        }
        $types = $this->consignmentPricingRepository->publishedZoneMemberTypes($row->hq_id, $groups);
        if (! in_array('POLYGON', $types, true)) {
            $safe = [...$safe, 'latitude', 'longitude'];
        }
        if (! in_array('POSTAL_RANGE', $types, true)) {
            $safe[] = 'postal_code';
        }

        return $safe;
    }

    public function changed(
        ConsignmentDraftDto $before,
        ConsignmentDraftDto $after,
        array $safeContactFields,
    ): bool {
        return $this->editPricingPolicy->changed($before, $after, $safeContactFields);
    }
}
