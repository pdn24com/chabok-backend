<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ListNumberAllocations;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Application\Data\Page;

final readonly class ListNumberAllocationsHandler
{
    public function __construct(
        private \Modules\Consignment\Application\Services\NumberRangeAccess $numberRangeAccess,
        private \Modules\Consignment\Application\Repositories\NumberRangeRepository $ranges,
    )
    {
    }

    public function handle(ListNumberAllocationsCommand $command): ListNumberAllocationsResult
    {
        return new ListNumberAllocationsResult($this->execute($command->actor, $command->rangeId, $command->filters));
    }

    private function execute(AuthenticatedPrincipal $actor, string $rangeId, array $filters): Page
    {
        $hqId = $this->numberRangeAccess->access($actor, 'consignment.number_range.view');
        if (!$this->ranges->exists($hqId, $rangeId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return $this->ranges->paginateAllocations($hqId, $rangeId, $filters);
    }
}
