<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Dto;

use Carbon\CarbonImmutable;
use Modules\Consignment\Domain\Enums\ConsignmentStatusGroup;
use Modules\Consignment\Domain\Enums\SlaRisk;

final readonly class ConsignmentFiltersDto
{
    /** @param list<string> $statuses @param list<string> $pickupNodeIds @param list<string> $deliveryNodeIds
     * @param list<string> $pickupDriverIds @param list<string> $deliveryDriverIds @param list<SlaRisk> $slaRisks */
    public function __construct(
        public ?string $search,
        public array $statuses,
        public ?ConsignmentStatusGroup $statusGroup,
        public array $pickupNodeIds,
        public array $deliveryNodeIds,
        public array $pickupDriverIds,
        public array $deliveryDriverIds,
        public ?string $serviceTypeId,
        public ?string $shippingMethodId,
        public ?CarbonImmutable $createdFrom,
        public ?CarbonImmutable $createdTo,
        public array $slaRisks,
        public string $sortField,
        public string $sortDirection,
        public int $pageSize,
        public int $page,
    ) {}

    public static function fromValidated(array $input): self
    {
        $sort = $input['sort'] ?? '-created_at';
        $field = ltrim($sort, '-');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        if (! in_array($field, ['created_at', 'updated_at', 'consignment_number', 'current_status'], true)) {
            $field = 'created_at';
            $direction = 'desc';
        }

        return new self(
            $input['search'] ?? null, array_values((array) ($input['status'] ?? [])),
            isset($input['status_group']) ? ConsignmentStatusGroup::from($input['status_group']) : null,
            array_values((array) ($input['pickup_node_id'] ?? [])), array_values((array) ($input['delivery_node_id'] ?? [])),
            array_values((array) ($input['pickup_man_id'] ?? [])), array_values((array) ($input['delivery_man_id'] ?? [])),
            $input['service_type_id'] ?? null, $input['shipping_method_id'] ?? null,
            isset($input['created_from']) ? CarbonImmutable::parse($input['created_from'])->utc() : null,
            isset($input['created_to']) ? CarbonImmutable::parse($input['created_to'])->utc() : null,
            array_map(SlaRisk::from(...), array_values((array) ($input['sla_risk'] ?? []))),
            $field, $direction, (int) ($input['page_size'] ?? 25), (int) ($input['page'] ?? 1),
        );
    }
}
