<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ListPickupCommitmentWindows;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListPickupCommitmentWindowsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $nodeId, public ?string $at = null)
    {
    }
}
