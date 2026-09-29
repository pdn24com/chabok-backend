<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Customer\Application\UseCases\CreateCompanyRelationship\CreateCompanyRelationshipHandler;
use Modules\Customer\Application\UseCases\EndCustomerRelationship\EndCustomerRelationshipHandler;
use Modules\Customer\Application\UseCases\ListCustomerRelationships\ListCustomerRelationshipsHandler;
use Modules\Customer\Presentation\Http\Requests\CreateCompanyRelationshipRequest;
use Modules\Customer\Presentation\Http\Requests\EndCustomerRelationshipRequest;
use Modules\Customer\Presentation\Http\Resources\RelationshipResource;
use Modules\Customer\Presentation\Mappers\CustomerCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

/** Who holds which role at a company: read from either end, opened from the company, ended by its own ID. */
final class CustomerRelationshipController
{
    public function index(Request $request, ListCustomerRelationshipsHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::relationshipListing(
            $request->attributes->get('principal'), $customerId, $request->boolean('active')));

        return ApiResponder::success($request, ['items' => RelationshipResource::collection($result->relationships)->resolve($request)]);
    }

    public function store(CreateCompanyRelationshipRequest $request, CreateCompanyRelationshipHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::companyRelationship($request->attributes->get('principal'), $customerId, $request->validated()));

        return ApiResponder::success($request, new RelationshipResource($result->relationship), status: 201);
    }

    public function end(EndCustomerRelationshipRequest $request, EndCustomerRelationshipHandler $handler, string $relationshipId): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::relationshipEnd($request->attributes->get('principal'), $relationshipId, $request->validated()));

        return ApiResponder::success($request, new RelationshipResource($result->relationship));
    }
}
