<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\TransitionCoverageVersion;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class TransitionCoverageVersionCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $policyId,
        public string $versionId,
        public string $action,
        public int $expected,
        public ?string $note,
        public string $correlationId,
    ) {}
}
