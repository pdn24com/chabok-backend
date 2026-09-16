<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\UpdateNode;

final readonly class UpdateNodeResult
{
    public function __construct(public array $data)
    {
    }
}
