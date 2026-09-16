<?php

declare(strict_types=1);

namespace Modules\Consignment\Application;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ConsignmentService
{
    public function __construct(
        private \Modules\Consignment\Application\UseCases\ListConsignments\ListConsignmentsHandler $listConsignments,
        private \Modules\Consignment\Application\UseCases\GetConsignmentFilterOptions\GetConsignmentFilterOptionsHandler $getConsignmentFilterOptions,
        private \Modules\Consignment\Application\UseCases\CountConsignmentStatusGroups\CountConsignmentStatusGroupsHandler $countConsignmentStatusGroups,
        private \Modules\Consignment\Application\UseCases\GetConsignment\GetConsignmentHandler $getConsignment,
        private \Modules\Consignment\Application\UseCases\CreateConsignment\CreateConsignmentHandler $createConsignment,
        private \Modules\Consignment\Application\UseCases\EditConsignment\EditConsignmentHandler $editConsignment,
        private \Modules\Consignment\Application\Services\ConsignmentProjection $consignmentProjection,
    )
    {
    }

    public function list(AuthenticatedPrincipal $actor, string $nodeId, array $filters): Page
    {
        return $this->listConsignments->handle(new \Modules\Consignment\Application\UseCases\ListConsignments\ListConsignmentsCommand($actor, $nodeId, $filters))->data;
    }

    public function filterOptions(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        return $this->getConsignmentFilterOptions->handle(new \Modules\Consignment\Application\UseCases\GetConsignmentFilterOptions\GetConsignmentFilterOptionsCommand($actor, $nodeId))->data;
    }

    public function statusGroupCounts(AuthenticatedPrincipal $actor, string $nodeId, array $filters): array
    {
        return $this->countConsignmentStatusGroups->handle(new \Modules\Consignment\Application\UseCases\CountConsignmentStatusGroups\CountConsignmentStatusGroupsCommand($actor, $nodeId, $filters))->data;
    }

    public function get(AuthenticatedPrincipal $actor, string $nodeId, string $consignmentId): array
    {
        return $this->getConsignment->handle(new \Modules\Consignment\Application\UseCases\GetConsignment\GetConsignmentCommand($actor, $nodeId, $consignmentId))->data;
    }

    public function create(AuthenticatedPrincipal $actor, string $nodeId, array $input, string $correlationId): array
    {
        return $this->createConsignment->handle(new \Modules\Consignment\Application\UseCases\CreateConsignment\CreateConsignmentCommand($actor, $nodeId, $input, $correlationId))->data;
    }

    public function edit(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $consignmentId,
        array $changes,
        string $correlationId,
    ): array
    {
        return $this->editConsignment->handle(new \Modules\Consignment\Application\UseCases\EditConsignment\EditConsignmentCommand($actor, $nodeId, $consignmentId, $changes, $correlationId))->data;
    }

    public function listItem(array $row): array
    {
        return $this->consignmentProjection->listItem($row);
    }
}
