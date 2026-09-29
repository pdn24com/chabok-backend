<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

use Modules\Pricing\Application\Dto\WorkbookFileDto;

interface MatrixWorkbookStorageInterface
{
    /** @return list<array<int, string>> */
    public function rows(string $encoded): array;

    /** @param list<string> $titles */
    public function sample(array $titles): WorkbookFileDto;
}
