<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ListNumberRanges;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Application\Data\Page;

final readonly class ListNumberRangesHandler
{
    public function __construct(
        private \Modules\Consignment\Application\Services\NumberRangeAccess $numberRangeAccess,
        private \Modules\Consignment\Application\Repositories\NumberRangeRepository $ranges,
    )
    {
    }

    public function handle(ListNumberRangesCommand $command): ListNumberRangesResult
    {
        return new ListNumberRangesResult($this->execute($command->actor, $command->filters));
    }

    private function execute(AuthenticatedPrincipal $actor, array $filters): Page
    {
        $hqId = $this->numberRangeAccess->access($actor, 'consignment.number_range.view');
        return $this->ranges->paginateRanges($hqId, $filters);
    }
}
