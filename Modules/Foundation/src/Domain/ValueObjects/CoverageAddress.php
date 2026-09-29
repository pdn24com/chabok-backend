<?php

declare(strict_types=1);

namespace Modules\Foundation\Domain\ValueObjects;

/** Address fields used by coverage rules; extensions preserve arbitrary contact facts. */
final class CoverageAddress
{
    /** @param array<string, mixed> $extensions */
    public function __construct(
        public ?string $country = null,
        public ?string $state = null,
        public ?string $city = null,
        public ?string $cityId = null,
        public ?string $provinceId = null,
        public ?string $postalCode = null,
        public int|float|string|null $latitude = null,
        public int|float|string|null $longitude = null,
        public ?string $zoneOverride = null,
        public array $extensions = [],
        /** @var list<string> Original fact key order for wildcard/first/last expressions. */
        public array $factKeys = [],
    ) {}

    /** Values exposed to configured dotted fact expressions. */
    public function factValue(string $key): mixed
    {
        return match ($key) {
            'country' => $this->country,
            'state' => $this->state,
            'city' => $this->city,
            'city_id' => $this->cityId,
            'province_id' => $this->provinceId,
            'postal_code' => $this->postalCode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'zone_override' => $this->zoneOverride,
            default => $this->extensions[$key] ?? null,
        };
    }

    /** Dynamic rule expressions are the dictionary boundary; application flow uses typed properties. */
    public function expressionFacts(): array
    {
        $values = [];
        foreach ($this->factKeys as $key) {
            $values[$key] = $this->factValue($key);
        }

        return $values;
    }
}
