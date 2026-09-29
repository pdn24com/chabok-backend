<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;
use Modules\Operations\Infrastructure\Persistence\Models\OperationalExceptionCaseRecord;

interface ManifestExceptionWriterInterface
{
    public function submitException(AuthenticatedPrincipal $actor, string $node, ManifestRecord $m, string $code, string $description, string $correlationId): int;

    public function insertException(AuthenticatedPrincipal $actor, ManifestRecord $m, int $sequence, string $code, string $description): string;

    public function exceptionHistory(AuthenticatedPrincipal $actor, OperationalExceptionCaseRecord $case, string $action, ?string $reason, int $manifestVersion, int $exceptionVersion): void;
}
