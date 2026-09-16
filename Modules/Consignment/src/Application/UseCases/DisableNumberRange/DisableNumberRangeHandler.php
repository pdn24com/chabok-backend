<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\DisableNumberRange;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class DisableNumberRangeHandler
{
    public function __construct(
        private \Modules\Consignment\Application\Services\NumberRangeAccess $numberRangeAccess,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Consignment\Application\Repositories\NumberRangeRepository $ranges,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Foundation\Application\Contracts\OutboxWriter $outbox,
        private \Modules\Consignment\Application\UseCases\GetNumberRange\GetNumberRangeHandler $getNumberRange,
    )
    {
    }

    public function handle(DisableNumberRangeCommand $command): DisableNumberRangeResult
    {
        return new DisableNumberRangeResult($this->execute($command->actor, $command->rangeId, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $rangeId, string $correlationId): array
    {
        $hqId = $this->numberRangeAccess->access($actor, 'consignment.number_range.manage');
        $this->transactions->run(function () use ($actor, $hqId, $rangeId, $correlationId): void {
            $row = $this->ranges->lock($hqId, $rangeId);
            if ($row === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            if ($row->status !== 'AVAILABLE') {
                throw new ApiException(ApiErrorCode::ConsignmentNumberRangeUnavailable, 422, 'Only an available range can be disabled.');
            }
            $this->ranges->update($hqId, $rangeId, [
                'status' => 'DISABLED',
                'disabled_by' => $actor->userId,
                'disabled_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $this->audit->write($hqId, $actor->userId, 'CONSIGNMENT_NUMBER_RANGE_DISABLED', 'CONSIGNMENT_NUMBER_RANGE', $rangeId, $correlationId, before: ['status' => 'AVAILABLE'], after: ['status' => 'DISABLED'], sourceClient: 'BRANCH_PANEL');
            $this->outbox->write($hqId, 'CONSIGNMENT_NUMBER_RANGE', $rangeId, 'consignment.number-range.disabled', $correlationId, ['range_id' => $rangeId, 'status' => 'DISABLED']);
        });
        return $this->getNumberRange->handle(new \Modules\Consignment\Application\UseCases\GetNumberRange\GetNumberRangeCommand($actor, $rangeId))->data;
    }
}
