<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Consignment\Application\Dto\ConsignmentFiltersDto;
use Modules\Consignment\Application\Mappers\ConsignmentInputMapper;
use Modules\Consignment\Application\UseCases\CountConsignmentStatusGroups\CountConsignmentStatusGroupsCommand;
use Modules\Consignment\Application\UseCases\CountConsignmentStatusGroups\CountConsignmentStatusGroupsHandler;
use Modules\Consignment\Application\UseCases\CreateConsignment\CreateConsignmentCommand;
use Modules\Consignment\Application\UseCases\CreateConsignment\CreateConsignmentHandler;
use Modules\Consignment\Application\UseCases\EditConsignment\EditConsignmentCommand;
use Modules\Consignment\Application\UseCases\EditConsignment\EditConsignmentHandler;
use Modules\Consignment\Application\UseCases\GetConsignment\GetConsignmentCommand;
use Modules\Consignment\Application\UseCases\GetConsignment\GetConsignmentHandler;
use Modules\Consignment\Application\UseCases\GetConsignmentFilterOptions\GetConsignmentFilterOptionsCommand;
use Modules\Consignment\Application\UseCases\GetConsignmentFilterOptions\GetConsignmentFilterOptionsHandler;
use Modules\Consignment\Application\UseCases\ListConsignments\ListConsignmentsCommand;
use Modules\Consignment\Application\UseCases\ListConsignments\ListConsignmentsHandler;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Consignment\Presentation\Http\Resources\ConsignmentDetailResource;
use Modules\Consignment\Presentation\Http\Resources\ConsignmentFilterOptionsResource;
use Modules\Consignment\Presentation\Http\Resources\ConsignmentListResource;
use Modules\Consignment\Presentation\Http\Resources\ConsignmentStatusCountsResource;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

/** Test fixture shorthand for focused use cases; no production facade. */
final readonly class ConsignmentFixtures
{
    public function __construct(
        private ListConsignmentsHandler $listConsignments,
        private GetConsignmentFilterOptionsHandler $getConsignmentFilterOptions,
        private CountConsignmentStatusGroupsHandler $countConsignmentStatusGroups,
        private GetConsignmentHandler $getConsignment,
        private CreateConsignmentHandler $createConsignment,
        private EditConsignmentHandler $editConsignment,
    ) {}

    public function list(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        array $filters,
    ): LengthAwarePaginator {
        return $this->listConsignments->handle(new ListConsignmentsCommand($actor, $nodeId, ConsignmentFiltersDto::fromValidated($filters)));
    }

    public function filterOptions(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        return (new ConsignmentFilterOptionsResource($this->getConsignmentFilterOptions->handle(new GetConsignmentFilterOptionsCommand($actor, $nodeId))))->resolve();
    }

    public function statusGroupCounts(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        array $filters,
    ): array {
        return (new ConsignmentStatusCountsResource($this->countConsignmentStatusGroups->handle(new CountConsignmentStatusGroupsCommand($actor, $nodeId, ConsignmentFiltersDto::fromValidated($filters)))))->resolve();
    }

    public function get(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $consignmentId,
    ): array {
        return (new ConsignmentDetailResource($this->getConsignment->handle(new GetConsignmentCommand($actor, $nodeId, $consignmentId))))->resolve();
    }

    public function create(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        array $input,
        string $correlationId,
    ): array {
        return (new ConsignmentDetailResource($this->createConsignment->handle(new CreateConsignmentCommand($actor, $nodeId, ConsignmentInputMapper::creation($input), $correlationId))))->resolve();
    }

    public function edit(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $consignmentId,
        array $changes,
        string $correlationId,
    ): array {
        return (new ConsignmentDetailResource($this->editConsignment->handle(new EditConsignmentCommand($actor, $nodeId, $consignmentId, ConsignmentInputMapper::edit($changes), $correlationId))))->resolve();
    }

    public function listItem(ConsignmentRecord $row): array
    {
        return (new ConsignmentListResource($row))->resolve();
    }
}
