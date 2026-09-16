<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class DestinationResolutionInput
{
    public function __construct(private \Modules\Operations\Application\Repositories\MovementRepository $plans)
    {
    }

    public function destinationResolutionInput(object $consignment): array
    {
        $input = [];
        if ($consignment->receiver_city_id !== null) {
            $input['city_id'] = (string) $consignment->receiver_city_id;
            $provinceId = $this->plans->provinceForActiveCity($consignment->receiver_city_id);
            if ($provinceId !== null) {
                $input['province_id'] = (string) $provinceId;
            }
        }
        if (preg_match('/^\d{10}$/', (string) $consignment->receiver_postal_code) === 1) {
            $input['postal_code'] = (string) $consignment->receiver_postal_code;
        }
        if ($consignment->receiver_latitude !== null && $consignment->receiver_longitude !== null) {
            $input['latitude'] = (float) $consignment->receiver_latitude;
            $input['longitude'] = (float) $consignment->receiver_longitude;
        }
        if ($input === []) {
            throw new ApiException(ApiErrorCode::CoverageNotFound, 422, 'Canonical destination geography is unavailable for coverage resolution.');
        }
        return $input;
    }
}
