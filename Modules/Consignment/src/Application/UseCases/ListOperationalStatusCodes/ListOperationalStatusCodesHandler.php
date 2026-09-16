<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ListOperationalStatusCodes;

final readonly class ListOperationalStatusCodesHandler
{
    public function __construct(private \Modules\Consignment\Application\Repositories\OperationalStatusRepository $statuses)
    {
    }

    public function handle(ListOperationalStatusCodesCommand $command): ListOperationalStatusCodesResult
    {
        return new ListOperationalStatusCodesResult($this->execute($command->hqId, $command->manifestOnly));
    }

    private function execute(?string $hqId, bool $manifestOnly = false): array
    {
        return $this->statuses->codes($hqId, $manifestOnly);
    }
}
