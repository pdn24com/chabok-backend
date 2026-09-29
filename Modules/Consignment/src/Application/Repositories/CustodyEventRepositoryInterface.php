<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Repositories;

interface CustodyEventRepositoryInterface
{
    /** @param list<array<string, mixed>> $rows */
    public function insert(array $rows): void;
}
