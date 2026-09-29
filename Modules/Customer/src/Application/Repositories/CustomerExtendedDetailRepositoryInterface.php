<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Repositories;

use Modules\Customer\Infrastructure\Persistence\Models\CustomerExtendedDetailRecord;

interface CustomerExtendedDetailRepositoryInterface
{
    /** The single extended-details row of a customer, with the evaluator of the qualification named. */
    public function findForCustomer(string $hqId, string $customerId): ?CustomerExtendedDetailRecord;

    /**
     * Writes that row, inserting it the first time and replacing the named columns afterwards. One
     * statement, so two writers racing on the first save cannot both insert.
     *
     * @param  array<string, mixed>  $attributes  the complete row, used when it is inserted
     * @param  list<string>  $changes  the columns overwritten when the row is already there
     */
    public function save(array $attributes, array $changes): void;
}
