<?php

declare(strict_types=1);

namespace Modules\Geography\Application\UseCases\GetCountry;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Geography\Application\Repositories\CountryRepositoryInterface;
use Modules\Geography\Infrastructure\Persistence\Models\CountryRecord;

final readonly class GetCountryHandler
{
    public function __construct(private CountryRepositoryInterface $countryRepository) {}

    public function handle(GetCountryCommand $command): CountryRecord
    {
        $country = $this->countryRepository->find($command->countryId);
        if ($country === null || ! $country->is_active) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $country;
    }
}
