<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\UseCases\CreateCustomerContract;

use Modules\CrmSales\Infrastructure\Persistence\Models\ContractRecord;

/** The stored contract; it starts without documents, which are attached through the archive afterwards. */
final readonly class CreateCustomerContractResult
{
    public function __construct(
        public ContractRecord $contract,
    ) {}
}
