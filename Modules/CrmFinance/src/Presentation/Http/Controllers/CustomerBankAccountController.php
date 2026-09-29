<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CrmFinance\Application\UseCases\CreateCustomerBankAccount\CreateCustomerBankAccountHandler;
use Modules\CrmFinance\Application\UseCases\ListCustomerBankAccounts\ListCustomerBankAccountsHandler;
use Modules\CrmFinance\Presentation\Http\Requests\CreateBankAccountRequest;
use Modules\CrmFinance\Presentation\Http\Resources\BankAccountResource;
use Modules\CrmFinance\Presentation\Mappers\FinanceCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

/** The bank accounts of one customer: the whole set and a new entry, both answered masked. */
final class CustomerBankAccountController
{
    public function index(Request $request, ListCustomerBankAccountsHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(FinanceCommandMapper::bankAccountListing($request->attributes->get('principal'), $customerId));

        // A customer owns a handful of accounts, so the set is returned whole rather than by the page.
        return ApiResponder::success($request, BankAccountResource::collection($result->bankAccounts)->resolve($request));
    }

    public function store(CreateBankAccountRequest $request, CreateCustomerBankAccountHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(FinanceCommandMapper::bankAccountDraft($request->attributes->get('principal'), $customerId, $request->validated()));

        return ApiResponder::success($request, new BankAccountResource($result->bankAccount), status: 201);
    }
}
