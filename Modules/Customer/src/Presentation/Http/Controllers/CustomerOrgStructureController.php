<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Customer\Application\UseCases\CreateCustomerDepartment\CreateCustomerDepartmentHandler;
use Modules\Customer\Application\UseCases\CreateCustomerPosition\CreateCustomerPositionHandler;
use Modules\Customer\Application\UseCases\GetCustomerOrgStructure\GetCustomerOrgStructureHandler;
use Modules\Customer\Application\UseCases\UpdateCustomerDepartment\UpdateCustomerDepartmentHandler;
use Modules\Customer\Application\UseCases\UpdateCustomerPosition\UpdateCustomerPositionHandler;
use Modules\Customer\Presentation\Http\Requests\CreateCustomerDepartmentRequest;
use Modules\Customer\Presentation\Http\Requests\CreateCustomerPositionRequest;
use Modules\Customer\Presentation\Http\Requests\UpdateCustomerDepartmentRequest;
use Modules\Customer\Presentation\Http\Requests\UpdateCustomerPositionRequest;
use Modules\Customer\Presentation\Http\Resources\CustomerDepartmentResource;
use Modules\Customer\Presentation\Http\Resources\CustomerOrgStructureResource;
use Modules\Customer\Presentation\Http\Resources\CustomerPositionResource;
use Modules\Customer\Presentation\Mappers\CustomerCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

/** The department chart of one customer company: the whole tree, a new node, and a change to one. */
final class CustomerOrgStructureController
{
    public function show(Request $request, GetCustomerOrgStructureHandler $handler, string $customerId): JsonResponse
    {
        $structure = $handler->handle(CustomerCommandMapper::orgStructure($request->attributes->get('principal'), $customerId));

        // A company holds a readable number of nodes, so the chart is returned whole rather than by the page.
        return ApiResponder::success($request, new CustomerOrgStructureResource($structure));
    }

    public function storeDepartment(CreateCustomerDepartmentRequest $request, CreateCustomerDepartmentHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::departmentDraft($request->attributes->get('principal'), $customerId, $request->validated()));

        return ApiResponder::success($request, new CustomerDepartmentResource($result->department), status: 201);
    }

    public function updateDepartment(UpdateCustomerDepartmentRequest $request, UpdateCustomerDepartmentHandler $handler, string $customerId, string $departmentId): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::departmentChanges($request->attributes->get('principal'), $customerId, $departmentId, $request->validated()));

        return ApiResponder::success($request, new CustomerDepartmentResource($result->department));
    }

    public function storePosition(CreateCustomerPositionRequest $request, CreateCustomerPositionHandler $handler, string $customerId, string $departmentId): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::positionDraft($request->attributes->get('principal'), $customerId, $departmentId, $request->validated()));

        return ApiResponder::success($request, new CustomerPositionResource($result->position), status: 201);
    }

    public function updatePosition(UpdateCustomerPositionRequest $request, UpdateCustomerPositionHandler $handler, string $customerId, string $positionId): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::positionChanges($request->attributes->get('principal'), $customerId, $positionId, $request->validated()));

        return ApiResponder::success($request, new CustomerPositionResource($result->position));
    }
}
