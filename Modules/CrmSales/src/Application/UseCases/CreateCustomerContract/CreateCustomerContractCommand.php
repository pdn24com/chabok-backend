<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\UseCases\CreateCustomerContract;

use Modules\CrmSales\Application\Dto\ContractDraftDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CreateCustomerContractCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $customerId,
        public ContractDraftDto $input,
    ) {}
}
