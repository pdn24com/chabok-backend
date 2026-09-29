<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Customer\Application\UseCases\ListCustomerContactPoints\ListCustomerContactPointsHandler;
use Modules\Customer\Application\UseCases\SaveCustomerContactPoints\SaveCustomerContactPointsHandler;
use Modules\Customer\Presentation\Http\Requests\SaveCustomerContactPointsRequest;
use Modules\Customer\Presentation\Http\Resources\ContactPointResource;
use Modules\Customer\Presentation\Mappers\CustomerCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

/** The channels of one person: the whole set, and its replacement. */
final class CustomerContactPointController
{
    public function index(Request $request, ListCustomerContactPointsHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::contactPointListing($request->attributes->get('principal'), $customerId));

        // A person owns a handful of channels, so the set is returned whole rather than by the page.
        return ApiResponder::success($request, ['items' => ContactPointResource::collection($result->contactPoints)->resolve($request)]);
    }

    public function save(SaveCustomerContactPointsRequest $request, SaveCustomerContactPointsHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::contactPointSet($request->attributes->get('principal'), $customerId, $request->validated()));

        return ApiResponder::success($request, ['items' => ContactPointResource::collection($result->contactPoints)->resolve($request)]);
    }
}
