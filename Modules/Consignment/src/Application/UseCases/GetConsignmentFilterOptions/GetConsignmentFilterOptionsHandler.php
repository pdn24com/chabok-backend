<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\GetConsignmentFilterOptions;

use Modules\Consignment\Application\Contracts\ConsignmentAccessGuardInterface;
use Modules\Consignment\Application\Repositories\ConsignmentRepositoryInterface;

final readonly class GetConsignmentFilterOptionsHandler
{
    public function __construct(
        private ConsignmentAccessGuardInterface $consignmentAccessGuard,
        private ConsignmentRepositoryInterface $consignmentRepository,
    ) {}

    public function handle(GetConsignmentFilterOptionsCommand $command): GetConsignmentFilterOptionsResult
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $this->consignmentAccessGuard->assertAccess($actor, $nodeId, 'consignment.view');

        $options = $this->consignmentRepository->driverOptions((string) $actor->hqId, [$nodeId]);

        return new GetConsignmentFilterOptionsResult($options->pickupDrivers, $options->deliveryDrivers);
    }
}
