<?php

declare(strict_types=1);

namespace Modules\Geography\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Geography\Application\Contracts\GeographyResolverInterface;
use Modules\Geography\Application\Repositories\CityRepositoryInterface;

final readonly class GeographyResolver implements GeographyResolverInterface
{
    public function __construct(private CityRepositoryInterface $cityRepository) {}

    /**
     * Canonical identity always wins over client-supplied province/city snapshots.
     *
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    public function canonicalizeContact(array $contact, bool $required): array
    {
        $cityId = $contact['city_id'] ?? null;
        if (! is_string($cityId) || $cityId === '') {
            if ($required) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'geography.active_canonical_city_is_required', ['city_id' => ['geography.select_active_city_from_reference_data']]);
            }

            return $contact;
        }
        $city = $this->cityRepository->findWithProvince($cityId);
        if ($city === null || ! (bool) $city->is_active || ! (bool) $city->province->is_active) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'geography.selected_city_is_inactive_invalid', ['city_id' => ['geography.select_active_city_from_reference_data']]);
        }

        return [
            ...$contact,
            'city_id' => (string) $city->city_id,
            'city' => (string) $city->name_fa,
            'state' => (string) $city->province->name_fa,
            'province_id' => (string) $city->province_id,
            'legacy_city_code' => (string) $city->legacy_city_code,
        ];
    }

    /** @return array<string, mixed>|null */
    public function cityReference(?string $cityId): ?array
    {
        if ($cityId === null || $cityId === '') {
            return null;
        }
        $row = $this->cityRepository->findWithProvince($cityId);
        if ($row === null) {
            return null;
        }

        return [
            'city_id' => (string) $row->city_id,
            'legacy_city_code' => (string) $row->legacy_city_code,
            'name_fa' => (string) $row->name_fa,
            'display_name' => sprintf('%s — %s (کد %s)', $row->name_fa, $row->province->name_fa, $row->legacy_city_code),
            'is_active' => (bool) $row->is_active,
            'province' => [
                'province_id' => (string) $row->province_id,
                'legacy_province_code' => (string) $row->province->legacy_province_code,
                'name_fa' => (string) $row->province->name_fa,
            ],
        ];
    }
}
