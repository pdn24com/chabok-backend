<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Data;

/** @template T */

final readonly class Page
{
    /** @param list<T> $rows */

    public function __construct(public array $rows, public int $page, public int $pageSize, public int $totalRows)
    {
    }
    /** @return list<T> */

    public function items(): array
    {
        return $this->rows;
    }

    public function currentPage(): int
    {
        return $this->page;
    }

    public function perPage(): int
    {
        return $this->pageSize;
    }

    public function total(): int
    {
        return $this->totalRows;
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->totalRows / $this->pageSize));
    }
}
