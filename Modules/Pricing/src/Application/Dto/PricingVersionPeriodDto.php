<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

use DateTimeImmutable;

final readonly class PricingVersionPeriodDto
{
    public function __construct(public string $versionId, public string $familyId, public int $versionNumber, public ?DateTimeImmutable $validFrom, public ?DateTimeImmutable $validTo) {}
}
