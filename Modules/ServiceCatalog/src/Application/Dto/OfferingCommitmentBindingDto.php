<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

final class OfferingCommitmentBindingDto
{
    public function __construct(
        public string $commitmentScheduleVersionId = '',
        public string $pickupMode = 'NONE',
        public string $deliveryMode = 'NONE',
        public ?int $durationValue = null,
        public ?string $durationUnit = null,
        public ?string $durationAnchor = null,
        /** @var list<string> */ public array $presentFields = [],
    ) {}
}
