<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Presentation\Http\ApiResponder;
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
use Modules\Organization\Presentation\Http\Requests\AreasRequest;
use Modules\Organization\Presentation\Http\Requests\CreateAreaRequest;
use Modules\Organization\Presentation\Http\Requests\CreateNodeRequest;
use Modules\Organization\Presentation\Http\Requests\NodesRequest;
use Modules\Organization\Presentation\Http\Requests\UpdateAreaRequest;
use Modules\Organization\Presentation\Http\Requests\UpdateNodeRequest;
use Modules\Organization\Presentation\Http\Resources\NetworkResource;

final class NetworkAdministrationController
{
    public function areas(AreasRequest $request, ListAreasHandler $listAreasHandler): JsonResponse
    {
        $filters = $request->validated();

        return ApiResponder::paginated($request, $listAreasHandler->handle(new ListAreasCommand($request->attributes->get('principal'), NetworkInput::filters($filters))), fn ($row) => (new NetworkResource($row))->resolve($request));
    }

    public function area(Request $request, GetAreaHandler $getAreaHandler, string $areaId): JsonResponse
    {
        return ApiResponder::success($request, (new NetworkResource($getAreaHandler->handle(new GetAreaCommand($request->attributes->get('principal'), $areaId))))->resolve($request));
    }

    public function createArea(CreateAreaRequest $request, CreateAreaHandler $createAreaHandler): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new NetworkResource($createAreaHandler->handle(new CreateAreaCommand($request->attributes->get('principal'), NetworkInput::area($input), (string) $request->attributes->get('correlation_id')))))->resolve($request), status: 201);
    }

    public function updateArea(UpdateAreaRequest $request, UpdateAreaHandler $updateAreaHandler, string $areaId): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new NetworkResource($updateAreaHandler->handle(new UpdateAreaCommand($request->attributes->get('principal'), $areaId, NetworkInput::areaChanges($input), (string) $request->attributes->get('correlation_id')))))->resolve($request));
    }

    public function nodes(NodesRequest $request, ListNodesHandler $listNodesHandler): JsonResponse
    {
        $filters = $request->validated();

        return ApiResponder::paginated($request, $listNodesHandler->handle(new ListNodesCommand($request->attributes->get('principal'), NetworkInput::filters($filters))), fn ($row) => (new NetworkResource($row))->resolve($request));
    }

    public function node(Request $request, GetNodeHandler $getNodeHandler, string $nodeId): JsonResponse
    {
        return ApiResponder::success($request, (new NetworkResource($getNodeHandler->handle(new GetNodeCommand($request->attributes->get('principal'), $nodeId))))->resolve($request));
    }

    public function createNode(CreateNodeRequest $request, CreateNodeHandler $createNodeHandler): JsonResponse
    {
        return ApiResponder::success($request, (new NetworkResource($createNodeHandler->handle(new CreateNodeCommand($request->attributes->get('principal'), NetworkInput::node($request->validated()), (string) $request->attributes->get('correlation_id')))))->resolve($request), status: 201);
    }

    public function updateNode(UpdateNodeRequest $request, UpdateNodeHandler $updateNodeHandler, string $nodeId): JsonResponse
    {
        return ApiResponder::success($request, (new NetworkResource($updateNodeHandler->handle(new UpdateNodeCommand($request->attributes->get('principal'), $nodeId, NetworkInput::nodeChanges($request->validated()), (string) $request->attributes->get('correlation_id')))))->resolve($request));
    }
}
