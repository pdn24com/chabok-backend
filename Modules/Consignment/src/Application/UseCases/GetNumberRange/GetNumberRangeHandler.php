<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\GetNumberRange;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class GetNumberRangeHandler
{
    public function __construct(
        private \Modules\Consignment\Application\Services\NumberRangeAccess $numberRangeAccess,
        private \Modules\Consignment\Application\Repositories\NumberRangeRepository $ranges,
        private \Modules\Consignment\Application\Services\NumberRangeProjection $numberRangeProjection,
    )
    {
    }

    public function handle(GetNumberRangeCommand $command): GetNumberRangeResult
    {
        return new GetNumberRangeResult($this->execute($command->actor, $command->rangeId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $rangeId): array
    {
        $hqId = $this->numberRangeAccess->access($actor, 'consignment.number_range.view');
        $row = $this->ranges->find($hqId, $rangeId);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return $this->numberRangeProjection->resource($row);
    }
}
