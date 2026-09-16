<?php

declare(strict_types=1);

namespace Modules\Geography\Application\UseCases\GetCity;

use Modules\Geography\Application\Repositories\GeographyRepository;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Geography\Application\Data\GeographyData;

final readonly class GetCityHandler
{
    public function __construct(private GeographyRepository $geography)
    {
    }

    public function handle(GetCityCommand $command): GetCityResult
    {
        $row = $this->geography->city($command->cityId);
        if ($row === null || $command->activeOnly && (!(bool) $row->is_active || !(bool) $row->province_active)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return new GetCityResult(GeographyData::cityResource((array) $row));
    }
}
