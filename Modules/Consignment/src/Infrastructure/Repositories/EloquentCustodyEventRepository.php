<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Repositories;

use Modules\Consignment\Application\Repositories\CustodyEventRepositoryInterface;
use Modules\Consignment\Infrastructure\Persistence\Models\CustodyEventRecord;

final class EloquentCustodyEventRepository implements CustodyEventRepositoryInterface
{
    public function insert(array $rows): void
    {
        CustodyEventRecord::query()->insert($rows);
    }
}
