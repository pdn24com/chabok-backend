<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\CreateNode;

final readonly class CreateNodeResult
{
    public function __construct(public array $data)
    {
    }
}
