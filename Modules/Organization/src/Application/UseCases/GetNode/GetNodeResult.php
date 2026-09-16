<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\GetNode;

final readonly class GetNodeResult
{
    public function __construct(public array $data)
    {
    }
}
