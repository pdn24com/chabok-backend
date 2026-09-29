<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\CrmFinance\Application\UseCases\AllocateReceiptToInvoice\AllocateReceiptToInvoiceHandler;
use Modules\CrmFinance\Application\UseCases\CreateCustomerFinancialEntry\CreateCustomerFinancialEntryHandler;
use Modules\CrmFinance\Application\UseCases\ListCustomerFinancialEntries\ListCustomerFinancialEntriesHandler;
use Modules\CrmFinance\Presentation\Http\Requests\CreateAllocationRequest;
use Modules\CrmFinance\Presentation\Http\Requests\CreateFinancialEntryRequest;
use Modules\CrmFinance\Presentation\Http\Requests\ListFinancialEntriesRequest;
use Modules\CrmFinance\Presentation\Http\Resources\FinancialAllocationResource;
use Modules\CrmFinance\Presentation\Http\Resources\FinancialEntryResource;
use Modules\CrmFinance\Presentation\Mappers\FinanceCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

/** The manual ledger of one customer, and the allocation of one receipt in it against an invoice. */
final class CustomerFinancialEntryController
{
    public function index(ListFinancialEntriesRequest $request, ListCustomerFinancialEntriesHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(FinanceCommandMapper::entryListing($request->attributes->get('principal'), $customerId, $request->validated()));

        return ApiResponder::success($request, FinancialEntryResource::collection($result->entries)->resolve($request));
    }

    public function store(CreateFinancialEntryRequest $request, CreateCustomerFinancialEntryHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(FinanceCommandMapper::entryDraft($request->attributes->get('principal'), $customerId, $request->validated()));

        return ApiResponder::success($request, new FinancialEntryResource($result->entry), status: 201);
    }

    public function allocate(CreateAllocationRequest $request, AllocateReceiptToInvoiceHandler $handler, string $entryId): JsonResponse
    {
        $result = $handler->handle(FinanceCommandMapper::allocationDraft($request->attributes->get('principal'), $entryId, $request->validated()));

        return ApiResponder::success($request, new FinancialAllocationResource($result->allocation), status: 201);
    }
}
