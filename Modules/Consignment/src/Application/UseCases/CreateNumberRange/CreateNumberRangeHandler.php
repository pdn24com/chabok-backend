<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\CreateNumberRange;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CreateNumberRangeHandler
{
    public function __construct(
        private \Modules\Consignment\Application\Services\NumberRangeAccess $numberRangeAccess,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Consignment\Application\Repositories\NumberRangeRepository $ranges,
        private \Modules\Consignment\Domain\ConsignmentNumberRangeDefinition $definition,
        private \Modules\Consignment\Application\Services\NumberRangeOverlap $numberRangeOverlap,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Foundation\Application\Contracts\OutboxWriter $outbox,
        private \Modules\Consignment\Application\UseCases\GetNumberRange\GetNumberRangeHandler $getNumberRange,
    )
    {
    }

    public function handle(CreateNumberRangeCommand $command): CreateNumberRangeResult
    {
        return new CreateNumberRangeResult($this->execute($command->actor, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        $hqId = $this->numberRangeAccess->access($actor, 'consignment.number_range.manage');
        $rangeId = $this->transactions->run(function () use ($actor, $hqId, $input, $correlationId): string {
            $this->ranges->lockRegistry();
            $preview = $this->definition->validate($input);
            if ($this->numberRangeOverlap->overlaps($preview)) {
                throw new ApiException(ApiErrorCode::ConsignmentNumberRangeOverlap, 409, 'The requested number interval conflicts with an existing range.');
            }
            $rangeId = $this->identifiers->uuid();
            $this->ranges->insert([
                'range_id' => $rangeId,
                'hq_id' => $hqId,
                'title' => trim((string) $input['title']),
                'numeric_prefix' => $preview['numeric_prefix'],
                'total_length' => $preview['total_length'],
                'serial_width' => $preview['serial_width'],
                'serial_start' => $preview['serial_start'],
                'serial_end' => $preview['serial_end'],
                'next_serial' => $preview['serial_start'],
                'first_number' => $preview['first_number'],
                'last_number' => $preview['last_number'],
                'status' => 'AVAILABLE',
                'created_by' => $actor->userId,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $this->audit->write($hqId, $actor->userId, 'CONSIGNMENT_NUMBER_RANGE_CREATED', 'CONSIGNMENT_NUMBER_RANGE', $rangeId, $correlationId, after: [
                'status' => 'AVAILABLE',
                'first_number' => $preview['first_number'],
                'last_number' => $preview['last_number'],
            ], sourceClient: 'BRANCH_PANEL');
            $this->outbox->write($hqId, 'CONSIGNMENT_NUMBER_RANGE', $rangeId, 'consignment.number-range.created', $correlationId, ['range_id' => $rangeId, 'status' => 'AVAILABLE']);
            return $rangeId;
        });
        return $this->getNumberRange->handle(new \Modules\Consignment\Application\UseCases\GetNumberRange\GetNumberRangeCommand($actor, $rangeId))->data;
    }
}
