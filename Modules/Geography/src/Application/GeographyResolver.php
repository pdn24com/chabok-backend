<?php

declare(strict_types=1);

namespace Modules\Geography\Application;

use Modules\Geography\Application\Repositories\GeographyRepository;
use Modules\Foundation\Application\Contracts\CanonicalGeographyResolver;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class GeographyResolver implements CanonicalGeographyResolver
{
    public function __construct(private GeographyRepository $geography)
    {
    }
    /**
     * Canonical identity always wins over client-supplied province/city snapshots.
     *
     * @param array<string, mixed> $contact
     * @return array<string, mixed>
     */

    public function canonicalizeContact(array $contact, bool $required): array
    {
        $cityId = $contact['city_id'] ?? null;
        if (!is_string($cityId) || $cityId === '') {
            if ($required) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'An active canonical city is required.', ['city_id' => ['Select an active city from the Geography reference data.']]);
            }
            return $contact;
        }
        $city = $this->geography->city($cityId);
        if ($city === null || !(bool) $city->is_active || !(bool) $city->province_active) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The selected city is inactive or invalid.', ['city_id' => ['Select an active city from the Geography reference data.']]);
        }
        return [
            ...$contact,
            'city_id' => (string) $city->city_id,
            'city' => (string) $city->name_fa,
            'state' => (string) $city->province_name_fa,
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
        $row = $this->geography->city($cityId);
        if ($row === null) {
            return null;
        }
        return [
            'city_id' => (string) $row->city_id,
            'legacy_city_code' => (string) $row->legacy_city_code,
            'name_fa' => (string) $row->name_fa,
            'display_name' => sprintf('%s — %s (کد %s)', $row->name_fa, $row->province_name_fa, $row->legacy_city_code),
            'is_active' => (bool) $row->is_active,
            'province' => [
                'province_id' => (string) $row->province_id,
                'legacy_province_code' => (string) $row->legacy_province_code,
                'name_fa' => (string) $row->province_name_fa,
            ],
        ];
    }
}
