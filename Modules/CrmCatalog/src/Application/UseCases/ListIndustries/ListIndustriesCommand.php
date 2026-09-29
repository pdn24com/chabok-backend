<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\UseCases\ListIndustries;

use Modules\CrmCatalog\Application\Dto\IndustryListFiltersDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListIndustriesCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public IndustryListFiltersDto $filters = new IndustryListFiltersDto,
    ) {}
}
