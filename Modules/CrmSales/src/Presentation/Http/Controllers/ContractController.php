<?php

declare(strict_types=1);

namespace Modules\CrmSales\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CrmSales\Application\UseCases\CreateCustomerContract\CreateCustomerContractHandler;
use Modules\CrmSales\Application\UseCases\ListCustomerContracts\ListCustomerContractsHandler;
use Modules\CrmSales\Presentation\Http\Requests\CreateContractRequest;
use Modules\CrmSales\Presentation\Http\Resources\ContractResource;
use Modules\CrmSales\Presentation\Mappers\ContractCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

/** The contract file of a customer: what was signed, for how long and for how much. */
final class ContractController
{
    public function index(Request $request, ListCustomerContractsHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(ContractCommandMapper::listing($request->attributes->get('principal'), $customerId));

        return ApiResponder::success($request, $result->contracts
            ->map(fn ($contract): array => (new ContractResource($contract, $result->documents[$contract->contract_id] ?? []))->resolve($request))
            ->all());
    }

    public function store(CreateContractRequest $request, CreateCustomerContractHandler $handler, string $customerId): JsonResponse
    {
        $result = $handler->handle(ContractCommandMapper::draft($request->attributes->get('principal'), $customerId, $request->validated()));

        return ApiResponder::success($request, new ContractResource($result->contract), status: 201);
    }
}
