<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

interface MatrixWorkbookStorage
{
    public function rows(string $encoded): array;

    public function sample(array $titles): array;
}
