<?php

declare(strict_types=1);

namespace Modules\Outbox\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Application\Contracts\TransactionManager;
/** Preserves the worker's existing single-attempt transaction semantics. */

final class SingleAttemptTransactionManager implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
