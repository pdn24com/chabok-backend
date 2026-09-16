<?php

declare(strict_types=1);

namespace Modules\Consignment\Application;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class OperationalStatusCatalog
{
    public function __construct(
        private \Modules\Consignment\Application\UseCases\ListOperationalStatuses\ListOperationalStatusesHandler $listOperationalStatuses,
        private \Modules\Consignment\Application\UseCases\ListOperationalStatusCodes\ListOperationalStatusCodesHandler $listOperationalStatusCodes,
        private \Modules\Consignment\Application\UseCases\SaveOperationalStatus\SaveOperationalStatusHandler $saveOperationalStatus,
    )
    {
    }

    public function entries(AuthenticatedPrincipal $actor): array
    {
        return $this->listOperationalStatuses->handle(new \Modules\Consignment\Application\UseCases\ListOperationalStatuses\ListOperationalStatusesCommand($actor))->data;
    }

    public function codes(?string $hqId, bool $manifestOnly = false): array
    {
        return $this->listOperationalStatusCodes->handle(new \Modules\Consignment\Application\UseCases\ListOperationalStatusCodes\ListOperationalStatusCodesCommand($hqId, $manifestOnly))->data;
    }

    public function save(AuthenticatedPrincipal $actor, ?string $id, array $input, string $correlation): array
    {
        return $this->saveOperationalStatus->handle(new \Modules\Consignment\Application\UseCases\SaveOperationalStatus\SaveOperationalStatusCommand($actor, $id, $input, $correlation))->data;
    }
}
