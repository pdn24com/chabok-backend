<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Application\Contracts\TransactionManager;

final class LaravelTransactionManager implements TransactionManager
{
    public function __construct(private readonly int $attempts = 3)
    {
    }

    public function run(callable $callback): mixed
    {
        return DB::transaction($callback, $this->attempts);
    }
}
