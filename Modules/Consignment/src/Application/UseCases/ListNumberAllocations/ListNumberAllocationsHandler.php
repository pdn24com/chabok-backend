<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ListNumberAllocations;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Consignment\Application\Contracts\NumberRangeAccessInterface;
use Modules\Consignment\Application\Repositories\NumberRangeRepositoryInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class ListNumberAllocationsHandler
{
    public function __construct(
        private NumberRangeAccessInterface $numberRangeAccess,
        private NumberRangeRepositoryInterface $numberRangeRepository,
    ) {}

    public function handle(ListNumberAllocationsCommand $command): LengthAwarePaginator
    {
        $actor = $command->actor;
        $rangeId = $command->rangeId;
        $filters = $command->filters;
        $hqId = $this->numberRangeAccess->access($actor, 'consignment.number_range.view');
        if (! $this->numberRangeRepository->existsInTenant($hqId, $rangeId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $this->numberRangeRepository->paginateAllocations($hqId, $rangeId, $filters->page, $filters->pageSize);
    }
}
