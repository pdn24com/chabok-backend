<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\GetConsignmentFilterOptions;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class GetConsignmentFilterOptionsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $nodeId) {}
}
