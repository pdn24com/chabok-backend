<?php

declare(strict_types=1);

namespace Modules\ArchitectureProof\Application\Services;

use Modules\ArchitectureProof\Application\Contracts\ResolveArchitectureProofInterface;
use Modules\ArchitectureProof\Domain\Constants\ArchitectureMarker;

final class ResolveArchitectureProof implements ResolveArchitectureProofInterface
{
    public function moduleId(): string
    {
        return ArchitectureMarker::MODULE_ID;
    }
}
