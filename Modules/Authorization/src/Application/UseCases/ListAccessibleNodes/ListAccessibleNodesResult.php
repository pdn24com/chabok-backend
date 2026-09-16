<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ListAccessibleNodes;

final readonly class ListAccessibleNodesResult
{
    public function __construct(public array $data)
    {
    }
}
