<?php

declare(strict_types=1);

namespace Modules\Consignment\Application;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Application\Data\Page;

final readonly class ConsignmentNumberRangeService
{
    public function __construct(
        private \Modules\Consignment\Application\UseCases\ValidateNumberRange\ValidateNumberRangeHandler $validateNumberRange,
        private \Modules\Consignment\Application\UseCases\ListNumberRanges\ListNumberRangesHandler $listNumberRanges,
        private \Modules\Consignment\Application\UseCases\GetNumberRange\GetNumberRangeHandler $getNumberRange,
        private \Modules\Consignment\Application\UseCases\CreateNumberRange\CreateNumberRangeHandler $createNumberRange,
        private \Modules\Consignment\Application\UseCases\DisableNumberRange\DisableNumberRangeHandler $disableNumberRange,
        private \Modules\Consignment\Application\UseCases\ListNumberAllocations\ListNumberAllocationsHandler $listNumberAllocations,
        private \Modules\Consignment\Application\Services\NumberRangeProjection $numberRangeProjection,
    )
    {
    }

    public function validate(AuthenticatedPrincipal $actor, array $input): array
    {
        return $this->validateNumberRange->handle(new \Modules\Consignment\Application\UseCases\ValidateNumberRange\ValidateNumberRangeCommand($actor, $input))->data;
    }

    public function ranges(AuthenticatedPrincipal $actor, array $filters): Page
    {
        return $this->listNumberRanges->handle(new \Modules\Consignment\Application\UseCases\ListNumberRanges\ListNumberRangesCommand($actor, $filters))->data;
    }

    public function range(AuthenticatedPrincipal $actor, string $rangeId): array
    {
        return $this->getNumberRange->handle(new \Modules\Consignment\Application\UseCases\GetNumberRange\GetNumberRangeCommand($actor, $rangeId))->data;
    }

    public function create(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        return $this->createNumberRange->handle(new \Modules\Consignment\Application\UseCases\CreateNumberRange\CreateNumberRangeCommand($actor, $input, $correlationId))->data;
    }

    public function disable(AuthenticatedPrincipal $actor, string $rangeId, string $correlationId): array
    {
        return $this->disableNumberRange->handle(new \Modules\Consignment\Application\UseCases\DisableNumberRange\DisableNumberRangeCommand($actor, $rangeId, $correlationId))->data;
    }

    public function allocations(AuthenticatedPrincipal $actor, string $rangeId, array $filters): Page
    {
        return $this->listNumberAllocations->handle(new \Modules\Consignment\Application\UseCases\ListNumberAllocations\ListNumberAllocationsCommand($actor, $rangeId, $filters))->data;
    }

    public function allocationResource(object $row): array
    {
        return $this->numberRangeProjection->allocationResource($row);
    }

    public function resource(object $row): array
    {
        return $this->numberRangeProjection->resource($row);
    }
}
