<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;

interface ManifestRowProcessorInterface
{
    public function applyRows(AuthenticatedPrincipal $actor, string $node, ManifestRecord $m, string $correlationId, bool $exceptionApproval): int;

    public function eligibleRows(AuthenticatedPrincipal $actor, string $node, ManifestRecord $m): array;
}
