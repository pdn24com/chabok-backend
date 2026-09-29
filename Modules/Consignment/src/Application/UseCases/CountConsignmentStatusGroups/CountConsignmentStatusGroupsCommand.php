<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\CountConsignmentStatusGroups;

use Modules\Consignment\Application\Dto\ConsignmentFiltersDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CountConsignmentStatusGroupsCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public ConsignmentFiltersDto $filters,
    ) {}
}
