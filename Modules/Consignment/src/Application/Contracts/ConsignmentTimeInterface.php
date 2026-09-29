<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Contracts;

interface ConsignmentTimeInterface
{
    public function time(mixed $value): string;

    public function databaseTime(mixed $value): ?string;
}
