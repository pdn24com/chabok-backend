<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\UseCases\ListSalesDocuments;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmSales\Application\Contracts\SalesDocumentAccessGuardInterface;
use Modules\CrmSales\Application\Repositories\SalesDocumentRepositoryInterface;

final readonly class ListSalesDocumentsHandler
{
    public function __construct(
        private SalesDocumentAccessGuardInterface $accessGuard,
        private SalesDocumentRepositoryInterface $salesDocuments,
    ) {}

    /** @return Collection<int, \Modules\CrmSales\Infrastructure\Persistence\Models\SalesDocumentRecord> */
    public function handle(ListSalesDocumentsCommand $command): Collection
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);

        return $this->salesDocuments->listForTenant($hqId, $command->filters);
    }
}
