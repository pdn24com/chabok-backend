<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

/**
 * Typed request values. Numeric strings retain their wire representation for stable fingerprints.
 * presentFields preserves omitted versus null; extensions are opaque transport metadata only.
 */
final class QuoteContactDto
{
    /** @param list<string> $presentFields @param array<string, mixed> $extensions */
    public function __construct(
        public ?string $contactName = null,
        public ?string $mobile = null,
        public ?string $phone = null,
        public ?string $addressText = null,
        public ?string $addressBookEntryId = null,
        public ?string $country = null,
        public ?string $state = null,
        public ?string $city = null,
        public ?string $cityId = null,
        public ?string $provinceId = null,
        public ?string $postalCode = null,
        public int|float|string|null $latitude = null,
        public int|float|string|null $longitude = null,
        public array $presentFields = [],
        public array $extensions = [],
    ) {}
}
