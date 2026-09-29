<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\UseCases\ListCustomerContracts;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmSales\Infrastructure\Persistence\Models\ContractRecord;

/** The contracts of one customer, newest first, with the archive files attached to each. */
final readonly class ListCustomerContractsResult
{
    /**
     * @param  Collection<int, ContractRecord>  $contracts
     * @param  array<string, list<array{document_id: string, title: string}>>  $documents  contract ID to its files
     */
    public function __construct(
        public Collection $contracts,
        public array $documents,
    ) {}
}
