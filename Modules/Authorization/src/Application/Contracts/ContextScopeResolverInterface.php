<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Contracts;

interface ContextScopeResolverInterface
{
    public function accessibleNodeIds(string $hqId, array $assignments): array;

    public function descendantAreaIds(string $hqId, string $areaId): array;
}
