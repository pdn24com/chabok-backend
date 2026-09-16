<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\UpdateCoverageVersion;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class UpdateCoverageVersionCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $policyId,
        public string $versionId,
        public array $input,
        public string $correlationId,
    )
    {
    }
}
