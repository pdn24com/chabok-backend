<?php

declare(strict_types=1);

namespace Modules\Geography\Application\UseCases\ListProvinces;

use Modules\Geography\Application\Repositories\GeographyRepository;
use Modules\Geography\Domain\PersianSearchNormalizer;

final readonly class ListProvincesHandler
{
    public function __construct(private GeographyRepository $geography, private PersianSearchNormalizer $normalizer)
    {
    }

    public function handle(ListProvincesCommand $command): ListProvincesResult
    {
        $filters = $command->filters;
        $term = ($filters['search'] ?? null) === null ? null : $this->normalizer->normalize((string) $filters['search']);
        return new ListProvincesResult($this->geography->provinces($filters, $term));
    }
}
