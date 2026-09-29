<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Customer\Application\UseCases\ListCustomerIndustries\ListCustomerIndustriesHandler;
use Modules\Customer\Application\UseCases\SaveCustomerIndustries\SaveCustomerIndustriesHandler;
use Modules\Customer\Presentation\Http\Requests\SaveCustomerIndustriesRequest;
use Modules\Customer\Presentation\Http\Resources\CustomerIndustryItemResource;
use Modules\Customer\Presentation\Mappers\CustomerCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

/** The industries of one customer, one of them primary: the whole set, and its replacement. */
final class CustomerIndustryController
{
    public function index(Request $request, ListCustomerIndustriesHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::industryListing($request->attributes->get('principal'), $customerId));

        return ApiResponder::success($request, ['items' => CustomerIndustryItemResource::collection($result->industries)->resolve($request)]);
    }

    public function save(SaveCustomerIndustriesRequest $request, SaveCustomerIndustriesHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::industrySet($request->attributes->get('principal'), $customerId, $request->validated()));

        return ApiResponder::success($request, ['items' => CustomerIndustryItemResource::collection($result->industries)->resolve($request)]);
    }
}
