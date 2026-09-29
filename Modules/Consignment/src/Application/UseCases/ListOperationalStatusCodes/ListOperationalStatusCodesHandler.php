<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ListOperationalStatusCodes;

use Modules\Consignment\Application\Repositories\OperationalStatusRepositoryInterface;

final readonly class ListOperationalStatusCodesHandler
{
    public function __construct(
        private OperationalStatusRepositoryInterface $operationalStatusRepository,
    ) {}

    /** @return list<string> */
    public function handle(ListOperationalStatusCodesCommand $command): array
    {
        return $this->operationalStatusRepository->visibleCodes($command->hqId, $command->manifestOnly);
    }
}
