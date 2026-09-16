<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetRouteDefinition;

final readonly class GetRouteDefinitionResult
{
    public function __construct(public array $data)
    {
    }
}
