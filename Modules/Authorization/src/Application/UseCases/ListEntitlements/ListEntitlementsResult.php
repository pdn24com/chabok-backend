<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ListEntitlements;

final readonly class ListEntitlementsResult
{
    public function __construct(public array $data)
    {
    }
}
