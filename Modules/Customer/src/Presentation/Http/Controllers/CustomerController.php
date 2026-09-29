<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Customer\Application\UseCases\CreateCustomer\CreateCustomerHandler;
use Modules\Customer\Application\UseCases\GetCustomerDetail\GetCustomerDetailHandler;
use Modules\Customer\Application\UseCases\GetCustomerExtendedDetails\GetCustomerExtendedDetailsHandler;
use Modules\Customer\Application\UseCases\GetCustomerFinancialDetails\GetCustomerFinancialDetailsHandler;
use Modules\Customer\Application\UseCases\GetCustomerProfile\GetCustomerProfileHandler;
use Modules\Customer\Application\UseCases\ListCustomers\ListCustomersHandler;
use Modules\Customer\Application\UseCases\SaveCustomerExtendedDetails\SaveCustomerExtendedDetailsHandler;
use Modules\Customer\Application\UseCases\SaveCustomerFinancialDetails\SaveCustomerFinancialDetailsHandler;
use Modules\Customer\Application\UseCases\UpdateCustomerProfile\UpdateCustomerProfileHandler;
use Modules\Customer\Presentation\Http\Requests\CreateCustomerRequest;
use Modules\Customer\Presentation\Http\Requests\ListCustomersRequest;
use Modules\Customer\Presentation\Http\Requests\SaveCustomerExtendedDetailsRequest;
use Modules\Customer\Presentation\Http\Requests\SaveCustomerFinancialDetailsRequest;
use Modules\Customer\Presentation\Http\Requests\UpdateCustomerProfileRequest;
use Modules\Customer\Presentation\Http\Resources\CustomerDetailResource;
use Modules\Customer\Presentation\Http\Resources\CustomerExtendedDetailsResource;
use Modules\Customer\Presentation\Http\Resources\CustomerFinancialDetailsResource;
use Modules\Customer\Presentation\Http\Resources\CustomerListResource;
use Modules\Customer\Presentation\Http\Resources\CustomerProfileResource;
use Modules\Customer\Presentation\Http\Resources\CustomerResource;
use Modules\Customer\Presentation\Mappers\CustomerCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

final class CustomerController
{
    public function index(ListCustomersRequest $request, ListCustomersHandler $handler): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::listing($request->attributes->get('principal'), $request->validated()));

        return ApiResponder::paginated($request, $result->customers, fn ($customer): array => (new CustomerListResource($customer))->resolve($request));
    }

    public function detail(Request $request, GetCustomerDetailHandler $handler, string $customerId): JsonResponse
    {
        $detail = $handler->handle(CustomerCommandMapper::detail($request->attributes->get('principal'), $customerId));

        return ApiResponder::success($request, new CustomerDetailResource($detail));
    }

    public function extendedDetails(Request $request, GetCustomerExtendedDetailsHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::extendedDetails($request->attributes->get('principal'), $customerId));

        return ApiResponder::success($request, new CustomerExtendedDetailsResource($result->details));
    }

    public function saveExtendedDetails(SaveCustomerExtendedDetailsRequest $request, SaveCustomerExtendedDetailsHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::extendedDetailsInput($request->attributes->get('principal'), $customerId, $request->validated()));

        return ApiResponder::success($request, new CustomerExtendedDetailsResource($result->details));
    }

    public function financialDetails(Request $request, GetCustomerFinancialDetailsHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::financialDetails($request->attributes->get('principal'), $customerId));

        return ApiResponder::success($request, new CustomerFinancialDetailsResource($result->details));
    }

    public function saveFinancialDetails(SaveCustomerFinancialDetailsRequest $request, SaveCustomerFinancialDetailsHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::financialDetailsInput($request->attributes->get('principal'), $customerId, $request->validated()));

        return ApiResponder::success($request, new CustomerFinancialDetailsResource($result->details));
    }

    public function profile(Request $request, GetCustomerProfileHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::profile($request->attributes->get('principal'), $customerId));

        return ApiResponder::success($request, new CustomerProfileResource($result->customer));
    }

    public function updateProfile(UpdateCustomerProfileRequest $request, UpdateCustomerProfileHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::profileChanges($request->attributes->get('principal'), $customerId, $request->validated()));

        return ApiResponder::success($request, new CustomerProfileResource($result->customer));
    }

    public function store(CreateCustomerRequest $request, CreateCustomerHandler $handler): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::create($request->attributes->get('principal'), $request->validated()));

        return ApiResponder::success($request, new CustomerResource($result->customer), status: 201);
    }
}
