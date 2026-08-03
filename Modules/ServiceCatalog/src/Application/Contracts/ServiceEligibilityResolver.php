<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

interface ServiceEligibilityResolver
{
    /** @param array<string, mixed> $context @return array<string, mixed> */
    public function validateSelection(AuthenticatedPrincipal $actor, string $offeringId, ?string $versionId, array $context): array;
}
