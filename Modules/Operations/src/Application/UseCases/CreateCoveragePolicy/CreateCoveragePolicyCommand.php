<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateCoveragePolicy;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Dto\CoveragePolicyDto;

final readonly class CreateCoveragePolicyCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public CoveragePolicyDto $input,
        public string $correlationId,
    ) {}
}
