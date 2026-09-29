<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

/** The catalog evidence required by a pricing quote; snapshots are serialized only at the storage boundary. */
final readonly class ServiceEligibilitySelectionDto
{
    /**
     * @param  list<string>  $reasonCodes
     * @param  array<string, string>  $labels
     * @param  array<string, string>  $serviceTypeLabels
     * @param  array<string, string>  $shippingMethodLabels
     * @param  list<SelectedServiceOptionDto>  $options
     */
    public function __construct(
        public string $offeringId,
        public string $offeringVersionId,
        public string $serviceTypeId,
        public string $serviceTypeVersionId,
        public string $shippingMethodId,
        public string $shippingMethodVersionId,
        public string $outcome,
        public array $reasonCodes,
        public array $labels,
        public array $serviceTypeLabels,
        public array $shippingMethodLabels,
        public array $options,
        public ?OfferingCommitmentDto $commitment,
    ) {}
}
