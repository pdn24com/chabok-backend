<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Repositories;

use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingQuoteRecord;

interface ConsignmentPricingRepositoryInterface
{
    /** @param list<array<string, mixed>> $rows */
    public function insertChargeLines(array $rows): void;

    /** The quote behind a Consignment's active snapshot, with the Zone version its prices were computed against. */
    public function findQuoteForActiveSnapshot(ConsignmentRecord $consignment): ?PricingQuoteRecord;

    /** @return array<string, mixed>|null */
    public function publishedSchedulePolicy(?string $commitmentScheduleVersionId): ?array;

    /** Distinct Zone member types a Consignment's Zone groups still publish, to decide whether an edit moves the price. @param list<string> $zoneSetIds @return list<string> */
    public function publishedZoneMemberTypes(?string $hqId, array $zoneSetIds): array;
}
