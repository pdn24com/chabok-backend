<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListCoveragePolicies;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListCoveragePoliciesCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $filters)
    {
    }
}
