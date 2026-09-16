<?php

declare(strict_types=1);

namespace Modules\Geography\Application\UseCases\ListCities;

use Modules\Geography\Application\Repositories\GeographyRepository;
use Modules\Geography\Domain\PersianSearchNormalizer;

final readonly class ListCitiesHandler
{
    public function __construct(private GeographyRepository $geography, private PersianSearchNormalizer $normalizer)
    {
    }

    public function handle(ListCitiesCommand $command): ListCitiesResult
    {
        $filters = $command->filters;
        $term = ($filters['search'] ?? null) === null ? null : $this->normalizer->normalize((string) $filters['search']);
        return new ListCitiesResult($this->geography->cities($filters, $term));
    }
}
