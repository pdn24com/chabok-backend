<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Contracts;

interface HierarchyEditorInterface
{
    public function replaceParent(string $hqId, string $areaId, ?string $parentId): void;
}
