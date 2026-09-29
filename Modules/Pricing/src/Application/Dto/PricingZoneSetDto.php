<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

use DateTimeImmutable;
use Modules\Pricing\Application\Mappers\PricingZoneInput;
use Modules\Pricing\Domain\Enums\PricingPurpose;

final readonly class PricingZoneSetDto
{
    /** @param list<PricingZoneDraftDto> $zones */
    public function __construct(public array $zones, public ?string $code = null, public ?string $title = null,
        public ?PricingPurpose $purpose = null, public ?DateTimeImmutable $validFrom = null, public ?DateTimeImmutable $validTo = null,
        public ?int $expectedVersion = null) {}

    public static function fromInput(array $input): self
    {
        return new self(PricingZoneInput::many($input['zones']), $input['code'] ?? null, $input['title'] ?? null,
            isset($input['purpose']) ? PricingPurpose::from($input['purpose']) : null,
            empty($input['valid_from']) ? null : new DateTimeImmutable($input['valid_from']),
            empty($input['valid_to']) ? null : new DateTimeImmutable($input['valid_to']),
            isset($input['expected_version']) ? (int) $input['expected_version'] : null);
    }
}
