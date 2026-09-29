<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Presentation\Mappers;

use Modules\CrmCatalog\Application\Dto\IndustryListFiltersDto;
use Modules\CrmCatalog\Application\UseCases\ListIndustries\ListIndustriesCommand;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final class IndustryCommandMapper
{
    public static function listing(AuthenticatedPrincipal $actor, array $input): ListIndustriesCommand
    {
        return new ListIndustriesCommand($actor, new IndustryListFiltersDto(
            page: (int) ($input['page'] ?? 1),
            perPage: (int) ($input['per_page'] ?? 25),
            search: $input['search'] ?? null,
            isActive: isset($input['is_active']) ? (bool) $input['is_active'] : null,
        ));
    }
}
