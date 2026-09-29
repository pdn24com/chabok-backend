<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Customer\Application\UseCases\GetCustomerHistory\GetCustomerHistoryHandler;
use Modules\Customer\Application\UseCases\ListCustomerHistoryEntries\ListCustomerHistoryEntriesHandler;
use Modules\Customer\Presentation\Http\Requests\ListCustomerHistoryEntriesRequest;
use Modules\Customer\Presentation\Http\Resources\CustomerHistoryEntryResource;
use Modules\Customer\Presentation\Http\Resources\CustomerHistoryResource;
use Modules\Customer\Presentation\Mappers\CustomerCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

/** Tab 6 of the customer file: the six history cards, one drawer, and the continuous timeline. */
final class CustomerHistoryController
{
    public function show(Request $request, GetCustomerHistoryHandler $handler, string $customerId): JsonResponse
    {
        $history = $handler->handle(CustomerCommandMapper::history($request->attributes->get('principal'), $customerId));

        return ApiResponder::success($request, new CustomerHistoryResource($history));
    }

    public function timeline(ListCustomerHistoryEntriesRequest $request, ListCustomerHistoryEntriesHandler $handler, string $customerId): JsonResponse
    {
        $entries = $handler->handle(CustomerCommandMapper::historyEntries(
            $request->attributes->get('principal'), $customerId, null, $request->validated()));

        return ApiResponder::paginated($request, $entries, fn ($entry): array => (new CustomerHistoryEntryResource($entry))->resolve($request));
    }

    public function category(ListCustomerHistoryEntriesRequest $request, ListCustomerHistoryEntriesHandler $handler, string $customerId, string $category): JsonResponse
    {
        $entries = $handler->handle(CustomerCommandMapper::historyEntries(
            $request->attributes->get('principal'), $customerId, $category, $request->validated()));

        return ApiResponder::paginated($request, $entries, fn ($entry): array => (new CustomerHistoryEntryResource($entry))->resolve($request));
    }
}
