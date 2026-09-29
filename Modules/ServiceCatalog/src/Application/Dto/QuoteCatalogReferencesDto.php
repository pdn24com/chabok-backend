<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

final readonly class QuoteCatalogReferencesDto
{
    /** @param list<string> $optionVersionIds */
    public function __construct(public string $hqId, public string $offeringVersionId, public ?string $typeVersionId = null,
        public ?string $methodVersionId = null, public ?string $scheduleVersionId = null, public array $optionVersionIds = [],
        public ?CommitmentZoneRevisionDto $destinationZone = null) {}

    public static function fromEvidence(string $hqId, string $offeringVersionId, array $resolution): self
    {
        $service = $resolution['service'] ?? [];
        $zone = $service['commitment']['destination_zone'] ?? null;

        return new self($hqId, $offeringVersionId, $service['service_type_version_id'] ?? null,
            $service['shipping_method_version_id'] ?? null, $service['commitment']['schedule_version_id'] ?? null,
            array_map(static fn (array $option): string => $option['service_option_version_id'], $service['selected_services'] ?? []),
            $zone ? new CommitmentZoneRevisionDto($zone['zone_set_id'], $zone['zone_set_version_id']) : null);
    }
}
