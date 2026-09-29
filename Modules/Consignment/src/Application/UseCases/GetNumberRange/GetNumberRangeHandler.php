<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\GetNumberRange;

use Modules\Consignment\Application\Contracts\NumberRangeAccessInterface;
use Modules\Consignment\Application\Repositories\NumberRangeRepositoryInterface;
use Modules\Consignment\Infrastructure\Persistence\Models\NumberRangeRecord;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class GetNumberRangeHandler
{
    public function __construct(
        private NumberRangeAccessInterface $numberRangeAccess,
        private NumberRangeRepositoryInterface $numberRangeRepository,
    ) {}

    public function handle(GetNumberRangeCommand $command): NumberRangeRecord
    {
        $actor = $command->actor;
        $rangeId = $command->rangeId;
        $hqId = $this->numberRangeAccess->access($actor, 'consignment.number_range.view');
        $row = $this->numberRangeRepository->findByTenant($hqId, $rangeId);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $row;
    }
}
