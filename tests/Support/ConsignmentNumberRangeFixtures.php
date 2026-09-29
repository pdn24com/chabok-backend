<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Consignment\Application\Dto\NumberRangeCreationDto;
use Modules\Consignment\Application\Dto\NumberRangeFiltersDto;
use Modules\Consignment\Application\UseCases\CreateNumberRange\CreateNumberRangeCommand;
use Modules\Consignment\Application\UseCases\CreateNumberRange\CreateNumberRangeHandler;
use Modules\Consignment\Application\UseCases\DisableNumberRange\DisableNumberRangeCommand;
use Modules\Consignment\Application\UseCases\DisableNumberRange\DisableNumberRangeHandler;
use Modules\Consignment\Application\UseCases\GetNumberRange\GetNumberRangeCommand;
use Modules\Consignment\Application\UseCases\GetNumberRange\GetNumberRangeHandler;
use Modules\Consignment\Application\UseCases\ListNumberAllocations\ListNumberAllocationsCommand;
use Modules\Consignment\Application\UseCases\ListNumberAllocations\ListNumberAllocationsHandler;
use Modules\Consignment\Application\UseCases\ListNumberRanges\ListNumberRangesCommand;
use Modules\Consignment\Application\UseCases\ListNumberRanges\ListNumberRangesHandler;
use Modules\Consignment\Application\UseCases\ValidateNumberRange\ValidateNumberRangeCommand;
use Modules\Consignment\Application\UseCases\ValidateNumberRange\ValidateNumberRangeHandler;
use Modules\Consignment\Domain\ValueObjects\NumberRangeInput;
use Modules\Consignment\Presentation\Http\Resources\NumberAllocationResource;
use Modules\Consignment\Presentation\Http\Resources\NumberRangeResource;
use Modules\Consignment\Presentation\Http\Resources\NumberRangeValidationResource;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

/** Test fixture shorthand for focused use cases; no production facade. */
final readonly class ConsignmentNumberRangeFixtures
{
    public function __construct(
        private ValidateNumberRangeHandler $validateNumberRange,
        private ListNumberRangesHandler $listNumberRanges,
        private GetNumberRangeHandler $getNumberRange,
        private CreateNumberRangeHandler $createNumberRange,
        private DisableNumberRangeHandler $disableNumberRange,
        private ListNumberAllocationsHandler $listNumberAllocations,
    ) {}

    public function validate(AuthenticatedPrincipal $actor, array $input): array
    {
        return (new NumberRangeValidationResource($this->validateNumberRange->handle(new ValidateNumberRangeCommand($actor, NumberRangeInput::fromValidated($input)))))->resolve();
    }

    public function ranges(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        return $this->listNumberRanges->handle(new ListNumberRangesCommand($actor, NumberRangeFiltersDto::fromValidated($filters)));
    }

    public function range(AuthenticatedPrincipal $actor, string $rangeId): array
    {
        return (new NumberRangeResource($this->getNumberRange->handle(new GetNumberRangeCommand($actor, $rangeId))))->resolve();
    }

    public function create(
        AuthenticatedPrincipal $actor,
        array $input,
        string $correlationId,
    ): array {
        return (new NumberRangeResource($this->createNumberRange->handle(new CreateNumberRangeCommand($actor, NumberRangeCreationDto::fromValidated($input), $correlationId))))->resolve();
    }

    public function disable(
        AuthenticatedPrincipal $actor,
        string $rangeId,
        string $correlationId,
    ): array {
        return (new NumberRangeResource($this->disableNumberRange->handle(new DisableNumberRangeCommand($actor, $rangeId, $correlationId))))->resolve();
    }

    public function allocations(
        AuthenticatedPrincipal $actor,
        string $rangeId,
        array $filters,
    ): LengthAwarePaginator {
        return $this->listNumberAllocations->handle(new ListNumberAllocationsCommand($actor, $rangeId, NumberRangeFiltersDto::fromValidated($filters)));
    }

    public function allocationResource(object $row): array
    {
        return (new NumberAllocationResource($row))->resolve();
    }

    public function resource(object $row): array
    {
        return (new NumberRangeResource($row))->resolve();
    }
}
