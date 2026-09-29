<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

final class CatalogDraftDto
{
    public function __construct(
        public ?string $code = null,
        public array $labels = [],
        public ?string $description = null,
        public ?string $validFrom = null,
        public ?string $validTo = null,
        public array $definition = [],
        public ?string $serviceTypeVersionId = null,
        public ?string $shippingMethodVersionId = null,
        public array $slaPolicy = [],
        public array $availabilitySummary = [],
        public ?int $expectedVersion = null,
        /** @var list<OfferingOptionRuleDto> */ public array $optionRules = [],
        /** @var list<OfferingEligibilityRuleDto> */ public array $eligibilityRules = [],
        /** @var list<OfferingCoverageReferenceDto> */ public array $coverageReferences = [],
        /** @var list<OfferingAvailabilityDto> */ public array $availabilityBindings = [],
        public ?OfferingCommitmentBindingDto $commitmentBinding = null,
        /** @var list<string> */ public array $presentFields = [],
        public ?string $sourceFingerprint = null,
    ) {}
}
