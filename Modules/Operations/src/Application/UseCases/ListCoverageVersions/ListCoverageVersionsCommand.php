<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListCoverageVersions;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListCoverageVersionsCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $policyId,
        public int $page = 1,
        public int $perPage = 20,
    )
    {
    }
}
