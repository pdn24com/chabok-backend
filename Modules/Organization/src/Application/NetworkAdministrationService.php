<?php

declare(strict_types=1);

namespace Modules\Organization\Application;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Application\Data\Page;

final readonly class NetworkAdministrationService
{
    public function __construct(
        private \Modules\Organization\Application\UseCases\ListAreas\ListAreasHandler $listAreas,
        private \Modules\Organization\Application\UseCases\GetArea\GetAreaHandler $getArea,
        private \Modules\Organization\Application\UseCases\CreateArea\CreateAreaHandler $createArea,
        private \Modules\Organization\Application\UseCases\UpdateArea\UpdateAreaHandler $updateArea,
        private \Modules\Organization\Application\UseCases\ListNodes\ListNodesHandler $listNodes,
        private \Modules\Organization\Application\UseCases\GetNode\GetNodeHandler $getNode,
        private \Modules\Organization\Application\UseCases\CreateNode\CreateNodeHandler $createNode,
        private \Modules\Organization\Application\UseCases\UpdateNode\UpdateNodeHandler $updateNode,
        private \Modules\Organization\Application\Services\NetworkProjection $networkProjection,
    )
    {
    }

    public function areas(AuthenticatedPrincipal $actor, array $filters): Page
    {
        return $this->listAreas->handle(new \Modules\Organization\Application\UseCases\ListAreas\ListAreasCommand($actor, $filters))->data;
    }

    public function area(AuthenticatedPrincipal $actor, string $areaId): array
    {
        return $this->getArea->handle(new \Modules\Organization\Application\UseCases\GetArea\GetAreaCommand($actor, $areaId))->data;
    }

    public function createArea(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        return $this->createArea->handle(new \Modules\Organization\Application\UseCases\CreateArea\CreateAreaCommand($actor, $input, $correlationId))->data;
    }

    public function updateArea(AuthenticatedPrincipal $actor, string $areaId, array $input, string $correlationId): array
    {
        return $this->updateArea->handle(new \Modules\Organization\Application\UseCases\UpdateArea\UpdateAreaCommand($actor, $areaId, $input, $correlationId))->data;
    }

    public function nodes(AuthenticatedPrincipal $actor, array $filters): Page
    {
        return $this->listNodes->handle(new \Modules\Organization\Application\UseCases\ListNodes\ListNodesCommand($actor, $filters))->data;
    }

    public function node(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        return $this->getNode->handle(new \Modules\Organization\Application\UseCases\GetNode\GetNodeCommand($actor, $nodeId))->data;
    }

    public function createNode(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        return $this->createNode->handle(new \Modules\Organization\Application\UseCases\CreateNode\CreateNodeCommand($actor, $input, $correlationId))->data;
    }

    public function updateNode(AuthenticatedPrincipal $actor, string $nodeId, array $input, string $correlationId): array
    {
        return $this->updateNode->handle(new \Modules\Organization\Application\UseCases\UpdateNode\UpdateNodeCommand($actor, $nodeId, $input, $correlationId))->data;
    }

    public function areaResource(object $row): array
    {
        return $this->networkProjection->areaResource($row);
    }

    public function nodeResource(object $row): array
    {
        return $this->networkProjection->nodeResource($row);
    }
}
