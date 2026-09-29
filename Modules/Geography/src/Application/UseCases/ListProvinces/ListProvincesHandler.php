<?php

declare(strict_types=1);

namespace Modules\Geography\Application\UseCases\ListProvinces;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Geography\Application\Repositories\ProvinceRepositoryInterface;
use Modules\Geography\Domain\Support\PersianSearchNormalizer;

final readonly class ListProvincesHandler
{
    public function __construct(
        private PersianSearchNormalizer $normalizer,
        private ProvinceRepositoryInterface $provinceRepository,
    ) {}

    public function handle(ListProvincesCommand $command): LengthAwarePaginator
    {
        $filters = $command->filters;

        return $this->provinceRepository->search($filters, $filters->search === null ? null : $this->normalizer->normalize($filters->search));
    }
}
