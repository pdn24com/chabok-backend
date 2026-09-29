<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

/**
 * One matrix being edited. `linearBands` stays null when the editor never sent the key, which is how a
 * draft that still carries only the historical `linearTail` is told apart from one that cleared its bands.
 */
final class FreightMatrixDraftDto
{
    /** @param list<string> $zoneIds @param list<FreightMatrixBandDraftDto> $bands @param list<FreightMatrixBandDraftDto>|null $linearBands */
    public function __construct(
        public string $id,
        public array $zoneIds,
        public array $bands,
        public ?string $serviceOfferingVersionId = null,
        public ?string $serviceOptionVersionId = null,
        public ?string $originZoneId = null,
        public ?FreightMatrixTailDraftDto $linearTail = null,
        public ?array $linearBands = null,
    ) {}

    public function hasSameCatalogSelection(?string $offeringVersionId, ?string $optionVersionId): bool
    {
        return $this->serviceOfferingVersionId === $offeringVersionId && $this->serviceOptionVersionId === $optionVersionId;
    }
}
