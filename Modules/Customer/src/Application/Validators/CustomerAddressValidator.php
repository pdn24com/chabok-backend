<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Validators;

use Modules\Customer\Application\Contracts\CustomerAddressValidatorInterface;
use Modules\Customer\Application\Dto\CustomerAddressDraftDto;
use Modules\Customer\Application\Dto\CustomerAddressDto;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Geography\Application\Repositories\CityRepositoryInterface;
use Modules\Geography\Application\Repositories\CountryRepositoryInterface;
use Modules\Geography\Application\Repositories\ProvinceRepositoryInterface;

final readonly class CustomerAddressValidator implements CustomerAddressValidatorInterface
{
    public function __construct(
        private CountryRepositoryInterface $countryRepository,
        private CityRepositoryInterface $cityRepository,
        private ProvinceRepositoryInterface $provinceRepository,
    ) {}

    public function validate(CustomerAddressDto $address): void
    {
        $this->assertActiveCountry($address->countryCode);

        if ($address->countryCode === 'IR') {
            $this->validateIranianAddress($address);

            return;
        }

        $this->validateForeignAddress($address);
    }

    /**
     * The address book demands only the country, the purpose and the written address, so an Iranian entry
     * may name no province and no city at all. What is named still has to be real: a reference city must
     * be active and must sit in the named province, and reference geography stays out of a foreign entry
     * exactly as a foreign region or city stays out of an Iranian one.
     */
    public function validateEntry(CustomerAddressDraftDto $address): void
    {
        $this->assertActiveCountry($address->countryCode);

        if ($address->countryCode !== 'IR') {
            $this->assertNoCanonicalGeography($address->provinceId, $address->cityId);

            return;
        }

        if ($address->foreignRegion !== null) {
            throw $this->invalid('foreign_region', 'customer.foreign_region_requires_foreign_country');
        }
        if ($address->foreignCity !== null) {
            throw $this->invalid('foreign_city', 'customer.foreign_city_requires_foreign_country');
        }
        if ($address->provinceId !== null && ! $this->provinceRepository->activeExists($address->provinceId)) {
            throw $this->invalid('province_id', 'customer.select_active_province_from_reference_data');
        }
        if ($address->cityId !== null) {
            $this->assertCityBelongsToProvince($address->cityId, $address->provinceId);
        }
        // An Iranian postal code is exactly ten digits. The rule lives here rather than in the request,
        // because a PATCH may change the country and the code in either order, or neither.
        if ($address->postalCode !== null && preg_match('/^\d{10}$/D', $address->postalCode) !== 1) {
            throw $this->invalid('postal_code', 'customer.iranian_postal_code_has_ten_digits');
        }
    }

    private function validateIranianAddress(CustomerAddressDto $address): void
    {
        if ($address->cityId === null) {
            throw $this->invalid('city_id', 'geography.select_active_city_from_reference_data');
        }
        $this->assertCityBelongsToProvince($address->cityId, $address->provinceId);
        if ($address->foreignCity !== null) {
            throw $this->invalid('foreign_city', 'customer.foreign_city_requires_foreign_country');
        }
    }

    private function validateForeignAddress(CustomerAddressDto $address): void
    {
        $this->assertNoCanonicalGeography($address->provinceId, $address->cityId);
        if ($address->foreignCity === null || trim($address->foreignCity) === '') {
            throw $this->invalid('foreign_city', 'customer.foreign_city_is_required');
        }
    }

    private function assertActiveCountry(string $countryCode): void
    {
        if ($this->countryRepository->findActiveByCode($countryCode) === null) {
            throw $this->invalid('country_code', 'customer.select_active_country');
        }
    }

    private function assertNoCanonicalGeography(?string $provinceId, ?string $cityId): void
    {
        if ($provinceId !== null) {
            throw $this->invalid('province_id', 'customer.canonical_province_requires_iran');
        }
        if ($cityId !== null) {
            throw $this->invalid('city_id', 'customer.canonical_city_requires_iran');
        }
    }

    /** A named reference city must be active and must belong to the province named beside it. */
    private function assertCityBelongsToProvince(string $cityId, ?string $provinceId): void
    {
        $city = $this->cityRepository->findWithProvince($cityId);
        if ($city === null || ! $city->is_active) {
            throw $this->invalid('city_id', 'geography.select_active_city_from_reference_data');
        }
        if (! $city->province?->is_active || $city->province_id !== $provinceId) {
            throw $this->invalid('province_id', 'customer.select_active_province_for_city');
        }
    }

    private function invalid(string $field, string $messageKey): ApiException
    {
        return new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', [$field => [$messageKey]]);
    }
}
