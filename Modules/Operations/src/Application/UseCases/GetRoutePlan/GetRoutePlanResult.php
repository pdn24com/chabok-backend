<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetRoutePlan;

final readonly class GetRoutePlanResult
{
    public function __construct(public array $data)
    {
    }
}
