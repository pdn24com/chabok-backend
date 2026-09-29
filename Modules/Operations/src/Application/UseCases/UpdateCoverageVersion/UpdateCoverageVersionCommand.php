<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\UpdateCoverageVersion;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Dto\CoverageVersionChangesDto;

final readonly class UpdateCoverageVersionCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $policyId,
        public string $versionId,
        public CoverageVersionChangesDto $input,
        public string $correlationId,
    ) {}
}
