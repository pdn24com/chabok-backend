<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class NetworkAdministrationController
{
    public function __construct(
        private \Modules\Organization\Application\Services\NetworkProjection $projection,
        private \Modules\Organization\Application\UseCases\ListAreas\ListAreasHandler $areas,
        private \Modules\Organization\Application\UseCases\GetArea\GetAreaHandler $area,
        private \Modules\Organization\Application\UseCases\CreateArea\CreateAreaHandler $createArea,
        private \Modules\Organization\Application\UseCases\UpdateArea\UpdateAreaHandler $updateArea,
        private \Modules\Organization\Application\UseCases\ListNodes\ListNodesHandler $nodes,
        private \Modules\Organization\Application\UseCases\GetNode\GetNodeHandler $node,
        private \Modules\Organization\Application\UseCases\CreateNode\CreateNodeHandler $createNode,
        private \Modules\Organization\Application\UseCases\UpdateNode\UpdateNodeHandler $updateNode,
    )
    {
    }

    public function areas(\Modules\Organization\Presentation\Http\Requests\AreasRequest $request): JsonResponse
    {
        $filters = $request->validated();
        return ApiResponder::paginated($request, $this->areas->handle(new \Modules\Organization\Application\UseCases\ListAreas\ListAreasCommand($this->actor($request), $filters))->data, fn($row) => $this->projection->areaResource($row));
    }

    public function area(Request $request, string $areaId): JsonResponse
    {
        return ApiResponder::success($request, $this->area->handle(new \Modules\Organization\Application\UseCases\GetArea\GetAreaCommand($this->actor($request), $areaId))->data);
    }

    public function createArea(\Modules\Organization\Presentation\Http\Requests\CreateAreaRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->createArea->handle(new \Modules\Organization\Application\UseCases\CreateArea\CreateAreaCommand($this->actor($request), $input, $this->correlation($request)))->data, status: 201);
    }

    public function updateArea(\Modules\Organization\Presentation\Http\Requests\UpdateAreaRequest $request, string $areaId): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->updateArea->handle(new \Modules\Organization\Application\UseCases\UpdateArea\UpdateAreaCommand($this->actor($request), $areaId, $input, $this->correlation($request)))->data);
    }

    public function nodes(\Modules\Organization\Presentation\Http\Requests\NodesRequest $request): JsonResponse
    {
        $filters = $request->validated();
        return ApiResponder::paginated($request, $this->nodes->handle(new \Modules\Organization\Application\UseCases\ListNodes\ListNodesCommand($this->actor($request), $filters))->data, fn($row) => $this->projection->nodeResource($row));
    }

    public function node(Request $request, string $nodeId): JsonResponse
    {
        return ApiResponder::success($request, $this->node->handle(new \Modules\Organization\Application\UseCases\GetNode\GetNodeCommand($this->actor($request), $nodeId))->data);
    }

    public function createNode(\Modules\Organization\Presentation\Http\Requests\CreateNodeRequest $request): JsonResponse
    {
        return ApiResponder::success($request, $this->createNode->handle(new \Modules\Organization\Application\UseCases\CreateNode\CreateNodeCommand($this->actor($request), $request->validated(), $this->correlation($request)))->data, status: 201);
    }

    public function updateNode(\Modules\Organization\Presentation\Http\Requests\UpdateNodeRequest $request, string $nodeId): JsonResponse
    {
        return ApiResponder::success($request, $this->updateNode->handle(new \Modules\Organization\Application\UseCases\UpdateNode\UpdateNodeCommand($this->actor($request), $nodeId, $request->validated(), $this->correlation($request)))->data);
    }

    private function actor(Request $request): AuthenticatedPrincipal
    {
        return $request->attributes->get('principal');
    }

    private function correlation(Request $request): string
    {
        return (string) $request->attributes->get('correlation_id');
    }
}
