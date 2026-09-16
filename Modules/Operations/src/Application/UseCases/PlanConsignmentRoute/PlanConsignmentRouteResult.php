<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\PlanConsignmentRoute;

final readonly class PlanConsignmentRouteResult
{
    public function __construct(public array $data)
    {
    }
}
