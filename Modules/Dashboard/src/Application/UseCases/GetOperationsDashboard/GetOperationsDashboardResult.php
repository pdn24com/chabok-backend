<?php

declare(strict_types=1);

namespace Modules\Dashboard\Application\UseCases\GetOperationsDashboard;

final readonly class GetOperationsDashboardResult
{
    public function __construct(public array $data)
    {
    }
}
