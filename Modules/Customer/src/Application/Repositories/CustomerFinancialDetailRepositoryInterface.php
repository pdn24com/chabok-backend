<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Repositories;

use Modules\Customer\Infrastructure\Persistence\Models\CustomerFinancialDetailRecord;

interface CustomerFinancialDetailRepositoryInterface
{
    public function findForCustomer(string $hqId, string $customerId): ?CustomerFinancialDetailRecord;

    /**
     * Writes the single financial-summary row of a customer, inserting it the first time and replacing
     * the named columns afterwards. One statement, so two writers racing on the first save cannot both
     * insert.
     *
     * @param  array<string, mixed>  $attributes  the complete row, used when it is inserted
     * @param  list<string>  $changes  the columns overwritten when the row is already there
     */
    public function save(array $attributes, array $changes): void;
}
