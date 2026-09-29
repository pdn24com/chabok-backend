<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Customer\Application\UseCases\CreateCustomerAddress\CreateCustomerAddressHandler;
use Modules\Customer\Application\UseCases\GetCustomerAddress\GetCustomerAddressHandler;
use Modules\Customer\Application\UseCases\ListCustomerAddresses\ListCustomerAddressesHandler;
use Modules\Customer\Application\UseCases\UpdateCustomerAddress\UpdateCustomerAddressHandler;
use Modules\Customer\Presentation\Http\Requests\CreateCustomerAddressRequest;
use Modules\Customer\Presentation\Http\Requests\UpdateCustomerAddressRequest;
use Modules\Customer\Presentation\Http\Resources\CustomerAddressResource;
use Modules\Customer\Presentation\Mappers\CustomerCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

/** The address book of one customer: the whole list, one entry, a new entry and a change to one. */
final class CustomerAddressController
{
    public function index(Request $request, ListCustomerAddressesHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::addressListing($request->attributes->get('principal'), $customerId));

        // A customer owns a handful of addresses, so the book is returned whole rather than by the page.
        return ApiResponder::success($request, CustomerAddressResource::collection($result->addresses)->resolve($request));
    }

    public function show(Request $request, GetCustomerAddressHandler $handler, string $customerId, string $addressId): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::address($request->attributes->get('principal'), $customerId, $addressId));

        return ApiResponder::success($request, new CustomerAddressResource($result->address));
    }

    public function store(CreateCustomerAddressRequest $request, CreateCustomerAddressHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::addressDraft($request->attributes->get('principal'), $customerId, $request->validated()));

        return ApiResponder::success($request, new CustomerAddressResource($result->address), status: 201);
    }

    public function update(UpdateCustomerAddressRequest $request, UpdateCustomerAddressHandler $handler, string $customerId, string $addressId): JsonResponse
    {
        $result = $handler->handle(CustomerCommandMapper::addressChanges($request->attributes->get('principal'), $customerId, $addressId, $request->validated()));

        return ApiResponder::success($request, new CustomerAddressResource($result->address));
    }
}
