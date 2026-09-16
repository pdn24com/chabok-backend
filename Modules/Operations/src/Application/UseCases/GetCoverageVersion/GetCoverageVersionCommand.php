<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetCoverageVersion;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetCoverageVersionCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $policyId, public string $versionId)
    {
    }
}
