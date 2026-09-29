<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\UseCases\ListIndustries;

use Modules\CrmCatalog\Application\Contracts\IndustryAccessGuardInterface;
use Modules\CrmCatalog\Application\Repositories\IndustryRepositoryInterface;

final readonly class ListIndustriesHandler
{
    public function __construct(
        private IndustryAccessGuardInterface $accessGuard,
        private IndustryRepositoryInterface $industryRepository,
    ) {}

    public function handle(ListIndustriesCommand $command): ListIndustriesResult
    {
        $hqId = $this->accessGuard->assertCanList($command->actor);

        return new ListIndustriesResult($this->industryRepository->paginateForTenant($hqId, $command->filters));
    }
}
