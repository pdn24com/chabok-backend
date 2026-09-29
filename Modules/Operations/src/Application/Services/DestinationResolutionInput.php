<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Geography\Application\Repositories\CityRepositoryInterface;
use Modules\Geography\Domain\ValueObjects\GeoPoint;
use Modules\Operations\Application\Contracts\DestinationResolutionInputInterface;
use Modules\Operations\Domain\ValueObjects\CoverageLocation;

final class DestinationResolutionInput implements DestinationResolutionInputInterface
{
    public function __construct(
        private CityRepositoryInterface $cityRepository,
    ) {}

    public function destinationResolutionInput(ConsignmentRecord $consignment): CoverageLocation
    {
        $cityId = $consignment->receiver_city_id;
        $provinceId = $cityId === null ? null : $this->cityRepository->findActive($cityId)?->province_id;
        $postalCode = preg_match('/^\d{10}$/', (string) $consignment->receiver_postal_code) === 1 ? $consignment->receiver_postal_code : null;
        $point = $consignment->receiver_latitude !== null && $consignment->receiver_longitude !== null
            ? new GeoPoint(latitude: (float) $consignment->receiver_latitude, longitude: (float) $consignment->receiver_longitude) : null;
        if ($cityId === null && $postalCode === null && $point === null) {
            throw new ApiException(ApiErrorCode::CoverageNotFound, 422, 'operations.canonical_destination_geography_is_unavailable_coverage_resolution');
        }

        return new CoverageLocation($provinceId, $cityId, $postalCode, $point);
    }
}
