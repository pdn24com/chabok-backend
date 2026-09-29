<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;
use Modules\Operations\Infrastructure\Persistence\Models\OperationalExceptionCaseRecord;

interface ManifestWorkflowGuardInterface
{
    public function lockedManifest(AuthenticatedPrincipal $actor, string $node, string $id): ManifestRecord;

    public function manifestVersion(ManifestRecord $m, int $expected): void;

    public function lockedPendingCase(AuthenticatedPrincipal $actor, string $manifest): OperationalExceptionCaseRecord;

    public function exceptionVersion(OperationalExceptionCaseRecord $case, int $expected): void;

    public function differentReviewer(AuthenticatedPrincipal $actor, OperationalExceptionCaseRecord $case): void;
}
