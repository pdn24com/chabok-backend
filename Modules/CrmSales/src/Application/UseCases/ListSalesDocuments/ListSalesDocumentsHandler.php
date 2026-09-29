<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\UseCases\ListSalesDocuments;

use Modules\CrmSales\Application\Contracts\SalesDocumentAccessGuardInterface;
use Modules\CrmSales\Application\Repositories\SalesDocumentRepositoryInterface;

final readonly class ListSalesDocumentsHandler
{
    public function __construct(
        private SalesDocumentAccessGuardInterface $accessGuard,
        private SalesDocumentRepositoryInterface $salesDocumentRepository,
    ) {}

    public function handle(ListSalesDocumentsCommand $command): ListSalesDocumentsResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);

        return new ListSalesDocumentsResult($this->salesDocumentRepository->listForTenant($hqId, $command->filters));
    }
}
