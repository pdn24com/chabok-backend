<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Dto;

use Modules\Customer\Infrastructure\Persistence\Models\CustomerAddressRecord;

/**
 * One entry of a customer's address book, as the standalone address form submits it. Only the country,
 * the purpose and the written address are demanded; the reference province and city stay optional even
 * for Iran, matching the nullable columns behind them. Latitude and longitude are carried as decimal
 * strings so the stored precision survives the round trip untouched.
 */
final readonly class CustomerAddressDraftDto
{
    public function __construct(
        public string $countryCode,
        public string $purpose,
        public string $addressText,
        public ?string $provinceId = null,
        public ?string $cityId = null,
        public ?string $foreignRegion = null,
        public ?string $foreignCity = null,
        public ?string $postalCode = null,
        public ?string $plaque = null,
        public ?string $unit = null,
        public ?string $latitude = null,
        public ?string $longitude = null,
        public bool $isDefault = false,
    ) {}

    /** The stored entry as a draft, so a PATCH is validated as the whole address it will leave behind. */
    public static function fromRecord(CustomerAddressRecord $address): self
    {
        return new self(
            $address->country_code,
            $address->purpose,
            (string) $address->address_text,
            $address->province_id,
            $address->city_id,
            $address->foreign_region,
            $address->foreign_city,
            $address->postal_code,
            $address->plaque,
            $address->unit,
            $address->latitude,
            $address->longitude,
            (bool) $address->is_default,
        );
    }

    /**
     * The address as the row that stores it. Ownership, the author and the timestamps stay with the
     * caller, so the same map serves an insert and a full overwrite of an existing entry.
     */
    public function toAttributes(): array
    {
        return [
            'country_code' => $this->countryCode,
            'purpose' => $this->purpose,
            'address_text' => $this->addressText,
            'province_id' => $this->provinceId,
            'city_id' => $this->cityId,
            'foreign_region' => $this->foreignRegion,
            'foreign_city' => $this->foreignCity,
            'postal_code' => $this->postalCode,
            'plaque' => $this->plaque,
            'unit' => $this->unit,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_default' => $this->isDefault,
        ];
    }
}
