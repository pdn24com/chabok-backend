<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\UseCases\ListCatalogPersonas;

use Modules\CrmCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\CrmCatalog\Application\Repositories\CatalogPersonaRepositoryInterface;

final readonly class ListCatalogPersonasHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $accessGuard,
        private CatalogPersonaRepositoryInterface $catalogPersonaRepository,
    ) {}

    public function handle(ListCatalogPersonasCommand $command): ListCatalogPersonasResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);

        return new ListCatalogPersonasResult($this->catalogPersonaRepository->listForTenant($hqId, $command->activeOnly));
    }
}
