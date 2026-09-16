<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ClusterConsignmentRoute;

final readonly class ClusterConsignmentRouteResult
{
    public function __construct(public array $data)
    {
    }
}
