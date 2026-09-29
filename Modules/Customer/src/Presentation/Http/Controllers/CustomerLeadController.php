<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Customer\Application\UseCases\ChangeCustomerLifecycle\ChangeCustomerLifecycleHandler;
use Modules\Customer\Application\UseCases\ConvertLead\ConvertLeadHandler;
use Modules\Customer\Presentation\Http\Requests\ChangeCustomerLifecycleRequest;
use Modules\Customer\Presentation\Http\Requests\ConvertLeadRequest;
use Modules\Customer\Presentation\Http\Resources\CustomerConversionResource;
use Modules\Customer\Presentation\Http\Resources\CustomerLifecycleResource;
use Modules\Customer\Presentation\Mappers\CustomerCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

/** The two decisions taken on a lead: promote it to a customer, or close it (and reopen it) with a reason. */
final class CustomerLeadController
{
    public function convert(ConvertLeadRequest $request, ConvertLeadHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::leadConversion($request->attributes->get('principal'), $customerId, $request->validated()));

        return ApiResponder::success($request, new CustomerConversionResource($result->customer));
    }

    public function changeLifecycle(ChangeCustomerLifecycleRequest $request, ChangeCustomerLifecycleHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::lifecycleChange($request->attributes->get('principal'), $customerId, $request->validated()));

        return ApiResponder::success($request, new CustomerLifecycleResource($result));
    }
}
