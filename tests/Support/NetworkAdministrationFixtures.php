<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Organization\Application\Mappers\NetworkInput;
use Modules\Organization\Application\UseCases\CreateArea\CreateAreaCommand;
use Modules\Organization\Application\UseCases\CreateArea\CreateAreaHandler;
use Modules\Organization\Application\UseCases\CreateNode\CreateNodeCommand;
use Modules\Organization\Application\UseCases\CreateNode\CreateNodeHandler;
use Modules\Organization\Application\UseCases\GetArea\GetAreaCommand;
use Modules\Organization\Application\UseCases\GetArea\GetAreaHandler;
use Modules\Organization\Application\UseCases\GetNode\GetNodeCommand;
use Modules\Organization\Application\UseCases\GetNode\GetNodeHandler;
use Modules\Organization\Application\UseCases\ListAreas\ListAreasCommand;
use Modules\Organization\Application\UseCases\ListAreas\ListAreasHandler;
use Modules\Organization\Application\UseCases\ListNodes\ListNodesCommand;
use Modules\Organization\Application\UseCases\ListNodes\ListNodesHandler;
use Modules\Organization\Application\UseCases\UpdateArea\UpdateAreaCommand;
use Modules\Organization\Application\UseCases\UpdateArea\UpdateAreaHandler;
use Modules\Organization\Application\UseCases\UpdateNode\UpdateNodeCommand;
use Modules\Organization\Application\UseCases\UpdateNode\UpdateNodeHandler;
use Modules\Organization\Infrastructure\Persistence\Models\AreaRecord;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;
use Modules\Organization\Presentation\Http\Resources\NetworkResource;

/** Test fixture shorthand for focused use cases; no production facade. */
final readonly class NetworkAdministrationFixtures
{
    public function __construct(
        private ListAreasHandler $listAreas,
        private GetAreaHandler $getArea,
        private CreateAreaHandler $createArea,
        private UpdateAreaHandler $updateArea,
        private ListNodesHandler $listNodes,
        private GetNodeHandler $getNode,
        private CreateNodeHandler $createNode,
        private UpdateNodeHandler $updateNode,
    ) {}

    public function areas(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        return $this->listAreas->handle(new ListAreasCommand($actor, NetworkInput::filters($filters)));
    }

    public function area(AuthenticatedPrincipal $actor, string $areaId): array
    {
        return (new NetworkResource($this->getArea->handle(new GetAreaCommand($actor, $areaId))))->resolve();
    }

    public function createArea(
        AuthenticatedPrincipal $actor,
        array $input,
        string $correlationId,
    ): array {
        return (new NetworkResource($this->createArea->handle(new CreateAreaCommand($actor, NetworkInput::area($input), $correlationId))))->resolve();
    }

    public function updateArea(
        AuthenticatedPrincipal $actor,
        string $areaId,
        array $input,
        string $correlationId,
    ): array {
        return (new NetworkResource($this->updateArea->handle(new UpdateAreaCommand($actor, $areaId, NetworkInput::areaChanges($input), $correlationId))))->resolve();
    }

    public function nodes(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        return $this->listNodes->handle(new ListNodesCommand($actor, NetworkInput::filters($filters)));
    }

    public function node(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        return (new NetworkResource($this->getNode->handle(new GetNodeCommand($actor, $nodeId))))->resolve();
    }

    public function createNode(
        AuthenticatedPrincipal $actor,
        array $input,
        string $correlationId,
    ): array {
        return (new NetworkResource($this->createNode->handle(new CreateNodeCommand($actor, NetworkInput::node($input), $correlationId))))->resolve();
    }

    public function updateNode(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        array $input,
        string $correlationId,
    ): array {
        return (new NetworkResource($this->updateNode->handle(new UpdateNodeCommand($actor, $nodeId, NetworkInput::nodeChanges($input), $correlationId))))->resolve();
    }

    public function areaResource(AreaRecord $row): array
    {
        return (new NetworkResource($row))->resolve();
    }

    public function nodeResource(NodeRecord $row): array
    {
        return (new NetworkResource($row))->resolve();
    }
}
