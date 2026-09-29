<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Dto;

/**
 * A PATCH of one address-book entry. Country, purpose and the written address cannot be cleared, so a
 * null simply means "left alone"; every field that may be cleared carries its own *Specified flag, so an
 * explicit null is told apart from an absent key.
 */
final readonly class CustomerAddressChangesDto
{
    public function __construct(
        public ?string $countryCode = null,
        public ?string $purpose = null,
        public ?string $addressText = null,
        public ?string $provinceId = null,
        public bool $provinceSpecified = false,
        public ?string $cityId = null,
        public bool $citySpecified = false,
        public ?string $foreignRegion = null,
        public bool $foreignRegionSpecified = false,
        public ?string $foreignCity = null,
        public bool $foreignCitySpecified = false,
        public ?string $postalCode = null,
        public bool $postalCodeSpecified = false,
        public ?string $plaque = null,
        public bool $plaqueSpecified = false,
        public ?string $unit = null,
        public bool $unitSpecified = false,
        /** The pair moves together: the database keeps latitude and longitude both set or both null. */
        public ?string $latitude = null,
        public ?string $longitude = null,
        public bool $coordinatesSpecified = false,
        public ?bool $isDefault = null,
    ) {}

    public function touchesNothing(): bool
    {
        return $this->countryCode === null && $this->purpose === null && $this->addressText === null
            && $this->isDefault === null && ! $this->coordinatesSpecified
            && ! $this->provinceSpecified && ! $this->citySpecified
            && ! $this->foreignRegionSpecified && ! $this->foreignCitySpecified
            && ! $this->postalCodeSpecified && ! $this->plaqueSpecified && ! $this->unitSpecified;
    }

    /** The address as it will stand once the change lands, which is what the domain rules judge. */
    public function applyTo(CustomerAddressDraftDto $current): CustomerAddressDraftDto
    {
        return new CustomerAddressDraftDto(
            $this->countryCode ?? $current->countryCode,
            $this->purpose ?? $current->purpose,
            $this->addressText ?? $current->addressText,
            $this->provinceSpecified ? $this->provinceId : $current->provinceId,
            $this->citySpecified ? $this->cityId : $current->cityId,
            $this->foreignRegionSpecified ? $this->foreignRegion : $current->foreignRegion,
            $this->foreignCitySpecified ? $this->foreignCity : $current->foreignCity,
            $this->postalCodeSpecified ? $this->postalCode : $current->postalCode,
            $this->plaqueSpecified ? $this->plaque : $current->plaque,
            $this->unitSpecified ? $this->unit : $current->unit,
            $this->coordinatesSpecified ? $this->latitude : $current->latitude,
            $this->coordinatesSpecified ? $this->longitude : $current->longitude,
            $this->isDefault ?? $current->isDefault,
        );
    }
}
