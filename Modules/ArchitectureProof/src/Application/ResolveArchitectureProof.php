<?php

declare(strict_types=1);

namespace Modules\ArchitectureProof\Application;

use Modules\ArchitectureProof\Domain\ArchitectureMarker;

final class ResolveArchitectureProof
{
    public function moduleId(): string
    {
        return ArchitectureMarker::MODULE_ID;
    }
}
