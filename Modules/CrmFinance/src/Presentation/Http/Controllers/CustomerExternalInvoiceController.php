<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CrmFinance\Application\UseCases\CreateCustomerExternalInvoice\CreateCustomerExternalInvoiceHandler;
use Modules\CrmFinance\Application\UseCases\ListCustomerExternalInvoices\ListCustomerExternalInvoicesHandler;
use Modules\CrmFinance\Presentation\Http\Requests\CreateExternalInvoiceRequest;
use Modules\CrmFinance\Presentation\Http\Resources\ExternalInvoiceResource;
use Modules\CrmFinance\Presentation\Mappers\FinanceCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

/** References to invoices issued outside the CRM for one customer. */
final class CustomerExternalInvoiceController
{
    public function index(Request $request, ListCustomerExternalInvoicesHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(FinanceCommandMapper::externalInvoiceListing($request->attributes->get('principal'), $customerId));

        return ApiResponder::success($request, ExternalInvoiceResource::collection($result->invoices)->resolve($request));
    }

    public function store(CreateExternalInvoiceRequest $request, CreateCustomerExternalInvoiceHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(FinanceCommandMapper::externalInvoiceDraft($request->attributes->get('principal'), $customerId, $request->validated()));

        return ApiResponder::success($request, new ExternalInvoiceResource($result->invoice), status: 201);
    }
}
