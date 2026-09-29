<?php

declare(strict_types=1);

namespace Modules\Geography\Application\UseCases\ListCities;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Geography\Application\Repositories\CityRepositoryInterface;
use Modules\Geography\Domain\Support\PersianSearchNormalizer;

final readonly class ListCitiesHandler
{
    public function __construct(
        private PersianSearchNormalizer $normalizer,
        private CityRepositoryInterface $cityRepository,
    ) {}

    public function handle(ListCitiesCommand $command): LengthAwarePaginator
    {
        $filters = $command->filters;

        return $this->cityRepository->search($filters, $filters->search === null ? null : $this->normalizer->normalize($filters->search));
    }
}
