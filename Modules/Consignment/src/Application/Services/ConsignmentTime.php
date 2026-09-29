<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Consignment\Application\Contracts\ConsignmentTimeInterface;

final readonly class ConsignmentTime implements ConsignmentTimeInterface
{
    public function time(mixed $value): string
    {
        return CarbonImmutable::parse((string) $value, 'UTC')->utc()->toISOString();
    }

    public function databaseTime(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value)->utc()->format('Y-m-d H:i:s.u');
    }
}
