<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ResolveRouteDefinition;

final readonly class ResolveRouteDefinitionResult
{
    public function __construct(public array $data)
    {
    }
}
