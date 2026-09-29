<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\ValueObjects;

final readonly class FreightMatrix
{
    /** @param list<string> $zoneIds @param list<FreightMatrixBand> $bands @param list<FreightMatrixBand> $linearBands */
    public function __construct(
        public string $id,
        public ?string $serviceOfferingVersionId,
        public ?string $serviceOptionVersionId,
        public ?string $originZoneId,
        public array $zoneIds,
        public array $bands,
        public array $linearBands,
        public bool $conflictingLinearDefinitions = false,
    ) {}

    public function hasSameContext(self $other): bool
    {
        return ($this->serviceOfferingVersionId ?? '') === ($other->serviceOfferingVersionId ?? '')
            && ($this->serviceOptionVersionId ?? '') === ($other->serviceOptionVersionId ?? '')
            && ($this->originZoneId ?? '') === ($other->originZoneId ?? '');
    }
}
