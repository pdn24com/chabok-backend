<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ListOperationalStatuses;

final readonly class ListOperationalStatusesResult
{
    public function __construct(public array $data)
    {
    }
}
