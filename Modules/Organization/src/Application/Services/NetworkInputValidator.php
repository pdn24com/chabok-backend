<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Geography\Application\Repositories\CityRepositoryInterface;
use Modules\Geography\Application\Repositories\ProvinceRepositoryInterface;
use Modules\Organization\Application\Contracts\NetworkInputValidatorInterface;
use Modules\Organization\Application\Dto\NodeDetailsDto;
use Modules\Organization\Application\Repositories\AreaRepositoryInterface;

final readonly class NetworkInputValidator implements NetworkInputValidatorInterface
{
    public function __construct(
        private AreaRepositoryInterface $areaRepository,
        private CityRepositoryInterface $cityRepository,
        private ProvinceRepositoryInterface $provinceRepository,
    ) {}

    public function validateNodeInput(string $hqId, NodeDetailsDto $input): void
    {
        if (! $this->areaRepository->activeExists($hqId, $input->areaId)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'organization.active_area_current_hq_is_required');
        }
        $address = $input->address;
        if ($address->countryCode !== 'IR') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'organization.only_ir_addresses_are_supported');
        }
        if ($address->cityId !== null) {
            $city = $this->cityRepository->findActive($address->cityId);
            if ($city === null || $address->provinceId !== $city->province_id) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'organization.city_province_must_be_active_canonical_pair');
            }
        } elseif ($address->provinceId !== null && ! $this->provinceRepository->activeExists($address->provinceId)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'organization.province_must_be_active');
        }
    }
}
