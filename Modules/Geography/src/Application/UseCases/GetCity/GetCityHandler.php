<?php

declare(strict_types=1);

namespace Modules\Geography\Application\UseCases\GetCity;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Geography\Application\Repositories\CityRepositoryInterface;
use Modules\Geography\Infrastructure\Persistence\Models\CityRecord;

final readonly class GetCityHandler
{
    public function __construct(private CityRepositoryInterface $cityRepository) {}

    public function handle(GetCityCommand $command): CityRecord
    {
        $row = $this->cityRepository->findWithProvince($command->cityId);
        if ($row === null || $command->activeOnly && (! (bool) $row->is_active || ! (bool) $row->province->is_active)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $row;
    }
}
