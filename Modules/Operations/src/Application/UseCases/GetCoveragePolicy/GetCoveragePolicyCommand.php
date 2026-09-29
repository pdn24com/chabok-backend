<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetCoveragePolicy;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class GetCoveragePolicyCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $id) {}
}
