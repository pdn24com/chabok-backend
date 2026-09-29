<?php

declare(strict_types=1);

namespace Modules\Geography\Application\UseCases\ListCountries;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Geography\Application\Repositories\CountryRepositoryInterface;
use Modules\Geography\Domain\Support\PersianSearchNormalizer;

final readonly class ListCountriesHandler
{
    public function __construct(
        private PersianSearchNormalizer $normalizer,
        private CountryRepositoryInterface $countryRepository,
    ) {}

    public function handle(ListCountriesCommand $command): LengthAwarePaginator
    {
        $filters = $command->filters;

        return $this->countryRepository->search($filters, $filters->search === null ? null : $this->normalizer->normalize($filters->search));
    }
}
