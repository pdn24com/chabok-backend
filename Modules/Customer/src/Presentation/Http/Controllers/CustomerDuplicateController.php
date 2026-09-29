<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Customer\Application\UseCases\FindCustomerDuplicates\FindCustomerDuplicatesHandler;
use Modules\Customer\Presentation\Http\Requests\FindCustomerDuplicatesRequest;
use Modules\Customer\Presentation\Http\Resources\CustomerDuplicateResource;
use Modules\Customer\Presentation\Mappers\CustomerCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

/** The lookup a lead form runs before it saves: who already holds this mobile number or email address. */
final class CustomerDuplicateController
{
    public function index(FindCustomerDuplicatesRequest $request, FindCustomerDuplicatesHandler $handler): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::duplicateLookup($request->attributes->get('principal'), $request->validated()));

        return ApiResponder::success($request, ['items' => CustomerDuplicateResource::collection($result->duplicates)->resolve($request)]);
    }
}
