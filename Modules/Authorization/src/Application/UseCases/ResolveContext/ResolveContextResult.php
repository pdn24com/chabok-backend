<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ResolveContext;

final readonly class ResolveContextResult
{
    public function __construct(public array $data)
    {
    }
}
