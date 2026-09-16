<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateRouteDefinition;

final readonly class CreateRouteDefinitionResult
{
    public function __construct(public array $data)
    {
    }
}
