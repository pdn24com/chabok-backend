<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateCoverageVersion;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Dto\CoverageVersionDto;

final readonly class CreateCoverageVersionCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $policyId,
        public CoverageVersionDto $input,
        public string $correlationId,
    ) {}
}
