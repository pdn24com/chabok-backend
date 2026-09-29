<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ListNumberRanges;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Consignment\Application\Contracts\NumberRangeAccessInterface;
use Modules\Consignment\Application\Repositories\NumberRangeRepositoryInterface;

final readonly class ListNumberRangesHandler
{
    public function __construct(
        private NumberRangeAccessInterface $numberRangeAccess,
        private NumberRangeRepositoryInterface $numberRangeRepository,
    ) {}

    public function handle(ListNumberRangesCommand $command): LengthAwarePaginator
    {
        $actor = $command->actor;
        $filters = $command->filters;
        $hqId = $this->numberRangeAccess->access($actor, 'consignment.number_range.view');

        return $this->numberRangeRepository->paginate($hqId, $filters->status, $filters->page, $filters->pageSize);
    }
}
