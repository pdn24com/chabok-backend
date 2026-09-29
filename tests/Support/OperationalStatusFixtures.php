<?php

declare(strict_types=1);

namespace Tests\Support;

use Modules\Consignment\Application\Dto\OperationalStatusDto;
use Modules\Consignment\Application\UseCases\ListOperationalStatusCodes\ListOperationalStatusCodesCommand;
use Modules\Consignment\Application\UseCases\ListOperationalStatusCodes\ListOperationalStatusCodesHandler;
use Modules\Consignment\Application\UseCases\ListOperationalStatuses\ListOperationalStatusesCommand;
use Modules\Consignment\Application\UseCases\ListOperationalStatuses\ListOperationalStatusesHandler;
use Modules\Consignment\Application\UseCases\SaveOperationalStatus\SaveOperationalStatusCommand;
use Modules\Consignment\Application\UseCases\SaveOperationalStatus\SaveOperationalStatusHandler;
use Modules\Consignment\Presentation\Http\Resources\OperationalStatusResource;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class OperationalStatusFixtures
{
    public function __construct(
        private ListOperationalStatusesHandler $listOperationalStatuses,
        private ListOperationalStatusCodesHandler $listOperationalStatusCodes,
        private SaveOperationalStatusHandler $saveOperationalStatus,
    ) {}

    public function entries(AuthenticatedPrincipal $actor): array
    {
        return OperationalStatusResource::collection($this->listOperationalStatuses->handle(new ListOperationalStatusesCommand($actor)))->resolve();
    }

    public function codes(?string $hqId, bool $manifestOnly = false): array
    {
        return $this->listOperationalStatusCodes->handle(new ListOperationalStatusCodesCommand($hqId, $manifestOnly));
    }

    public function save(
        AuthenticatedPrincipal $actor,
        ?string $id,
        array $input,
        string $correlation,
    ): array {
        return (new OperationalStatusResource($this->saveOperationalStatus->handle(new SaveOperationalStatusCommand($actor, $id, OperationalStatusDto::fromValidated($input), $correlation))))->resolve();
    }
}
